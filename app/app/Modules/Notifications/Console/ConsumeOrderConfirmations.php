<?php

namespace App\Modules\Notifications\Console;

use App\Modules\Notifications\Mail\OrderConfirmationMail;
use App\Modules\Shared\Messaging\RabbitMq;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use RuntimeException;
use Throwable;

class ConsumeOrderConfirmations extends Command
{
    protected $signature = 'notifications:consume-confirmations';

    protected $description = 'Worker: send order confirmation emails (manual ack, bounded retries, DLQ)';

    public function handle(RabbitMq $rabbit): int
    {
        $channel = $rabbit->channel();
        $queue = (string) config('messaging.order_confirmation_queue');

        // One message at a time: a crashed worker leaves at most one unacked message.
        $channel->basic_qos(0, 1, false);

        $channel->basic_consume(
            queue: $queue,
            consumer_tag: 'order-confirmation-worker',
            no_local: false,
            no_ack: false, // manual ack: the broker keeps the message until we ack
            exclusive: false,
            nowait: false,
            callback: fn (AMQPMessage $message) => $this->process($message, $channel),
        );

        $this->info('Consuming '.$queue.' (mailer: '.config('mail.default').' → '.config('mail.mailers.smtp.host').')');

        while ($channel->is_consuming()) {
            $channel->consume();
        }

        return self::SUCCESS;
    }

    private function process(AMQPMessage $message, AMQPChannel $channel): void
    {
        $orderId = '?';

        try {
            $order = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $orderId = $order['order_id'];

            if (config('messaging.fail_sending')) {
                throw new RuntimeException('Simulated email failure (ORDER_EMAIL_FAIL_SENDING).');
            }

            Mail::to($order['email'], $order['name'])->send(new OrderConfirmationMail($order));

            $channel->basic_ack($message->getDeliveryTag());
            $this->info("Order #{$orderId}: confirmation email sent to {$order['email']}");
        } catch (Throwable $e) {
            $this->handleFailure($message, $channel, $orderId, $e);
        }
    }

    private function handleFailure(AMQPMessage $message, AMQPChannel $channel, int|string $orderId, Throwable $e): void
    {
        $attempt = $this->previousFailures($message) + 1;
        $max = (int) config('messaging.max_attempts');

        if ($attempt >= $max) {
            // Persist a copy in the DLQ first (with confirm), only then ack the original.
            $this->parkInDlq($message, $channel, $orderId, $attempt);
            $channel->basic_ack($message->getDeliveryTag());
            $this->error("Order #{$orderId}: failed {$attempt} times — parked in DLQ ({$e->getMessage()})");

            return;
        }

        $ttl = (int) config('messaging.retry_ttl_ms') / 1000;
        // requeue=false -> dead-letter exchange -> retry queue -> back after the TTL.
        $channel->basic_nack($message->getDeliveryTag(), false, false);
        $this->error("Order #{$orderId}: attempt {$attempt}/{$max} failed — retry in {$ttl}s ({$e->getMessage()})");
    }

    /** x-death is maintained by RabbitMQ itself, so the count survives worker crashes. */
    private function previousFailures(AMQPMessage $message): int
    {
        if (! $message->has('application_headers')) {
            return 0;
        }

        $headers = $message->get('application_headers')->getNativeData();

        foreach ($headers['x-death'] ?? [] as $death) {
            if (($death['queue'] ?? '') === config('messaging.order_confirmation_queue')
                && ($death['reason'] ?? '') === 'rejected') {
                return (int) ($death['count'] ?? 0);
            }
        }

        return 0;
    }

    private function parkInDlq(AMQPMessage $message, AMQPChannel $channel, int|string $orderId, int $attempts): void
    {
        $copy = new AMQPMessage($message->getBody(), [
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'message_id' => 'order-'.$orderId,
            'timestamp' => time(),
            'application_headers' => new AMQPTable([
                'x-failed-attempts' => $attempts,
                'x-original-queue' => (string) config('messaging.order_confirmation_queue'),
            ]),
        ]);

        $channel->basic_publish($copy, (string) config('messaging.dlq_exchange'), (string) config('messaging.order_created_key'));
        $channel->wait_for_pending_acks(config('messaging.rabbitmq.confirm_timeout'));
    }
}
