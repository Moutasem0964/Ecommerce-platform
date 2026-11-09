<?php

namespace App\Modules\Identity\Providers;

use App\Modules\Identity\Console\GenerateJwtKeys;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\JwtService;
use App\Modules\Shared\Providers\ModuleServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;

class IdentityServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/jwt.php', 'jwt');

        $this->app->singleton(JwtService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadApiRoutes(__DIR__.'/../Routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([GenerateJwtKeys::class]);
        }

        Relation::enforceMorphMap(['user' => User::class]);

        Auth::viaRequest('jwt', function (Request $request) {
            $token = $request->bearerToken();
            if ($token === null) {
                return null;
            }

            $claims = $this->app->make(JwtService::class)->verify($token);

            return $claims ? User::find($claims->sub) : null;
        });

        Gate::define('admin', fn (User $user) => $user->isAdmin());

        RateLimiter::for('login', fn (Request $request) =>
            Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
    }
}
