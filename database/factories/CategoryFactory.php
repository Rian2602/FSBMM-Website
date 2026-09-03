<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->randomElement([
            'Kabar Federasi',
            'Edukasi Anggota',
            'Perjuangan Upah',
            'Keselamatan Kerja',
            'Solidaritas',
        ]);

        // Unique slug suffix so factory rows never collide on categories.slug
        // (pool names repeat once several rows are created) or with explicit
        // slugs like 'kabar-federasi' used by tests/seeders.
        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 99999),
        ];
    }
}
