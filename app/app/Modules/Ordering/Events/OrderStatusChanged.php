<?php

namespace App\Modules\Ordering\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $orderId,
        public readonly int $userId,
        public readonly string $status,
    ) {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("orders.{$this->orderId}");
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->orderId,
            'status' => $this->status,
            'message' => match($this->status) {
                'confirmed' => 'تم تأكيد طلبك وجاري التجهيز.',
                'shipped' => 'تم شحن طلبك وهو في الطريق إليك.',
                'delivered' => 'تم تسليم طلبك بنجاح.',
                'cancelled' => 'تم إلغاء طلبك واسترجاع المخزون.',
                'failed' => 'تعذر معالجة طلبك.',
                'rejected' => 'تم رفض طلبك بسبب عدم توفر المخزون.',
                default => "تم تحديث حالة طلبك إلى {$this->status}.",
            },
        ];
    }
}
