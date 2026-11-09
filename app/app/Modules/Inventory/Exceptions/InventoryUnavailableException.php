<?php

namespace App\Modules\Inventory\Exceptions;

use App\Modules\Shared\Exceptions\DomainException;

class InventoryUnavailableException extends DomainException
{
    public function status(): int
    {
        return 503;
    }

    public function errorCode(): string
    {
        return 'INVENTORY_UNAVAILABLE';
    }

    public function publicMessage(): string
    {
        return 'Inventory service is temporarily unavailable. Please try again.';
    }
}
