<?php

namespace App\Modules\Notifications\Providers;

use App\Modules\Notifications\Console\ConsumeOrderConfirmations;
use App\Modules\Notifications\Console\ReplayDeadLetters;
use App\Modules\Notifications\Listeners\PublishOrderConfirmation;
use App\Modules\Notifications\Listeners\SendOrderStatusNotification;
use App\Modules\Ordering\Events\OrderConfirmed;
use App\Modules\Ordering\Events\OrderStatusChanged;
use App\Modules\Shared\Providers\ModuleServiceProvider;
use Illuminate\Support\Facades\Event;

class NotificationsServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadApiRoutes(__DIR__.'/../Routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([ConsumeOrderConfirmations::class, ReplayDeadLetters::class]);
        }

        Event::listen(OrderStatusChanged::class, SendOrderStatusNotification::class);
        Event::listen(OrderConfirmed::class, PublishOrderConfirmation::class);
    }
}
