<?php

namespace App\Modules\Ordering\Providers;

use App\Modules\Ordering\Console\ReleaseStaleOrders;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Policies\OrderPolicy;
use App\Modules\Shared\Providers\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;

class OrderingServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/orders.php', 'orders');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadApiRoutes(__DIR__.'/../Routes/api.php');

        Gate::policy(Order::class, OrderPolicy::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ReleaseStaleOrders::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('orders:release-stale')->everyMinute()->withoutOverlapping();
        });
    }
}
