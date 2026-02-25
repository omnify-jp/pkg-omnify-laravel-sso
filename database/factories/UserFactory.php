<?php

namespace Omnify\SsoClient\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Omnify\SsoClient\Models\Role;
use Omnify\SsoClient\Models\User;

/**
 * User Factory
 *
 * SAFE TO EDIT - This file is never overwritten by Omnify.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

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
            'remember_token' => \Illuminate\Support\Str::random(32),
            'console_user_id' => (string) \Illuminate\Support\Str::uuid(),
            'console_organization_id' => (string) \Illuminate\Support\Str::uuid(),
            'console_access_token' => fake()->paragraphs(3, true),
            'console_refresh_token' => fake()->paragraphs(3, true),
            'console_token_expires_at' => fake()->dateTime(),
        ];
    }

    public function withPassword(string $password): static
    {
        return $this->state([
            'password' => Hash::make($password),
        ]);
    }

    public function withoutConsoleUserId(): static
    {
        return $this->state([
            'console_user_id' => null,
            'console_access_token' => null,
            'console_refresh_token' => null,
            'console_token_expires_at' => null,
        ]);
    }

    public function withoutTokens(): static
    {
        return $this->state([
            'console_access_token' => null,
            'console_refresh_token' => null,
            'console_token_expires_at' => null,
        ]);
    }

    public function withExpiredTokens(): static
    {
        return $this->state([
            'console_access_token' => 'expired-token',
            'console_refresh_token' => 'expired-refresh',
            'console_token_expires_at' => now()->subHour(),
        ]);
    }

    /** No-op for SSO users — verification is handled by Console. */
    public function unverified(): static
    {
        return $this->state([]);
    }

    public function withRole(string $slug): static
    {
        return $this->afterCreating(function (User $user) use ($slug) {
            $role = Role::where('slug', $slug)->first();
            if ($role) {
                $user->assignRole($role);
            }
        });
    }
}
