<?php

namespace App\Modules\Ordering\Exceptions;

use App\Modules\Shared\Exceptions\DomainException;

class InvalidStatusTransitionException extends DomainException
{
    public function status(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'INVALID_STATUS_TRANSITION';
    }
}
