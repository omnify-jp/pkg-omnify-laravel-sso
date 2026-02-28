<?php

namespace Omnify\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Omnify\Core\Models\Role;

/**
 * Role Factory
 *
 * SAFE TO EDIT - This file is never overwritten by Omnify.
 *
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'console_organization_id' => fake()->words(3, true),
            'name' => fake()->sentence(3),
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->paragraphs(3, true),
            'level' => fake()->numberBetween(1, 1000),
        ];
    }
}
