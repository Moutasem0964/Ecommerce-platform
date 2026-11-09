<?php

namespace App\Modules\Shared\Exceptions;

use RuntimeException;

abstract class DomainException extends RuntimeException
{
    abstract public function status(): int;

    abstract public function errorCode(): string;

    public function publicMessage(): string
    {
        return $this->getMessage();
    }
}
