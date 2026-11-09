<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Data\AuthSession;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Requests\RegisterRequest;
use App\Modules\Identity\Http\Resources\UserResource;
use App\Modules\Identity\Services\AuthService;
use App\Modules\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $session = $this->auth->register(
            $request->validated('name'),
            $request->validated('email'),
            $request->validated('password'),
        );

        return $this->sessionResponse($session, 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $session = $this->auth->login($request->validated('email'), $request->validated('password'));

        return $this->sessionResponse($session);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => (new UserResource($request->user()))->resolve()]);
    }

    private function sessionResponse(AuthSession $session, int $status = 200): JsonResponse
    {
        return response()->json([
            'user' => (new UserResource($session->user))->resolve(),
            ...$session->token,
        ], $status);
    }
}
