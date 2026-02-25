<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Omnify\SsoClient\Models\Organization;
use Omnify\SsoClient\Models\Permission;
use Omnify\SsoClient\Models\Role;

class RoleService
{
    /**
     * List roles with optional scope filter.
     *
     * @param  array{scope?: string, organization_id?: string, filter_organization_id?: string}  $filters
     */
    public function list(?string $organizationId = null, array $filters = []): Collection
    {
        $scope = $filters['scope'] ?? 'all';
        $filterOrganizationId = $filters['filter_organization_id'] ?? null;

        $query = Role::withCount('permissions');

        // Apply scope filter
        if ($scope === 'global') {
            $query->whereNull('console_organization_id');
        } elseif ($scope === 'org') {
            $query->where('console_organization_id', $filterOrganizationId ?: $organizationId);
        } else {
            // 'all' - include global + current org roles
            $query->where(function ($q) use ($organizationId, $filterOrganizationId) {
                $q->whereNull('console_organization_id');
                if ($filterOrganizationId) {
                    $q->orWhere('console_organization_id', $filterOrganizationId);
                } elseif ($organizationId) {
                    $q->orWhere('console_organization_id', $organizationId);
                }
            });
        }

        $roles = $query->orderBy('level', 'desc')->get();

        // Add organization info
        $roles->each(function ($role) {
            $role->organization = $this->getOrganizationInfo($role->console_organization_id);
        });

        return $roles;
    }

    /**
     * Find role by ID with org access check.
     */
    public function find(string $id, ?string $organizationId = null): ?Role
    {
        return Role::with('permissions')
            ->where('id', $id)
            ->where(function ($query) use ($organizationId) {
                $query->whereNull('console_organization_id')
                    ->orWhere('console_organization_id', $organizationId);
            })
            ->first();
    }

    /**
     * Create a new role.
     *
     * @param  array{slug: string, name: string, level: int, description?: string, scope?: string, console_organization_id?: string}  $data
     * @return array{success: bool, role?: Role, error?: string, message?: string}
     */
    public function create(array $data, ?string $headerOrgId = null): array
    {
        $scope = $data['scope'] ?? 'org';
        $organizationId = $scope === 'global' ? null : ($data['console_organization_id'] ?? $headerOrgId);

        // Check unique constraint within scope
        $existingRole = Role::where('console_organization_id', $organizationId)
            ->where(function ($query) use ($data) {
                $query->where('slug', $data['slug'])
                    ->orWhere('name', $data['name']);
            })
            ->first();

        if ($existingRole) {
            return [
                'success' => false,
                'error' => 'DUPLICATE_ROLE',
                'message' => $organizationId
                    ? 'A role with this name or slug already exists in this organization'
                    : 'A global role with this name or slug already exists',
            ];
        }

        $role = Role::create([
            'slug' => $data['slug'],
            'name' => $data['name'],
            'level' => $data['level'],
            'description' => $data['description'] ?? null,
            'console_organization_id' => $organizationId,
        ]);

        return [
            'success' => true,
            'role' => $role,
            'message' => 'Role created successfully',
        ];
    }

    /**
     * Update a role.
     *
     * @param  array{name?: string, level?: int, description?: string}  $data
     */
    public function update(Role $role, array $data): Role
    {
        // Slug cannot be changed
        unset($data['slug']);

        $role->update($data);

        return $role->fresh();
    }

    /**
     * Delete a role.
     *
     * @return array{success: bool, error?: string, message?: string}
     */
    public function delete(Role $role): array
    {
        // Check if it's a global system role
        $systemRoles = ['admin', 'manager', 'member'];
        if ($role->console_organization_id === null && in_array($role->slug, $systemRoles, true)) {
            return [
                'success' => false,
                'error' => 'CANNOT_DELETE_SYSTEM_ROLE',
                'message' => 'Global system roles cannot be deleted',
            ];
        }

        $role->delete();

        return ['success' => true];
    }

    /**
     * Get role's permissions.
     */
    public function getPermissions(Role $role): array
    {
        return [
            'role' => [
                'id' => $role->id,
                'slug' => $role->slug,
                'name' => $role->name,
            ],
            'permissions' => $role->permissions,
        ];
    }

    /**
     * Sync role's permissions.
     *
     * @param  array<string|int>  $permissionIds  Permission IDs or slugs
     * @return array{message: string, attached: int, detached: int}
     */
    public function syncPermissions(Role $role, array $permissionIds): array
    {
        // Handle both IDs (UUIDs) and slugs
        $resolvedIds = collect($permissionIds)->map(function ($item) {
            if (Str::isUuid($item)) {
                return $item;
            }

            $permission = Permission::where('slug', $item)->first();

            return $permission?->id;
        })->filter()->values()->toArray();

        // Get current permissions for diff
        $currentIds = $role->permissions()->pluck('permissions.id')->toArray();

        // Sync permissions
        $role->permissions()->sync($resolvedIds);

        // Calculate attached and detached
        $attached = count(array_diff($resolvedIds, $currentIds));
        $detached = count(array_diff($currentIds, $resolvedIds));

        return [
            'message' => 'Permissions synced successfully',
            'attached' => $attached,
            'detached' => $detached,
        ];
    }

    /**
     * Get organization info by console_organization_id.
     */
    private function getOrganizationInfo(?string $consoleOrgId): ?array
    {
        if (! $consoleOrgId) {
            return null;
        }

        $org = Organization::where('console_organization_id', $consoleOrgId)->first();

        return $org ? [
            'id' => $org->id,
            'console_organization_id' => $org->console_organization_id,
            'name' => $org->name,
            'slug' => $org->slug,
        ] : null;
    }
}
