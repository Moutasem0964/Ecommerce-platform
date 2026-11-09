<?php

namespace App\Modules\Identity\Data;

use App\Modules\Identity\Models\User;

final class AuthSession
{
    public function __construct(
        public readonly User $user,
        public readonly array $token,
    ) {
    }
}
