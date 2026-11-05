<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;

// Every route in this file is served under /api/v1 (see bootstrap/app.php → apiPrefix).

Route::get('/ping', fn () => ['status' => 'ok']);

//  Auth 
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth:api');
});

//  Products 
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::post('/products', [ProductController::class, 'store'])->middleware(['auth:api', 'can:admin']);

//  Orders 
Route::middleware('auth:api')->group(function () {
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->middleware('can:admin');

    //  Notifications 
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
});
