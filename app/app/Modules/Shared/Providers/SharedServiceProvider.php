<?php

namespace App\Modules\Shared\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class SharedServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        $this->loadApiRoutes(__DIR__.'/../Routes/api.php');

        RateLimiter::for('api', fn (Request $request) =>
            Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
    }
}
