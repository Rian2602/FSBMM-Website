<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        $kabar = Category::firstOrCreate(['slug' => 'kabar-federasi'], ['name' => 'Kabar Federasi']);
        $edukasi = Category::firstOrCreate(['slug' => 'edukasi-anggota'], ['name' => 'Edukasi Anggota']);

        $author = User::first() ?? User::factory()->create([
            'name' => 'Admin FSBMM',
            'email' => 'admin@fsbmm.test',
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $articles = [
            [
                'category' => $kabar,
                'title' => 'Rapat Kerja Nasional FSBMM 2026 Bahas Strategi Perjuangan Upah',
                'published_at' => now()->subDays(1)->setTime(9, 0),
                'is_featured' => true,
            ],
            [
                'category' => $edukasi,
                'title' => 'Memahami Upah Minimum Sektoral dan Cara Perhitungannya',
                'published_at' => now()->subDays(3)->setTime(10, 0),
                'is_featured' => false,
            ],
            [
                'category' => $kabar,
                'title' => 'Solidaritas Antar-SBA: Aksi Bersama Peringati May Day',
                'published_at' => now()->subDays(6)->setTime(8, 30),
                'is_featured' => true,
            ],
            [
                'category' => $edukasi,
                'title' => 'Pelatihan K3 bagi Perwakilan SBA Angkatan Kedua Dimulai',
                'published_at' => now()->subDays(10)->setTime(9, 30),
                'is_featured' => false,
            ],
            [
                'category' => $kabar,
                'title' => 'Beasiswa Pendidikan Anak Anggota Kembali Dibuka',
                'published_at' => now()->subDays(14)->setTime(11, 0),
                'is_featured' => false,
            ],
        ];

        $body = fn (string $lead) => '<p>'.$lead.'</p><p>Kegiatan ini merupakan bagian dari program penguatan organisasi yang dijalankan sepanjang tahun anggaran berjalan. Perwakilan SBA dari berbagai daerah mengikuti agenda secara langsung dan menyampaikan aspirasi anggotanya untuk dibahas bersama pengurus federasi.</p><p>Federasi berkomitmen menjaga keberlanjutan program dengan melibatkan seluruh pemangku kepentingan secara transparan dan partisipatif.</p>';

        foreach ($articles as $i => $article) {
            Article::firstOrCreate(
                ['slug' => str($article['title'])->slug()],
                [
                    'title' => $article['title'],
                    'category_id' => $article['category']->id,
                    'author_id' => $author->id,
                    'excerpt' => 'Rangkuman singkat: '.$article['title'].'. Simak selengkapnya di halaman berita federasi.',
                    'body' => $body('Berita ini disampaikan untuk seluruh anggota FSBMM dan serikat pekerja di bawah naungannya.'),
                    'published_at' => $article['published_at'],
                    'is_featured' => $article['is_featured'],
                ]
            );
        }
    }
}
