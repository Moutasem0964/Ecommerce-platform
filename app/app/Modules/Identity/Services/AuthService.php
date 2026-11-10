<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Data\AuthSession;
use App\Modules\Identity\Exceptions\InvalidCredentialsException;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(private readonly JwtService $jwt)
    {
    }

    public function register(string $name, string $email, string $password): AuthSession
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ])->fresh();

        return new AuthSession($user, $this->jwt->issue($user));
    }

    public function login(string $email, string $password): AuthSession
    {
        $user = User::query()->useWritePdo()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new InvalidCredentialsException();
        }

        return new AuthSession($user, $this->jwt->issue($user));
    }
}
