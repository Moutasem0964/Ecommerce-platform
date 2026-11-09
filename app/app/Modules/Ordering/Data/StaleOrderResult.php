<?php

namespace App\Modules\Ordering\Data;

final class StaleOrderResult
{
    public const RELEASED = 'released';
    public const NOTHING_RESERVED = 'nothing_reserved';
    public const INVENTORY_UNAVAILABLE = 'inventory_unavailable';

    public function __construct(
        public readonly int $orderId,
        public readonly string $outcome,
    ) {
    }
}
