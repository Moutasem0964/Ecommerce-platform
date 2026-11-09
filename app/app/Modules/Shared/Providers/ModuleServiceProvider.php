<?php

namespace App\Modules\Shared\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

abstract class ModuleServiceProvider extends ServiceProvider
{
    protected function loadApiRoutes(string $path): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware('api')->prefix('api/v1')->group($path);
    }
}
