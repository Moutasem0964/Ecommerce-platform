<?php

use App\Modules\Catalog\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/products', [ProductController::class, 'index'])->middleware('etag');
Route::get('/products/{product}', [ProductController::class, 'show'])->whereNumber('product')->middleware('etag');
Route::post('/products', [ProductController::class, 'store'])->middleware(['auth:api', 'can:admin']);
Route::patch('/products/{product}', [ProductController::class, 'update'])->middleware(['auth:api', 'can:admin']);
