<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => config('muzicarap.admin.email')],
            [
                'name' => config('muzicarap.admin.name'),
                'password' => config('muzicarap.admin.password'),
                'email_verified_at' => now(),
                'is_admin' => true,
            ],
        );
    }
}
