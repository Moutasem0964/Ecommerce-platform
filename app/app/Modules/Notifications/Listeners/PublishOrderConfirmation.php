<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Ordering\Events\OrderConfirmed;
use App\Modules\Shared\Messaging\Outbox;

class PublishOrderConfirmation
{
    public function __construct(private readonly Outbox $outbox)
    {
    }

    public function handle(OrderConfirmed $event): void
    {
        $this->outbox->record(
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
    }
}
