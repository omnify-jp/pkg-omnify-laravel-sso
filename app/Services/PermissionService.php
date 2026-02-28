<?php

declare(strict_types=1);

namespace Omnify\Core\Services;

use Illuminate\Support\Collection;
use Omnify\Core\Models\Permission;
use Omnify\Core\Models\Role;

class PermissionService
{
    /**
     * List all permissions with optional filters (includes roles_count).
     *
     * @param  array{search?: string, group?: string}  $filters
     */
    public function list(array $filters = []): Collection
    {
        $query = Permission::withCount('roles')->orderBy('group')->orderBy('name');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['group'])) {
            $query->where('group', $filters['group']);
        }

        return $query->get();
    }

    /**
     * Get all unique permission groups.
     *
     * @return array<string>
     */
    public function getGroups(): array
    {
        return Permission::query()
            ->whereNotNull('group')
            ->distinct()
            ->pluck('group')
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * Get permissions grouped by group name.
     *
     * @return array<string, Collection>
     */
    public function getGrouped(): array
    {
        $permissions = Permission::orderBy('group')->orderBy('name')->get();

        return $permissions->groupBy('group')->toArray();
    }

    /**
     * Find permission by ID.
     */
    public function find(string $id): ?Permission
    {
        return Permission::find($id);
    }

    /**
     * Find permission by slug.
     */
    public function findBySlug(string $slug): ?Permission
    {
        return Permission::where('slug', $slug)->first();
    }

    /**
     * Create a new permission.
     *
     * @param  array{slug: string, name: string, group?: string, description?: string}  $data
     * @return array{success: bool, permission?: Permission, error?: string, message?: string}
     */
    public function create(array $data): array
    {
        // Check unique slug
        if (Permission::where('slug', $data['slug'])->exists()) {
            return [
                'success' => false,
                'error' => 'DUPLICATE_PERMISSION',
                'message' => 'A permission with this slug already exists',
            ];
        }

        $permission = Permission::create([
            'slug' => $data['slug'],
            'name' => $data['name'],
            'group' => $data['group'] ?? null,
            'description' => $data['description'] ?? null,
        ]);

        return [
            'success' => true,
            'permission' => $permission,
            'message' => 'Permission created successfully',
        ];
    }

    /**
     * Update a permission.
     *
     * @param  array{name?: string, group?: string, description?: string}  $data
     */
    public function update(Permission $permission, array $data): Permission
    {
        // Slug cannot be changed
        unset($data['slug']);

        $permission->update($data);

        return $permission->fresh();
    }

    /**
     * Delete a permission.
     */
    public function delete(Permission $permission): bool
    {
        return $permission->delete();
    }

    /**
     * Get permission matrix (roles vs permissions).
     *
     * @return array{
     *     roles: Collection,
     *     permissions: Collection,
     *     groups: array,
     *     matrix: array<string, array<string, bool>>
     * }
     */
    public function getMatrix(?string $organizationId = null): array
    {
        $roles = Role::query()
            ->where(function ($query) use ($organizationId) {
                $query->whereNull('console_organization_id');
                if ($organizationId) {
                    $query->orWhere('console_organization_id', $organizationId);
                }
            })
            ->with('permissions')
            ->orderBy('level', 'desc')
            ->get();

        $permissions = Permission::orderBy('group')->orderBy('name')->get();
        $groups = $this->getGroups();

        // Build matrix: keyed by role slug, value is array of permission slugs
        $matrix = [];
        foreach ($roles as $role) {
            $matrix[$role->slug] = $role->permissions->pluck('slug')->sort()->values()->toArray();
        }

        return [
            'roles' => $roles,
            'permissions' => $permissions,
            'groups' => $groups,
            'matrix' => $matrix,
        ];
    }

    /**
     * Get permissions with role count.
     */
    public function listWithRoleCount(): Collection
    {
        return Permission::withCount('roles')
            ->orderBy('group')
            ->orderBy('name')
            ->get();
    }
}
