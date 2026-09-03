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

        return [
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
