<?php

namespace App\Modules\Shared\Messaging;

final readonly class RelayResult
{
    public function __construct(
        public int $published,
        public int $pending,
        public ?string $error,
    ) {
    }
}
