<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $organizations = [
            [
                'name' => 'SPM Kecap Bango',
                'company' => 'PT Bango Food (pabrik Karawang)',
                'location' => 'Karawang, Jawa Barat',
                'founded_year' => 2008,
                'member_count' => 429,
                'description' => '<p>Serikat pekerja di lingkungan pabrik PT Bango Food yang aktif memperjuangkan upah layak, keselamatan kerja, dan dialog industrial yang sehat.</p>',
            ],
            [
                'name' => 'SPM Minuman Segar',
                'company' => 'PT Segar Makmur Beverages (pabrik Cikarang)',
                'location' => 'Cikarang, Jawa Barat',
                'founded_year' => 2012,
                'member_count' => 315,
                'description' => '<p>Organisasi pekerja pabrik minuman yang fokus pada perlindungan kesehatan kerja dan pelatihan keterampilan anggota.</p>',
            ],
            [
                'name' => 'SPM Roti Nusantara',
                'company' => 'PT Roti Nusantara Prima (pabrik Tangerang)',
                'location' => 'Tangerang, Banten',
                'founded_year' => 2015,
                'member_count' => 540,
                'description' => '<p>SBA di industri bakery yang mengutamakan kesejahteraan pekerja shift dan program beasiswa pendidikan anak anggota.</p>',
            ],
        ];

        foreach ($organizations as $org) {
            Organization::firstOrCreate(
                ['slug' => str($org['name'])->slug()],
                [...$org, 'is_published' => true, 'website' => null, 'logo_path' => null],
            );
        }
    }
}
