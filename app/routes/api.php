<?php

use Illuminate\Support\Facades\Route;

// Every route in this file is served under /api/v1 (see bootstrap/app.php → apiPrefix).

Route::get('/ping', fn () => ['status' => 'ok']);
