<?php

namespace Omnify\SsoClient\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Omnify\SsoClient\Database\Factories\TeamFactory;
use Omnify\SsoClient\Models\Base\TeamBaseModel;

/**
 * Team Model
 *
 * This file is generated once and can be customized.
 * Add your custom methods and logic here.
 */
class Team extends TeamBaseModel
{
    use HasFactory;

    protected static function newFactory(): TeamFactory
    {
        return TeamFactory::new();
    }

    /**
     * Get the organization this team belongs to.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'console_organization_id', 'console_organization_id');
    }

    /**
     * Get permissions for this team via TeamPermission.
     */
    public function permissions(): HasManyThrough
    {
        return $this->hasManyThrough(
            Permission::class,
            TeamPermission::class,
            'console_team_id',
            'id',
            'console_team_id',
            'permission_id'
        );
    }

    /**
     * Get all teams for an organization.
     */
    public static function getByOrgId(string $organizationId): Collection
    {
        return static::where('console_organization_id', $organizationId)->get();
    }

    /**
     * Find a team by its console team ID.
     */
    public static function findByConsoleId(string $consoleId): ?static
    {
        return static::where('console_team_id', $consoleId)->first();
    }

    /**
     * Check if this team has a specific permission.
     */
    public function hasPermission(string $slug): bool
    {
        return $this->permissions()->where('slug', $slug)->exists();
    }

    /**
     * Check if this team has any of the given permissions.
     *
     * @param  array<string>  $slugs
     */
    public function hasAnyPermission(array $slugs): bool
    {
        return $this->permissions()->whereIn('slug', $slugs)->exists();
    }

    /**
     * Check if this team has all of the given permissions.
     *
     * @param  array<string>  $slugs
     */
    public function hasAllPermissions(array $slugs): bool
    {
        $count = $this->permissions()->whereIn('slug', $slugs)->count();

        return $count === count($slugs);
    }
}
