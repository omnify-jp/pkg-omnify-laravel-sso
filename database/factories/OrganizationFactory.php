<?php

namespace Omnify\Core\Database\Factories;

use Omnify\Core\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;


/**
 * Organization Factory
 *
 * SAFE TO EDIT - This file is never overwritten by Omnify.
 *
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'console_organization_id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => fake()->sentence(3),
            'slug' => fake()->unique()->slug(2),
            'is_active' => fake()->boolean(),
        ];
    }

    public function standalone(): static
    {
        return $this->state([
            'is_standalone' => true,
        ]);
    }

    public function console(): static
    {
        return $this->state([
            'is_standalone' => false,
        ]);
    }
}
