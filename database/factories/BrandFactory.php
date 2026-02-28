<?php

namespace Omnify\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Omnify\Core\Models\Brand;

/**
 * Brand Factory
 *
 * SAFE TO EDIT - This file is never overwritten by Omnify.
 *
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    protected $model = Brand::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'console_brand_id' => (string) \Illuminate\Support\Str::uuid(),
            'console_organization_id' => fn () => \Omnify\Core\Models\Organization::factory()->create()->console_organization_id,
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->company(),
            'description' => fake()->sentence(),
            'logo_url' => null,
            'cover_image_url' => null,
            'website' => fake()->url(),
            'is_active' => true,
            'settings' => [],
            'metadata' => [],
        ];
    }

    public function forOrganization(string $organizationId): static
    {
        return $this->state([
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
