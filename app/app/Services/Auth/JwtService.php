<?php

namespace App\Services\Auth;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;
use Throwable;

class JwtService
{
    private const ALGORITHM = 'RS256';

    /**
     * Creates a signed access token for the user.
     *
     * @return array{access_token: string, token_type: string, expires_in: int}
     */
    public function issue(User $user): array
    {
        $now = time();
        $ttlSeconds = config('jwt.ttl_minutes') * 60;

        $claims = [
            'iss' => config('jwt.issuer'),    // who issued it
            'sub' => (string) $user->id,      // who it is about (the user)
            'role' => $user->role->value,     // customer | admin
            'iat' => $now,                    // issued at
            'nbf' => $now,                    // not valid before
            'exp' => $now + $ttlSeconds,      // expires at
            'jti' => (string) Str::uuid(),    // unique token id
        ];

        return [
            'access_token' => JWT::encode($claims, $this->privateKey(), self::ALGORITHM),
            'token_type' => 'Bearer',
            'expires_in' => $ttlSeconds,
        ];
    }


    public function verify(string $token): ?object
    {
        try {
            $claims = JWT::decode($token, new Key($this->publicKey(), self::ALGORITHM));
        } catch (Throwable) {
            return null;
        }

        return ($claims->iss ?? null) === config('jwt.issuer') ? $claims : null;
    }

    private function privateKey(): string
    {
        return file_get_contents(config('jwt.private_key_path'));
    }

    private function publicKey(): string
    {
        return file_get_contents(config('jwt.public_key_path'));
    }
}
