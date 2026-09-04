<?php

namespace Database\Factories;

use App\Models\Due;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Due>
 */
class DueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'period' => now()->format('Y-m'),
            'amount' => fake()->numberBetween(20000, 150000),
            'paid_at' => now()->format('Y-m-d'),
        ];
    }
}
