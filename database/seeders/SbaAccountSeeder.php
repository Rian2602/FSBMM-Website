<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class SbaAccountSeeder extends Seeder
{
    public function run(): void
    {
        // Same convention as AdminSeeder: no baked-in weak default for production.
        $password = env('FSBMM_SBA_PASSWORD');

        if (! $password) {
            if (! app()->environment(['local', 'testing'])) {
                throw new \RuntimeException(
                    'FSBMM_SBA_PASSWORD wajib di-set untuk seeding di luar local/testing.'
                );
            }

            $password = 'password'; // local/testing convenience only
        }

        $accounts = [
            ['name' => 'Pengurus SPM Kecap Bango', 'email' => 'pengurus@spm-kecap-bango.fsbmm.test', 'org' => 'spm-kecap-bango'],
            ['name' => 'Pengurus SPM Minuman Segar', 'email' => 'pengurus@spm-minuman-segar.fsbmm.test', 'org' => 'spm-minuman-segar'],
            ['name' => 'Pengurus SPM Roti Nusantara', 'email' => 'pengurus@spm-roti-nusantara.fsbmm.test', 'org' => 'spm-roti-nusantara'],
        ];

        foreach ($accounts as $account) {
            $org = Organization::where('slug', $account['org'])->firstOrFail();

            User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => $password,
                    'role' => User::ROLE_SBA_ADMIN,
                    'organization_id' => $org->id,
                ]
            );
        }
    }
}
