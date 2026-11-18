<?php

namespace App\Modules\Shared\Messaging;

use Illuminate\Support\Str;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPSocketConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;

class RabbitMq
{
    private ?AMQPSocketConnection $connection = null;
    private ?AMQPChannel $channel = null;

    public function channel(): AMQPChannel
    {
        if ($this->channel?->isConnected()) {
            return $this->channel;
        }

        $cfg = config('messaging.rabbitmq');
        $heartbeat = (int) $cfg['heartbeat'];

        $this->connection = new AMQPSocketConnection(
            host: $cfg['host'],
            port: $cfg['port'],
            user: $cfg['user'],
            password: $cfg['password'],
            vhost: $cfg['vhost'],
            read_timeout: max($cfg['confirm_timeout'] * 2, $heartbeat * 2),
            write_timeout: $cfg['connect_timeout'],
            heartbeat: $heartbeat,
        );

        $this->channel = $this->connection->channel();
        $this->declareTopology($this->channel);
        $this->channel->confirm_select();

        return $this->channel;
    }

    /** Publish a persistent JSON message and wait for the broker's ack (ms, not the email). */
    public function publish(string $exchange, string $routingKey, array $payload, ?string $messageId = null): void
    {
        $message = new AMQPMessage(json_encode($payload, JSON_THROW_ON_ERROR), [
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'message_id' => $messageId ?? (string) Str::uuid(),
            'timestamp' => time(),
        ]);

        $channel = $this->channel();
        $channel->basic_publish($message, $exchange, $routingKey);
        $channel->wait_for_pending_acks(config('messaging.rabbitmq.confirm_timeout'));
    }

    /** Idempotent declarations: safe from the app and from the worker alike. */
    private function declareTopology(AMQPChannel $channel): void
    {
        $name = fn (string $key): string => (string) config("messaging.$key");

        $channel->exchange_declare($name('orders_exchange'), 'topic', false, true, false);
        $channel->exchange_declare($name('retry_exchange'), 'topic', false, true, false);
        $channel->exchange_declare($name('dlq_exchange'), 'topic', false, true, false);

        // Main queue: rejected messages go to the retry exchange.
        $channel->queue_declare($name('order_confirmation_queue'), false, true, false, false, false, new AMQPTable([
            'x-dead-letter-exchange' => $name('retry_exchange'),
        ]));
        $channel->queue_bind($name('order_confirmation_queue'), $name('orders_exchange'), $name('order_created_key'));

        // Retry queue: holds a failed message for retry_ttl_ms, then dead-letters it back.
        $channel->queue_declare($name('retry_queue'), false, true, false, false, false, new AMQPTable([
            'x-message-ttl' => (int) config('messaging.retry_ttl_ms'),
            'x-dead-letter-exchange' => $name('orders_exchange'),
            'x-dead-letter-routing-key' => $name('order_created_key'),
        ]));
        $channel->queue_bind($name('retry_queue'), $name('retry_exchange'), $name('order_created_key'));

        // Final parking lot for permanently failed messages.
        $channel->queue_declare($name('dlq_queue'), false, true, false, false, false);
        $channel->queue_bind($name('dlq_queue'), $name('dlq_exchange'), $name('order_created_key'));
    }

    public function __destruct()
    {
        try {
            $this->channel?->close();
            $this->connection?->close();
        } catch (Throwable) {
            // Connection already gone - nothing to clean up.
        }
    }
}
