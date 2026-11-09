<?php

namespace App\Modules\Notifications\Notifications;

use Illuminate\Notifications\Notification;

/** Tells a customer their order changed status (confirmed, shipped, delivered, cancelled, failed). */
class OrderStatusNotification extends Notification
{
    public function __construct(
        private readonly int $orderId,
        private readonly string $status,
    ) {
    }

    /** Where it is delivered. "database" = stored in the notifications table, read through the API. */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** What is stored. */
    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->orderId,
            'status' => $this->status,
            'message' => "Your order #{$this->orderId} is now {$this->status}.",
        ];
    }
}
