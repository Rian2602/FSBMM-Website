<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->randomElement([
            'Tentang Kami',
            'Sejarah Federasi',
            'Visi dan Misi',
            'Struktur Organisasi',
            'Kontak Sekretariat',
            'Program Kerja',
        ]);

        return [
            'title' => $title,
            // Unique suffix so factory rows never collide on pages.slug
            // (pool names repeat) or with seeded slugs (home/tentang/kontak).
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(100, 99999),
            'meta_title' => null,
            'meta_description' => null,
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
        ]);
    }
}
