<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Auth\JwtService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // "jwt" auth driver: reads the Bearer token, verifies it, and returns the user (or null).
        Auth::viaRequest('jwt', function (Request $request) {
            $token = $request->bearerToken();
            if ($token === null) {
                return null;
            }

            $claims = app(JwtService::class)->verify($token);

            return $claims ? User::find($claims->sub) : null;
        });

        // Authorization rule: only admins pass "can:admin"
        Gate::define('admin', fn (User $user) => $user->isAdmin());

        // Rate limits
        RateLimiter::for('api', fn (Request $request) =>
        Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('login', fn (Request $request) =>
        Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
    }
}
