<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => env('FSBMM_ADMIN_EMAIL', 'admin@fsbmm.test')],
            [
                'name' => 'Admin FSBMM',
                'password' => env('FSBMM_ADMIN_PASSWORD', 'password'),
                'role' => User::ROLE_SUPER_ADMIN,
            ]
        );
    }
}
