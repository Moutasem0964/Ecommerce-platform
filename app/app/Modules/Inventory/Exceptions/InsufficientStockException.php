<?php

namespace App\Modules\Inventory\Exceptions;

use App\Modules\Shared\Exceptions\DomainException;

class InsufficientStockException extends DomainException
{
    public function status(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'INSUFFICIENT_STOCK';
    }
}
