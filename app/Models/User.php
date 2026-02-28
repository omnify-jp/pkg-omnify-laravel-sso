<?php

namespace Omnify\Core\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Omnify\Core\Database\Factories\UserFactory;
use Omnify\Core\Models\Base\UserBaseModel;
use Omnify\Core\Models\Traits\HasConsoleSso;
use Omnify\Core\Models\Traits\HasStandaloneScope;
use Omnify\Core\Models\Traits\HasTeamPermissions;

/**
 * User Model
 *
 * This file is generated once and can be customized.
 * Add your custom methods and logic here.
 */
class User extends UserBaseModel implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable;
    use Authorizable;
    use HasApiTokens;
    use HasConsoleSso;
    use HasFactory;
    use HasStandaloneScope;
    use HasTeamPermissions;
    use Notifiable;

    protected static function newFactory(): Factory
    {
        return UserFactory::new();
    }

    /**
     * Assign a role to this user with optional org/branch scope.
     * Accepts a Role model or a role slug string.
     * Idempotent: assigning the same role+scope twice is a no-op.
     */
    public function assignRole(Role|string $role, ?string $organizationId = null, ?string $branchId = null): void
    {
        if (is_string($role)) {
            $role = Role::where('slug', $role)->first()
                ?? throw new \InvalidArgumentException("Role with slug [{$role}] not found.");
        }

        $query = $this->roles()->where('roles.id', $role->id);

        if ($organizationId === null) {
            $query = $query->wherePivotNull('console_organization_id');
        } else {
            $query = $query->wherePivot('console_organization_id', $organizationId);
        }

        if ($branchId === null) {
            $query = $query->wherePivotNull('console_branch_id');
        } else {
            $query = $query->wherePivot('console_branch_id', $branchId);
        }

        if (! $query->exists()) {
            $this->roles()->attach($role->id, [
                'console_organization_id' => $organizationId,
                'console_branch_id' => $branchId,
            ]);
        }
    }

    /**
     * Remove a specific scoped role assignment.
     */
    public function removeRole(Role $role, ?string $organizationId = null, ?string $branchId = null): void
    {
        $query = $this->roles();

        if ($organizationId === null) {
            $query = $query->wherePivotNull('console_organization_id');
        } else {
            $query = $query->wherePivot('console_organization_id', $organizationId);
        }

        if ($branchId === null) {
            $query = $query->wherePivotNull('console_branch_id');
        } else {
            $query = $query->wherePivot('console_branch_id', $branchId);
        }

        $query->detach($role->id);
    }

    /**
     * Get all role assignments with pivot data.
     */
    public function getRoleAssignments(): Collection
    {
        return $this->roles()->withPivot('console_organization_id', 'console_branch_id')->get();
    }

    /**
     * Get roles that apply in the given context.
     *
     * Context resolution:
     * - No context (null, null)    → global roles only
     * - Org context (org, null)    → global + org-wide roles
     * - Branch context (org, br)   → global + org-wide + branch-specific roles
     */
    public function getRolesForContext(?string $organizationId = null, ?string $branchId = null): Collection
    {
        $query = $this->roles()->withPivot('console_organization_id', 'console_branch_id');

        if ($organizationId === null) {
            return $query
                ->wherePivotNull('console_organization_id')
                ->wherePivotNull('console_branch_id')
                ->get();
        }

        if ($branchId === null) {
            return $query->where(function ($q) use ($organizationId) {
                $q->whereRaw('(role_user_pivot.console_organization_id IS NULL AND role_user_pivot.console_branch_id IS NULL)')
                    ->orWhereRaw(
                        '(role_user_pivot.console_organization_id = ? AND role_user_pivot.console_branch_id IS NULL)',
                        [$organizationId]
                    );
            })->get();
        }

        return $query->where(function ($q) use ($organizationId, $branchId) {
            $q->whereRaw('(role_user_pivot.console_organization_id IS NULL AND role_user_pivot.console_branch_id IS NULL)')
                ->orWhereRaw(
                    '(role_user_pivot.console_organization_id = ? AND role_user_pivot.console_branch_id IS NULL)',
                    [$organizationId]
                )
                ->orWhereRaw(
                    '(role_user_pivot.console_organization_id = ? AND role_user_pivot.console_branch_id = ?)',
                    [$organizationId, $branchId]
                );
        })->get();
    }

    /**
     * Check if user has a role by slug in the given context.
     */
    public function hasRoleInContext(string $slug, ?string $organizationId = null, ?string $branchId = null): bool
    {
        return $this->getRolesForContext($organizationId, $branchId)
            ->contains('slug', $slug);
    }

    /**
     * Get the highest role level in the given context.
     */
    public function getHighestRoleLevelInContext(?string $organizationId = null, ?string $branchId = null): int
    {
        return (int) $this->getRolesForContext($organizationId, $branchId)
            ->max('level') ?? 0;
    }

    /**
     * Replace all roles in a specific scope, leaving other scopes intact.
     */
    public function syncRolesInScope(array $roles, ?string $organizationId = null, ?string $branchId = null): void
    {
        $query = $this->roles();

        if ($organizationId === null) {
            $query = $query->wherePivotNull('console_organization_id');
        } else {
            $query = $query->wherePivot('console_organization_id', $organizationId);
        }

        if ($branchId === null) {
            $query = $query->wherePivotNull('console_branch_id');
        } else {
            $query = $query->wherePivot('console_branch_id', $branchId);
        }

        $query->detach();

        foreach ($roles as $role) {
            $this->assignRole($role, $organizationId, $branchId);
        }
    }
}
