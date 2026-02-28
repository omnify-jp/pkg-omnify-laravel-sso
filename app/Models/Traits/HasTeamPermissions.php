<?php

declare(strict_types=1);

namespace Omnify\Core\Models\Traits;

use Illuminate\Support\Facades\Cache;
use Omnify\Core\Models\Role;
use Omnify\Core\Models\TeamPermission;
use Omnify\Core\Services\OrganizationAccessService;

/**
 * Trait for checking permissions from both Role and Teams.
 *
 * Permission Resolution Flow (Branch-Level Permissions - Option B):
 * 1. Get scoped role permissions (global + org-wide + branch-specific)
 * 2. Get team permissions (org-scoped, unchanged)
 * 3. Aggregate all permissions
 *
 * Requires:
 * - HasConsoleSso trait
 * - Scoped role methods from User model (getRolesForContext, etc.)
 *
 * @see https://csrc.nist.gov/Projects/Role-Based-Access-Control NIST RBAC Standard
 * @see https://workos.com/blog/how-to-design-multi-tenant-rbac-saas WorkOS Multi-Tenant RBAC
 */
trait HasTeamPermissions
{
    /**
     * Get all permissions for user in a specific context.
     *
     * Permission sources:
     * 1. Role permissions (from scoped role assignments)
     * 2. Team permissions (from Console teams in organization)
     *
     * @param  string|null  $organizationId  Organization ID (console_organization_id). Falls back to session.
     * @param  string|null  $branchId  Branch ID (console_branch_id). Falls back to session. NEW!
     * @return array<string> List of permission slugs
     *
     * @example
     * // Get all permissions for current context (from session)
     * $permissions = $user->getAllPermissions();
     *
     * // Get permissions for specific org (org-wide context)
     * $permissions = $user->getAllPermissions($organizationId);
     *
     * // Get permissions for specific branch context
     * $permissions = $user->getAllPermissions($organizationId, $branchId);
     */
    public function getAllPermissions(?string $organizationId = null, ?string $branchId = null): array
    {
        // Fall back to session/request values
        $organizationId = $organizationId ?? session('current_organization_id') ?? request()->attributes->get('organizationId');
        $branchId = $branchId ?? session('current_branch_id') ?? request()->attributes->get('branchId');

        // Get scoped role permissions (includes global, org-wide, and branch-specific)
        $rolePermissions = $this->getRolePermissions($organizationId, $branchId);

        // If no org context, return only role permissions
        if (! $organizationId) {
            return $rolePermissions;
        }

        // Get team permissions (org-scoped only, unchanged from before)
        $teamPermissions = $this->getTeamPermissions($organizationId);

        return array_unique([...$rolePermissions, ...$teamPermissions]);
    }

