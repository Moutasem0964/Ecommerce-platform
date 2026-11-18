<?php

namespace App\Modules\Notifications\Console;

use App\Modules\Notifications\Mail\OrderConfirmationMail;
use App\Modules\Shared\Messaging\RabbitMq;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Exception\AMQPExceptionInterface;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use RuntimeException;
use Throwable;

class ConsumeOrderConfirmations extends Command
{
    protected $signature = 'notifications:consume-confirmations';

    protected $description = 'Worker: send order confirmation emails (manual ack, bounded retries, DLQ, no duplicates)';

    private bool $running = true;

    public function handle(RabbitMq $rabbit): int
    {
        $this->trap([SIGTERM, SIGINT], fn () => $this->running = false);

        $queue = (string) config('messaging.order_confirmation_queue');
        $this->info('Consuming '.$queue.' (mailer: '.config('mail.default').')');

        $down = false;
        while ($this->running) {
            try {
                $channel = $this->subscribe($rabbit, $queue);

                if ($down) {
                    $this->line(now()->toDateTimeString().'  rabbitmq is back, consuming again');
                    $down = false;
                }

                while ($this->running && $channel->is_consuming()) {
                    try {
                        $channel->wait(null, false, 1.0);
                    } catch (AMQPTimeoutException) {
                    }
                }
            } catch (AMQPExceptionInterface $e) {
                if (! $this->running) {
                    break;
                }

                if (! $down) {
                    $this->warn(now()->toDateTimeString().'  rabbitmq unavailable, retrying every 3s: '.$e->getMessage());
                    $down = true;
                }

                $rabbit->reset();
                sleep(3);
            }
        }

        $this->info('Worker stopped cleanly');

        return self::SUCCESS;
    }

    private function subscribe(RabbitMq $rabbit, string $queue): AMQPChannel
    {
        $channel = $rabbit->channel();
        $channel->basic_qos(0, 1, false);

        $channel->basic_consume(
            queue: $queue,
            consumer_tag: 'order-confirmation-worker',
            no_local: false,
            no_ack: false,
            exclusive: false,
            nowait: false,
            callback: fn (AMQPMessage $message) => $this->process($message, $channel),
        );

        return $channel;
    }

    private function process(AMQPMessage $message, AMQPChannel $channel): void
    {
        $orderId = '?';
        $messageId = $message->has('message_id') ? (string) $message->get('message_id') : null;

        try {
            $order = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $orderId = $order['order_id'];

            if ($messageId !== null && $this->alreadySent($messageId)) {
                $channel->basic_ack($message->getDeliveryTag());
                $this->warn("Order #{$orderId}: email already sent, duplicate message skipped");

                return;
            }

            if (config('messaging.fail_sending')) {
                throw new RuntimeException('Simulated email failure (ORDER_EMAIL_FAIL_SENDING).');
            }

            Mail::to($order['email'], $order['name'])->send(new OrderConfirmationMail($order));
        } catch (Throwable $e) {
            $this->handleFailure($message, $channel, $orderId, $e);

            return;
        }

        if ($messageId !== null) {
            $this->markSent($messageId, $orderId);
        }

        $channel->basic_ack($message->getDeliveryTag());
        $this->info("Order #{$orderId}: confirmation email sent to {$order['email']}");
    }

    private function alreadySent(string $messageId): bool
    {
        return DB::table('processed_messages')->useWritePdo()->where('message_id', $messageId)->exists();
    }

    private function markSent(string $messageId, int|string $orderId): void
    {
        try {
            DB::table('processed_messages')->insertOrIgnore([
                'message_id' => $messageId,
                'processed_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Email sent but not recorded as processed', ['order_id' => $orderId, 'error' => $e->getMessage()]);
        }
    }

    private function handleFailure(AMQPMessage $message, AMQPChannel $channel, int|string $orderId, Throwable $e): void
    {
        $attempt = $this->previousFailures($message) + 1;
        $max = (int) config('messaging.max_attempts');

        if ($attempt >= $max) {
            $this->parkInDlq($message, $channel, $orderId, $attempt);
            $channel->basic_ack($message->getDeliveryTag());
            $this->error("Order #{$orderId}: failed {$attempt} times — parked in DLQ ({$e->getMessage()})");

            return;
        }

        $ttlMs = (int) config('messaging.retry_ttl_ms');
        $this->scheduleRetry($message, $channel, $attempt, $ttlMs);
        $channel->basic_ack($message->getDeliveryTag());
        $this->error("Order #{$orderId}: attempt {$attempt}/{$max} failed — retry in ".($ttlMs / 1000)."s ({$e->getMessage()})");
    }

    private function previousFailures(AMQPMessage $message): int
    {
        if (! $message->has('application_headers')) {
            return 0;
        }

        return (int) ($message->get('application_headers')->getNativeData()['x-attempts'] ?? 0);
    }

    private function scheduleRetry(AMQPMessage $message, AMQPChannel $channel, int $attempt, int $ttlMs): void
    {
        $copy = new AMQPMessage($message->getBody(), $this->properties($message, [
            'expiration' => (string) $ttlMs,
            'application_headers' => new AMQPTable(['x-attempts' => $attempt]),
        ]));

        $channel->basic_publish($copy, (string) config('messaging.retry_exchange'), (string) config('messaging.retry_key'));
        $channel->wait_for_pending_acks(config('messaging.rabbitmq.confirm_timeout'));
    }

    private function parkInDlq(AMQPMessage $message, AMQPChannel $channel, int|string $orderId, int $attempts): void
    {
        $copy = new AMQPMessage($message->getBody(), $this->properties($message, [
            'application_headers' => new AMQPTable([
                'x-failed-attempts' => $attempts,
                'x-original-queue' => (string) config('messaging.order_confirmation_queue'),
            ]),
        ], 'order-'.$orderId));

        $channel->basic_publish($copy, (string) config('messaging.dlq_exchange'), (string) config('messaging.order_created_key'));
        $channel->wait_for_pending_acks(config('messaging.rabbitmq.confirm_timeout'));
    }

    private function properties(AMQPMessage $message, array $extra, ?string $fallbackId = null): array
    {
        $messageId = $message->has('message_id') ? $message->get('message_id') : $fallbackId;

        return array_filter([
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'message_id' => $messageId,
            'timestamp' => time(),
            ...$extra,
        ], fn ($value) => $value !== null);
    }
}
