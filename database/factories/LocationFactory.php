<?php

namespace Omnify\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Omnify\Core\Models\Location;

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
            'console_branch_id' => fn () => \Omnify\Core\Models\Branch::factory()->create()->console_branch_id,
            'console_organization_id' => fn (array $attributes) => isset($attributes['console_branch_id'])
                ? \Omnify\Core\Models\Branch::where('console_branch_id', $attributes['console_branch_id'])->value('console_organization_id')
                : \Omnify\Core\Models\Organization::factory()->create()->console_organization_id,
            'code' => fake()->unique()->regexify('[A-Z0-9]{8}'),
            'name' => fake()->sentence(3),
            'type' => fake()->randomElement(['office', 'warehouse', 'factory', 'store', 'clinic', 'restaurant', 'other']),
            'is_active' => fake()->boolean(),
            'address' => fake()->address(),
            'city' => fake()->city(),
            'state_province' => fake()->state(),
            'postal_code' => fake()->postcode(),
            'country_code' => fake()->randomElement(['JP', 'US', 'VN']),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'timezone' => fake()->randomElement(\DateTimeZone::listIdentifiers()),
            'capacity' => fake()->numberBetween(1, 1000),
            'sort_order' => fake()->numberBetween(0, 100),
            'description' => fake()->sentence(),
            'settings' => [],
            'metadata' => [],
        ];
    }

    public function forBranch(string $branchId, string $organizationId): static
    {
        return $this->state([
            'console_branch_id' => $branchId,
            'console_organization_id' => $organizationId,
        ]);
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
