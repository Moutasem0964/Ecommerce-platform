<?php

namespace App\Modules\Inventory\Providers;

use App\Modules\Inventory\Clients\InventoryClient;
use Illuminate\Support\ServiceProvider;

class InventoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/inventory.php', 'inventory');

        $this->app->singleton(InventoryClient::class);
    }
}