    /**
     * Get role permissions for user in a specific context.
     *
     * Aggregates permissions from all applicable roles:
     * - Global roles (org=null, branch=null)
     * - Organization-wide roles (org=X, branch=null)
     * - Branch-specific roles (org=X, branch=Y)
     *
     * @param  string|null  $organizationId  Organization ID
     * @param  string|null  $branchId  Branch ID
     * @return array<string> List of permission slugs
     *
     * @example
     * // Get permissions from global roles only
     * $permissions = $user->getRolePermissions();
     *
     * // Get permissions including org-wide roles
     * $permissions = $user->getRolePermissions($organizationId);
     *
     * // Get permissions including branch-specific roles
     * $permissions = $user->getRolePermissions($organizationId, $branchId);
     */
    public function getRolePermissions(?string $organizationId = null, ?string $branchId = null): array
    {
        // Fall back to session/request values
        $organizationId = $organizationId ?? session('current_organization_id') ?? request()->attributes->get('organizationId');
        $branchId = $branchId ?? session('current_branch_id') ?? request()->attributes->get('branchId');

        // Get applicable roles for this context using scoped role method
        // This method is defined in User model
        $roles = $this->getRolesForContext($organizationId, $branchId);

        if ($roles->isEmpty()) {
            return [];
        }

        // Aggregate permissions from all applicable roles
        $roleSlugs = $roles->pluck('slug')->toArray();

        return Role::whereIn('slug', $roleSlugs)
            ->with('permissions')
            ->get()
            ->flatMap(fn ($role) => $role->permissions->pluck('slug'))
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Get team permissions for user in organization.
     *
     * Note: Team permissions are org-scoped only (not branch-scoped).
     * Branch-level access control is handled via scoped roles.
     *
     * @param  string|int  $organizationId  Organization ID (UUID string or int for legacy)
     * @return array<string> List of permission slugs
     */
    public function getTeamPermissions(string|int $organizationId): array
    {
        $teams = $this->getConsoleTeams($organizationId);

        if (empty($teams)) {
            return [];
        }

        $teamIds = collect($teams)->pluck('id')->toArray();

        if (empty($teamIds)) {
            return [];
        }

        return TeamPermission::where('console_organization_id', $organizationId)
            ->whereIn('console_team_id', $teamIds)
            ->with('permission')
            ->get()
            ->pluck('permission.slug')
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Check if user has a specific permission in context.
     *
     * @param  string  $permission  Permission slug to check
     * @param  string|null  $organizationId  Organization context
     * @param  string|null  $branchId  Branch context (NEW!)
     *
     * @example
     * // Check with current context (from session)
     * $user->hasPermission('orders.create');
     *
     * // Check with explicit context
     * $user->hasPermission('orders.create', $organizationId, $branchId);
     */
    public function hasPermission(string $permission, ?string $organizationId = null, ?string $branchId = null): bool
    {
        return in_array($permission, $this->getAllPermissions($organizationId, $branchId), true);
    }

    /**
     * Check if user has any of the given permissions.
     *
     * @param  array<string>  $permissions  List of permission slugs
     * @param  string|null  $organizationId  Organization context
     * @param  string|null  $branchId  Branch context (NEW!)
     * @return bool True if user has at least one permission
     *
     * @example
     * $user->hasAnyPermission(['orders.create', 'orders.update'], $organizationId, $branchId);
     */
    public function hasAnyPermission(array $permissions, ?string $organizationId = null, ?string $branchId = null): bool
    {
        $userPermissions = $this->getAllPermissions($organizationId, $branchId);

        foreach ($permissions as $permission) {
            if (in_array($permission, $userPermissions, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has all of the given permissions.
     *
     * @param  array<string>  $permissions  List of permission slugs
     * @param  string|null  $organizationId  Organization context
     * @param  string|null  $branchId  Branch context (NEW!)
     * @return bool True if user has all permissions
     *
     * @example
     * $user->hasAllPermissions(['orders.create', 'orders.view'], $organizationId, $branchId);
     */
    public function hasAllPermissions(array $permissions, ?string $organizationId = null, ?string $branchId = null): bool
    {
        $userPermissions = $this->getAllPermissions($organizationId, $branchId);

        foreach ($permissions as $permission) {
            if (! in_array($permission, $userPermissions, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get user's teams from Console (cached).
     *
     * @param  string|int  $organizationId  Organization ID (UUID string or int for legacy)
     * @return array<array{id: int, name: string, path: string|null, parent_id: int|null, is_leader: bool}>
     */
    public function getConsoleTeams(string|int $organizationId): array
    {
        $cacheKey = "sso:user_teams:{$this->id}:{$organizationId}";

        return Cache::remember(
            $cacheKey,
            config('sso-client.cache.user_teams_ttl', 300),
            function () use ($organizationId) {
                $organizationId = $this->getOrgSlugById($organizationId);

                if (! $organizationId) {
                    return [];
                }

                return app(OrganizationAccessService::class)->getUserTeams($this, $organizationId);
            }
        );
    }

    /**
     * Clear permission cache for user.
     *
     * @param  string|null  $organizationId  Organization ID
     * @param  string|null  $branchId  Branch ID (for future cache granularity)
     */
    public function clearPermissionCache(?string $organizationId = null, ?string $branchId = null): void
    {
        if ($organizationId) {
            Cache::forget("sso:user_teams:{$this->id}:{$organizationId}");
        }

        // Note: Role permissions are cached by role slug, not user context.
        // If needed, clear all role caches or implement user-specific role cache.
    }

    /**
     * Get organization slug by ID.
     * Override this method if you have a different way to resolve org slug.
     *
     * @param  string|int  $organizationId  Organization ID (UUID string or int for legacy)
     */
    protected function getOrgSlugById(string|int $organizationId): ?string
    {
        // Try to get from session
        $organizationId = session('current_organization_id');

        if ($organizationId) {
            return $organizationId;
        }

        // Try to get from request
        return request()->attributes->get('organizationId');
    }
}
