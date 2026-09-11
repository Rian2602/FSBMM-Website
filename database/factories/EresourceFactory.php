<?php

namespace Database\Factories;

use App\Models\Eresource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Eresource>
 */
class EresourceFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->randomElement([
            'AD/ART Federasi',
            'Leaflet Keselamatan Kerja',
            'Template Surat Kuasa',
            'Modul Organisasi Dasar',
            'Pedoman Pencatatan Iuran',
            'Buku Saku K3 Pabrik',
        ]);

        return [
            'title' => $title,
            // Unique slug suffix so several factory rows never collide.
            'slug' => Str::slug($title) . '-' . fake()->unique()->numberBetween(100, 99999),
            'description' => fake()->paragraph(),
            // Dummy relative path; tests that need a real file put one on the
            // disk themselves (Storage::fake('public')).
            'file_path' => 'eresources/' . Str::slug($title) . '.pdf',
            'is_published' => true,
            'downloads_count' => 0,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
        ]);
    }
}
