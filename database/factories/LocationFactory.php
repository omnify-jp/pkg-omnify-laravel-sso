<?php

namespace Omnify\SsoClient\Database\Factories;

use Omnify\SsoClient\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;


/**
 * Location Factory
 *
 * SAFE TO EDIT - This file is never overwritten by Omnify.
 *
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'console_location_id' => (string) \Illuminate\Support\Str::uuid(),
            'console_branch_id' => (string) \Illuminate\Support\Str::uuid(),
            'console_organization_id' => (string) \Illuminate\Support\Str::uuid(),
            'code' => fake()->unique()->regexify('[A-Z0-9]{8}'),
            'name' => fake()->sentence(3),
            'type' => fake()->randomElement(['office', 'warehouse', 'factory', 'store', 'clinic', 'restaurant', 'other']),
            'is_active' => fake()->boolean(),
            'address' => fake()->paragraphs(3, true),
            'city' => fake()->city(),
            'state_province' => fake()->sentence(),
            'postal_code' => fake()->postcode(),
            'country_code' => fake()->country(),
            'latitude' => fake()->randomFloat(2, 1, 10000),
            'longitude' => fake()->randomFloat(2, 1, 10000),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'timezone' => fake()->words(3, true),
            'capacity' => fake()->numberBetween(1, 1000),
            'sort_order' => fake()->numberBetween(1, 100),
            'description' => fake()->paragraphs(3, true),
            'settings' => [],
            'metadata' => [],
        ];
    }
}
