<?php

namespace Database\Seeders;

use App\Modules\Catalog\Database\Seeders\LoadTestProductsSeeder;
use App\Modules\Identity\Database\Seeders\LoadTestUsersSeeder;
use App\Modules\Ordering\Database\Seeders\LoadTestOrdersSeeder;
use Illuminate\Database\Seeder;

class LoadTestSeeder extends Seeder
{
    public const USERS = 5000;

    public const PRODUCTS = 10000;

    public const ORDERS = 200000;

    public function run(): void
    {
        $startedAt = microtime(true);

        $this->call(DatabaseSeeder::class);

        $userIds = $this->resolve(LoadTestUsersSeeder::class)->run(self::USERS);
        $products = $this->resolve(LoadTestProductsSeeder::class)->run(self::PRODUCTS);
        $this->resolve(LoadTestOrdersSeeder::class)->run($userIds, $products, self::ORDERS);

        $this->command->info(sprintf('Load test data ready in %.1fs', microtime(true) - $startedAt));
    }
}
