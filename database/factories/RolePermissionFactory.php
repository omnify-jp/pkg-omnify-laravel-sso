<?php

namespace Omnify\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Omnify\Core\Models\Permission;
use Omnify\Core\Models\Role;
use Omnify\Core\Models\RolePermission;

/**
 * RolePermission Factory
 *
 * SAFE TO EDIT - This file is never overwritten by Omnify.
 *
 * @extends Factory<RolePermission>
 */
class RolePermissionFactory extends Factory
{
    protected $model = RolePermission::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role_id' => Role::query()->inRandomOrder()->first()?->id ?? Role::factory()->create()->id,
            'permission_id' => Permission::query()->inRandomOrder()->first()?->id ?? Permission::factory()->create()->id,
        ];
    }
}
