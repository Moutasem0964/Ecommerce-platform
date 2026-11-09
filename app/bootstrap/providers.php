<?php

use App\Modules\Catalog\Providers\CatalogServiceProvider;
use App\Modules\Identity\Providers\IdentityServiceProvider;
use App\Modules\Inventory\Providers\InventoryServiceProvider;
use App\Modules\Notifications\Providers\NotificationsServiceProvider;
use App\Modules\Ordering\Providers\OrderingServiceProvider;
use App\Modules\Shared\Providers\SharedServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    IdentityServiceProvider::class,
    InventoryServiceProvider::class,
    CatalogServiceProvider::class,
    OrderingServiceProvider::class,
    NotificationsServiceProvider::class,
];
