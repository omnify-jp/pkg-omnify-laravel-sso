<?php

namespace Omnify\SsoClient\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Omnify\SsoClient\Models\User;

/**
 * テスト用Userファクトリー
 * Updated for UUID-based schema (no password, no remember_token)
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'console_user_id' => (string) Str::uuid(),
            'console_access_token' => 'encrypted-token',
            'console_refresh_token' => 'encrypted-refresh',
            'console_token_expires_at' => now()->addHour(),
        ];
    }

    /**
     * コンソールユーザーIDなしのユーザー
     */
    public function withoutConsoleUserId(): static
    {
        return $this->state(fn (array $attributes) => [
            'console_user_id' => null,
        ]);
    }

    /**
     * 特定のコンソールユーザーID (UUID)
     */
    public function withConsoleUserId(string $id): static
    {
        return $this->state(fn (array $attributes) => [
            'console_user_id' => $id,
        ]);
    }

    /**
     * トークンなしのユーザー
     */
    public function withoutTokens(): static
    {
        return $this->state(fn (array $attributes) => [
            'console_access_token' => null,
            'console_refresh_token' => null,
            'console_token_expires_at' => null,
        ]);
    }
}
