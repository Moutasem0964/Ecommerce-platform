<?php

namespace App\Modules\Inventory\Exceptions;

use App\Modules\Shared\Exceptions\DomainException;

class ReservationConflictException extends DomainException
{
    public function status(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'RESERVATION_CONFLICT';
    }
}
