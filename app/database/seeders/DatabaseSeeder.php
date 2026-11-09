<?php

namespace Database\Seeders;

use App\Modules\Identity\Database\Seeders\DemoUsersSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DemoUsersSeeder::class,
        ]);
    }
}
