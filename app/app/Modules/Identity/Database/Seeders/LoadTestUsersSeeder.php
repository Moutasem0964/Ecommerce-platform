<?php

namespace App\Modules\Identity\Database\Seeders;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LoadTestUsersSeeder extends Seeder
{
    private const CHUNK = 1000;

    public function run(int $count): array
    {
        $existing = User::where('email', 'like', 'load%@test.local')->count();
        $password = Hash::make('secret123');
        $now = now()->toDateTimeString();

        for ($n = $existing + 1; $n <= $count; $n += self::CHUNK) {
            $rows = [];
            for ($i = $n; $i < min($n + self::CHUNK, $count + 1); $i++) {
                $rows[] = [
                    'name' => "Load User {$i}",
                    'email' => "load{$i}@test.local",
                    'password' => $password,
                    'role' => Role::Customer->value,
                    'email_verified_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('users')->insert($rows);
        }

        $this->command?->info('Users: '.User::count());

        return User::where('role', Role::Customer->value)->orderBy('id')->pluck('id')->all();
    }
}
