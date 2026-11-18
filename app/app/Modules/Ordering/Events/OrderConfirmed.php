<?php

namespace App\Modules\Ordering\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once an order is confirmed (stock reserved). It is NOT broadcast to browsers:
 * its only job is to trigger the RabbitMQ message that makes the worker send the confirmation email.
 */
class OrderConfirmed
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
}
