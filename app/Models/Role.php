<?php

namespace Omnify\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Omnify\Core\Database\Factories\RoleFactory;
use Omnify\Core\Models\Base\RoleBaseModel;

/**
 * Role Model
 *
 * This file is generated once and can be customized.
 * Add your custom methods and logic here.
 */
class Role extends RoleBaseModel
{
    use HasFactory;

    protected static function newFactory(): RoleFactory
    {
        return RoleFactory::new();
    }

    /**
     * Check if this role has a specific permission by slug.
     */
    public function hasPermission(string $slug): bool
    {
        return $this->permissions()->where('slug', $slug)->exists();
    }

    /**
     * Check if this role has any of the given permissions.
     *
     * @param  array<string>  $slugs
     */
    public function hasAnyPermission(array $slugs): bool
    {
        return $this->permissions()->whereIn('slug', $slugs)->exists();
    }

    /**
     * Check if this role has all of the given permissions.
     *
     * @param  array<string>  $slugs
     */
    public function hasAllPermissions(array $slugs): bool
    {
        $count = $this->permissions()->whereIn('slug', $slugs)->count();

        return $count === count($slugs);
    }
}
