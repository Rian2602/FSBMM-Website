<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->randomElement([
                'Rapat Bulanan', 'Aksi Solidaritas', 'Pelatihan Anggota', 'Sosialisasi Upah',
            ]),
            'event_date' => fake()->dateTimeBetween('now', '+1 month')->format('Y-m-d'),
            'description' => fake()->sentence(),
        ];
    }
}
