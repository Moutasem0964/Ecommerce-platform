<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

// Every route in this file is served under /api/v1 (see bootstrap/app.php → apiPrefix).

Route::get('/ping', fn () => ['status' => 'ok']);

// ---------- Auth ----------
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth:api');
});
