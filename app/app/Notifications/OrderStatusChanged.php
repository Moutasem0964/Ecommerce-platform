<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Notification;

/** Tells a customer their order changed status (confirmed, shipped, delivered, cancelled, failed). */
class OrderStatusChanged extends Notification
{
    public function __construct(private readonly Order $order)
    {
    }

    /** Where it is delivered. "database" = stored in the notifications table, read through the API. */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** What is stored. */
    public function toArray(object $notifiable): array
    {
        $status = $this->order->status->value;

        return [
            'order_id' => $this->order->id,
            'status' => $status,
            'message' => "Your order #{$this->order->id} is now {$status}.",
        ];
    }
}
