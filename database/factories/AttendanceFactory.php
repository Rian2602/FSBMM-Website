<?php

namespace Database\Factories;

use App\Models\Attendance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'status' => fake()->randomElement(['hadir', 'izin', 'tidak_hadir']),
            'note' => fake()->optional()->sentence(),
        ];
    }
}
