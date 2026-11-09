<?php

namespace App\Modules\Identity\Database\Seeders;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoUsersSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@test.com'],
            ['name' => 'Admin', 'password' => 'secret123'],
        );
        $admin->role = Role::Admin;
        $admin->save();

        User::firstOrCreate(['email' => 'sara@test.com'], ['name' => 'Sara', 'password' => 'secret123']);
        User::firstOrCreate(['email' => 'hacker@test.com'], ['name' => 'Hacker', 'password' => 'secret123']);
    }
}
