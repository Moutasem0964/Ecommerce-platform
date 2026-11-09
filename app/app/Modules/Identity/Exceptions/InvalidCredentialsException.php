<?php

namespace App\Modules\Identity\Exceptions;

use App\Modules\Shared\Exceptions\DomainException;

class InvalidCredentialsException extends DomainException
{
    public function status(): int
    {
        return 401;
    }

    public function errorCode(): string
    {
        return 'INVALID_CREDENTIALS';
    }

    public function publicMessage(): string
    {
        return 'Email or password is incorrect.';
    }
}
