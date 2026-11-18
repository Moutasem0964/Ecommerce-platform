<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Ordering\Events\OrderConfirmed;
use App\Modules\Shared\Messaging\RabbitMq;
use Illuminate\Support\Facades\Log;
use Throwable;

class PublishOrderConfirmation
{
    public function __construct(private readonly RabbitMq $rabbit)
    {
    }

    public function handle(OrderConfirmed $event): void
    {
        try {
            $this->rabbit->publish(
                config('messaging.orders_exchange'),
                config('messaging.order_created_key'),
                [
                    'type' => 'order.created',
                    'order_id' => $event->orderId,
                    'user_id' => $event->userId,
                    'email' => $event->userEmail,
                    'name' => $event->userName,
                    'total' => $event->total,
                    'items' => $event->items,
                    'placed_at' => $event->placedAt,
                ],
                'order-'.$event->orderId,
            );
        } catch (Throwable $e) {
            // Placing an order must never fail because the broker is unreachable.
            Log::error('Failed to publish order.created', [
                'order_id' => $event->orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
