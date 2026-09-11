<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->randomElement([
            'Rapat Kerja Nasional FSBMM 2026',
            'Pelatihan K3 bagi Perwakilan SBA',
            'Hasil Perundingan Upah Minimum Sektoral',
            'Peringatan May Day: Suara Pekerja Makanan & Minuman',
            'Beasiswa Pendidikan Anak Anggota Dibuka',
            'Solidaritas Antar-SBA dalam Aksi Nasional',
            'Tips Menghitung Upah Lembur dengan Benar',
            'Kabar Terbaru dari Kongres Federasi',
        ]);

        $paragraphs = [
            'Serikat pekerja tingkat perusahaan yang bernaung di bawah FSBMM terus memperkuat kapasitas organisasi melalui pelatihan rutin dan dialog dengan pengurus.',
            'Perwakilan SBA dari berbagai daerah mengikuti agenda ini secara langsung dan menyampaikan aspirasi anggotanya untuk dibahas bersama pengurus federasi.',
            'Federasi berkomitmen menjaga keberlanjutan program dengan melibatkan seluruh pemangku kepentingan secara transparan dan partisipatif.',
            'Kegiatan ini merupakan bagian dari program penguatan organisasi yang dijalankan sepanjang tahun anggaran berjalan.',
        ];

        return [
            'title' => $title,
            // Unique slug suffix: titles may repeat across factory rows, slugs must not.
            'slug' => Str::slug($title) . '-' . fake()->unique()->numberBetween(100, 99999),
            'excerpt' => fake()->randomElement($paragraphs),
            'body' => '<p>' . implode('</p><p>', $paragraphs) . '</p>',
            'cover_image_path' => null,
            'category_id' => Category::factory(),
            'author_id' => User::factory(),
            'published_at' => now(),
            'is_featured' => false,
        ];
    }

    /** Draft = never published (published_at null). */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => null,
        ]);
    }
}
