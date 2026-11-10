<?php

namespace App\Modules\Shared\Providers;

use App\Modules\Shared\Cache\CircuitBreakerFailoverStore;
use App\Modules\Shared\Console\MonitorHealth;
use App\Modules\Shared\Http\Middleware\ETag;
use App\Modules\Shared\Http\Middleware\ReadYourWrites;
use App\Modules\Shared\Services\ReplicaHealth;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class SharedServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/rate_limits.php', 'rate_limits');
        $this->mergeConfigFrom(__DIR__.'/../config/http_cache.php', 'http_cache');
        $this->mergeConfigFrom(__DIR__.'/../config/read_your_writes.php', 'read_your_writes');
        $this->mergeConfigFrom(__DIR__.'/../config/replica.php', 'replica');
        $this->mergeConfigFrom(__DIR__.'/../config/cache_breaker.php', 'cache_breaker');

        $this->app->booting(function () {
            Cache::extend('failover-breaker', fn ($app, array $config) => Cache::repository(
                new CircuitBreakerFailoverStore(
                    $app['cache'],
                    $app['events'],
                    $config['stores'],
                    storage_path('framework/cache-breaker'),
                    config('cache_breaker.open_seconds'),
                ),
                ['events' => false, ...$config],
            ));
        });

        $this->app->scoped(ReplicaHealth::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([MonitorHealth::class]);
        }

        Route::aliasMiddleware('etag', ETag::class);
        Route::prependMiddlewareToGroup('api', ReadYourWrites::class);

        $this->loadApiRoutes(__DIR__.'/../Routes/api.php');

        RateLimiter::for('api', fn (Request $request) =>
            Limit::perMinute(config('rate_limits.api_per_minute'))->by($request->user()?->id ?: $request->ip()));
    }
}
