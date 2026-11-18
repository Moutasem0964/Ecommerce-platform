<?php

namespace App\Modules\Notifications\Console;

use App\Modules\Shared\Messaging\RabbitMq;
use Illuminate\Console\Command;
use PhpAmqpLib\Message\AMQPMessage;

class ReplayDeadLetters extends Command
{
    protected $signature = 'notifications:replay-dlq';

    protected $description = 'Re-publish every message parked in the DLQ back to the main queue (fresh attempts)';

    public function handle(RabbitMq $rabbit): int
    {
        $channel = $rabbit->channel();
        $dlq = (string) config('messaging.dlq_queue');
        $replayed = 0;

        while ($message = $channel->basic_get($dlq)) {
            $props = [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'timestamp' => time(),
            ];
            if ($message->has('message_id')) {
                $props['message_id'] = $message->get('message_id');
            }

            $channel->basic_publish(new AMQPMessage($message->getBody(), $props),
                (string) config('messaging.orders_exchange'),
                (string) config('messaging.order_created_key'),
            );
            $channel->wait_for_pending_acks(config('messaging.rabbitmq.confirm_timeout'));
            $channel->basic_ack($message->getDeliveryTag());
            $replayed++;
        }

        $this->info("Replayed {$replayed} message(s) from the DLQ.");

        return self::SUCCESS;
    }
}
