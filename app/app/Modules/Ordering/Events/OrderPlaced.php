<?php

namespace App\Modules\Ordering\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPlaced implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    /** @param list<array{product_id: int, name: string, quantity: int, unit_price: string}> $items */
    public function __construct(
        public readonly int $orderId,
        public readonly int $userId,
        public readonly string $userEmail,
        public readonly string $userName,
        public readonly string $total,
        public readonly array $items,
        public readonly string $placedAt,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("orders.{$this->orderId}"),
            new PrivateChannel('admin.orders'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->orderId,
            'user_id' => $this->userId,
            'user_name' => $this->userName,
            'total' => $this->total,
            'status' => 'placed',
            'message' => "تم استلام طلبك رقم #{$this->orderId} وهو قيد المعالجة.",
        ];
    }
}
