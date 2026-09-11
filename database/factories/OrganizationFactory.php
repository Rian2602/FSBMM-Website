<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        $name = 'SPM ' . fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'company' => fake()->company() . ' (pabrik ' . fake()->city() . ')',
            'logo_path' => null,
            'description' => fake()->paragraph(),
            'website' => 'https://' . fake()->domainName(),
            'location' => fake()->city() . ', ' . fake()->state(),
            'founded_year' => fake()->numberBetween(1970, 2020),
            'member_count' => fake()->numberBetween(50, 2000),
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
