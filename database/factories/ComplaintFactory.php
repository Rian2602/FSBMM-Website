<?php

namespace Database\Factories;

use App\Models\Complaint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Complaint>
 */
class ComplaintFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reporter_name' => fake()->randomElement(['Budi Santoso', 'Siti Rahma', 'Dewi Lestari']),
            'title' => fake()->randomElement([
                'Fasilitas kantor rusak', 'Masalah transportasi', 'Keluhan jadwal shift',
            ]),
            'description' => fake()->paragraph(),
            'status' => 'baru',
            'submitted_at' => now()->format('Y-m-d'),
        ];
    }
}
