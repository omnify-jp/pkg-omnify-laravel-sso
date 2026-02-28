<?php

namespace Omnify\Core\Database\Factories;

use Omnify\Core\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;


/**
 * Admin Factory
 *
 * SAFE TO EDIT - This file is never overwritten by Omnify.
 *
 * @extends Factory<Admin>
 */
class AdminFactory extends Factory
{
    protected $model = Admin::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'email' => fake()->unique()->safeEmail(),
            'password' => fake()->sentence(),
            'is_active' => fake()->boolean(),
            'email_verified_at' => fake()->dateTime(),
            'remember_token' => \Illuminate\Support\Str::random(32),
        ];
    }
}
