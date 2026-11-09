<?php

namespace Database\Seeders;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Demo accounts used by the README, Swagger and the Postman collection.
     * Safe to run more than once (firstOrCreate).
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@test.com'],
            ['name' => 'Admin', 'password' => 'secret123'],
        );
        $admin->role = Role::Admin; // role is not mass-assignable on purpose
        $admin->save();

        User::firstOrCreate(['email' => 'sara@test.com'], ['name' => 'Sara', 'password' => 'secret123']);
        User::firstOrCreate(['email' => 'hacker@test.com'], ['name' => 'Hacker', 'password' => 'secret123']);
    }
}
