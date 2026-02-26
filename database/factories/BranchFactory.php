<?php

namespace Omnify\SsoClient\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Omnify\SsoClient\Models\Branch;

/**
 * Branch Factory
 *
 * SAFE TO EDIT - This file is never overwritten by Omnify.
 *
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'console_branch_id' => (string) \Illuminate\Support\Str::uuid(),
            'console_organization_id' => (string) \Illuminate\Support\Str::uuid(),
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->sentence(3),
            'is_headquarters' => fake()->boolean(),
            'is_active' => fake()->boolean(),
            'timezone' => fake()->randomElement(\DateTimeZone::listIdentifiers()),
        ];
    }

    public function forOrganization(string $organizationId): static
    {
        return $this->state([
            'console_organization_id' => $organizationId,
        ]);
    }

    public function headquarters(): static
    {
        return $this->state([
            'slug' => 'HQ',
            'name' => 'Headquarters',
            'is_headquarters' => true,
            'is_active' => true,
        ]);
    }
}
