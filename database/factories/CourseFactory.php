<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->randomElement([
            'Keselamatan Kerja di Pabrik',
            'Dasar-Dasar Perundingan Bersama',
            'Kepemimpinan Serikat Pekerja',
            'Kesehatan dan Keselamatan Kerja',
            'Hukum Ketenagakerjaan untuk Pengurus',
        ]);

        return [
            'title' => $title,
            // Unique slug suffix: titles may repeat across factory rows, slugs must not.
            'slug' => Str::slug($title) . '-' . fake()->unique()->numberBetween(100, 99999),
            'description' => fake()->paragraph(),
            'level' => fake()->randomElement(Course::LEVELS),
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
