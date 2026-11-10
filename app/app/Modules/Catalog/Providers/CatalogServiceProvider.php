<?php

namespace App\Modules\Catalog\Providers;

use App\Modules\Catalog\Contracts\CatalogApi;
use App\Modules\Catalog\Services\EloquentCatalogApi;
use App\Modules\Catalog\Services\ProductCache;
use App\Modules\Shared\Providers\ModuleServiceProvider;

class CatalogServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/catalog.php', 'catalog');

        $this->app->singleton(CatalogApi::class, EloquentCatalogApi::class);
        $this->app->singleton(ProductCache::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadApiRoutes(__DIR__.'/../Routes/api.php');
    }
}
