<?php

namespace Omnify\SsoClient\Database\Factories;

use Omnify\SsoClient\Models\TeamPermission;
use Illuminate\Database\Eloquent\Factories\Factory;

use Omnify\SsoClient\Models\Permission;

/**
 * TeamPermission Factory
 *
 * SAFE TO EDIT - This file is never overwritten by Omnify.
 *
 * @extends Factory<TeamPermission>
 */
class TeamPermissionFactory extends Factory
{
    protected $model = TeamPermission::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'console_organization_id' => (string) \Illuminate\Support\Str::uuid(),
            'console_team_id' => (string) \Illuminate\Support\Str::uuid(),
            'permission_id' => Permission::query()->inRandomOrder()->first()?->id ?? Permission::factory()->create()->id,
        ];
    }
}
