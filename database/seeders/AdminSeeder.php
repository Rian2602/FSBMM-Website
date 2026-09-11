<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Read via env() (not config) so tests that putenv() before seeding
        // still take effect; there is no baked-in weak default for production.
        $email = env('FSBMM_ADMIN_EMAIL');
        $password = env('FSBMM_ADMIN_PASSWORD');

        if (! $email || ! $password) {
            if (! app()->environment(['local', 'testing'])) {
                throw new \RuntimeException(
                    'FSBMM_ADMIN_EMAIL dan FSBMM_ADMIN_PASSWORD wajib di-set untuk seeding di luar local/testing.',
                );
            }

            // Local/testing convenience only — never relied on in production.
            $email = $email ?: 'admin@fsbmm.test';
            $password = $password ?: 'password';
        }

        User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin FSBMM',
                'password' => $password,
                'role' => User::ROLE_SUPER_ADMIN,
            ],
        );
    }
}
