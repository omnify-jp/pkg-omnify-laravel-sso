<?php

namespace Omnify\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Omnify\Core\Models\Admin;

/**
 * Admin Factory
 *
 * SAFE TO EDIT - This file is never overwritten by Omnify.
 *
 * Note: Do NOT use Hash::make() here — Admin model has 'password' => 'hashed' cast
 * which auto-hashes on assignment. Assigning a plain string is correct.
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
            'name' => fake('ja_JP')->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'is_active' => true,
            'email_verified_at' => now(),
            'remember_token' => Str::random(32),
        ];
    }

    /**
     * Set a specific plain-text password.
     * The model cast handles hashing automatically.
     */
    public function withPassword(string $password): static
    {
        return $this->state([
            'password' => $password,
        ]);
    }

    /**
     * Mark the admin as inactive.
     */
    public function inactive(): static
    {
        return $this->state([
            'is_active' => false,
        ]);
    }
}
