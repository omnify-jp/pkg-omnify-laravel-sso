<?php

namespace Omnify\SsoClient\Database\Factories;

use Omnify\SsoClient\Models\RefreshToken;
use Illuminate\Database\Eloquent\Factories\Factory;

use Omnify\SsoClient\Models\User;

/**
 * RefreshToken Factory
 *
 * SAFE TO EDIT - This file is never overwritten by Omnify.
 *
 * @extends Factory<RefreshToken>
 */
class RefreshTokenFactory extends Factory
{
    protected $model = RefreshToken::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'token_hash' => \Illuminate\Support\Str::random(32),
            'expires_at' => fake()->dateTime(),
            'revoked_at' => fake()->dateTime(),
            'ip_address' => fake()->address(),
            'user_agent' => fake()->sentence(),
            'user_id' => User::query()->inRandomOrder()->first()?->id ?? User::factory()->create()->id,
        ];
    }
}
