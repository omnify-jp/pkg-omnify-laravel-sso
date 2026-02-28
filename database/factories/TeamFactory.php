<?php

namespace Omnify\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Omnify\Core\Models\Team;

/**
 * Team Factory
 *
 * SAFE TO EDIT - This file is never overwritten by Omnify.
 *
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'console_team_id' => (string) \Illuminate\Support\Str::uuid(),
            'console_organization_id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => fake()->sentence(3),
        ];
    }

    public function forOrganization(string $organizationId): static
    {
        return $this->state([
            'console_organization_id' => $organizationId,
        ]);
    }
}
