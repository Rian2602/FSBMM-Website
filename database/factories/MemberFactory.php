<?php

namespace Database\Factories;

use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nik' => (string) fake()->numberBetween(1000000000000000, 9999999999999999),
            'name' => fake()->randomElement(['Yoga Pratama', 'Siti Rahma', 'Budi Santoso', 'Dewi Lestari', 'Andi Wijaya']),
            'gender' => fake()->randomElement(['L', 'P']),
            'birthplace' => fake()->randomElement(['Jakarta', 'Bandung', 'Karawang', 'Cikarang']),
            'birthdate' => fake()->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d'),
            'address' => fake()->streetAddress(),
            'department' => fake()->randomElement(['Produksi', 'Quality Control', 'Logistik', 'Teknik']),
            'position' => fake()->randomElement(['Operator', 'Staff', 'Supervisor']),
            'basic_salary' => fake()->numberBetween(3000000, 10000000),
            'join_date' => fake()->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'education' => fake()->randomElement(['SMA', 'D3', 'S1']),
            'status' => Member::STATUS_ACTIVE,
        ];
    }
}
