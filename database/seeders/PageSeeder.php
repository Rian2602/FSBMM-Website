<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            'home' => [
                'title' => 'Beranda',
                'meta_title' => 'FSBMM — Federasi Serikat Buruh Makanan dan Minuman',
                'meta_description' => 'Situs resmi Federasi Serikat Buruh Makanan dan Minuman: berita, direktori SBA, e-resource, dan e-learning untuk anggota.',
                'blocks' => [
                    [
                        'type' => 'hero',
                        'payload' => [
                            'eyebrow' => 'Serikat Buruh',
                            'title' => 'Federasi Serikat Buruh Makanan dan Minuman',
                            'subtitle' => 'Memperjuangkan kesejahteraan dan hak-hak pekerja industri makanan dan minuman di seluruh Indonesia.',
                            'cta_label' => 'Tentang Kami',
                            'cta_url' => '/tentang',
                        ],
                    ],
                    [
                        'type' => 'stats',
                        'payload' => [
                            // ANGKA DEMO — ganti dengan angka riil setelah SP2/SP3.
                            'items' => [
                                ['label' => 'SBA Terdaftar', 'value' => '25'],
                                ['label' => 'Anggota', 'value' => '10.000+'],
                                ['label' => 'Provinsi', 'value' => '12'],
                                ['label' => 'Tahun Berdiri', 'value' => '1998'],
                            ],
                        ],
                    ],
                    [
                        'type' => 'rich_text',
                        'payload' => [
                            'content' => '<p>FSBMM adalah federasi serikat pekerja yang menaungi puluhan serikat pekerja tingkat perusahaan (SBA) di industri makanan dan minuman. Kami bekerja melalui dialog industrial, advokasi kebijakan, dan penguatan kapasitas organisasi anggota.</p>',
                        ],
                    ],
                    [
                        'type' => 'cta',
                        'payload' => [
                            'title' => 'Butuh dokumen atau informasi?',
                            'body' => 'Kunjungi pusat e-resource kami untuk mengunduh AD/ART, template, dan materi pendidikan.',
                            'label' => 'Kunjungi E-Resource',
                            'url' => '/e-resource',
                        ],
                    ],
                ],
            ],
            'tentang' => [
                'title' => 'Tentang Kami',
                'meta_description' => 'Sejarah, visi, dan struktur Federasi Serikat Buruh Makanan dan Minuman.',
                'blocks' => [
                    [
                        'type' => 'rich_text',
                        'payload' => [
                            'content' => '<h2>Sejarah</h2><p>Federasi ini lahir dari kebutuhan pekerja industri makanan dan minuman untuk berserikat dalam satu wadah yang kuat di tingkat nasional.</p><h2>Visi</h2><p>Terwujudnya pekerja makanan dan minuman yang sejahtera, berdaya, dan terlindungi hak-haknya.</p><h2>Misi</h2><ul><li>Memperkuat serikat di tingkat perusahaan (SBA).</li><li>Mendorong upah layak dan kondisi kerja yang aman.</li><li>Menyediakan pendidikan dan pelatihan bagi anggota.</li></ul>',
                        ],
                    ],
                    [
                        'type' => 'quote',
                        'payload' => [
                            'quote' => 'Bersatu kita teguh — kesejahteraan pekerja adalah fondasi industri yang bermartabat.',
                            'author' => 'Dewan Pimpinan FSBMM',
                        ],
                    ],
                ],
            ],
            'kontak' => [
                'title' => 'Kontak',
                'meta_description' => 'Hubungi sekretariat Federasi Serikat Buruh Makanan dan Minuman.',
                'blocks' => [
                    [
                        'type' => 'rich_text',
                        'payload' => [
                            'content' => '<h2>Sekretariat FSBMM</h2><p>Alamat sekretariat menyusul. Untuk keperluan kerja sama dan informasi, hubungi kami melalui kanal resmi federasi.</p>',
                        ],
                    ],
                    [
                        'type' => 'cta',
                        'payload' => [
                            'title' => 'SBA belum tergabung?',
                            'body' => 'Pelajari cara bergabung dan manfaat keanggotaan federasi.',
                            'label' => 'Hubungi Kami',
                            'url' => '/kontak',
                        ],
                    ],
                ],
            ],
        ];

        foreach ($pages as $slug => $page) {
            $row = Page::firstOrCreate(
                ['slug' => $slug],
                [
                    'title' => $page['title'],
                    'meta_title' => $page['meta_title'] ?? null,
                    'meta_description' => $page['meta_description'] ?? null,
                    'is_published' => true,
                ]
            );

            if ($row->wasRecentlyCreated) {
                $blocks = array_map(
                    fn (array $block, int $i) => [...$block, 'sort_order' => $i],
                    $page['blocks'],
                    array_keys($page['blocks'])
                );
                $row->blocks()->createMany($blocks);
            }
        }
    }
}
