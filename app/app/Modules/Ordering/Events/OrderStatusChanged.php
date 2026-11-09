<?php

namespace App\Modules\Ordering\Events;

use Illuminate\Foundation\Events\Dispatchable;

class OrderStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly int $orderId,
        public readonly int $userId,
        public readonly string $status,
    ) {
    }
}
