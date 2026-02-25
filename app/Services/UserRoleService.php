<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Services;

use Illuminate\Support\Collection;
use Omnify\SsoClient\Enums\ScopeType;
use Omnify\SsoClient\Models\Branch;
use Omnify\SsoClient\Models\Organization;
use Omnify\SsoClient\Models\Role;
use Omnify\SsoClient\Models\User;

class UserRoleService
{
    /**
     * Get user's role assignments.
     */
    public function getUserRoles(User $user, ?string $organizationId = null): Collection
    {
        $query = $user->roles();

        // If org context, filter to global + org roles
        if ($organizationId) {
            $query->where(function ($q) use ($organizationId) {
                $q->whereNull('user_roles.console_organization_id')
                    ->orWhere('user_roles.console_organization_id', $organizationId);
            });
        }

        $roles = $query->get();

        // Add organization and branch info
        return $roles->map(function ($role) {
            $organizationName = null;
            $branchName = null;

            if ($role->pivot->console_organization_id) {
                $org = Organization::where('console_organization_id', $role->pivot->console_organization_id)->first();
                $organizationName = $org?->name;
            }

            if ($role->pivot->console_branch_id) {
                $branch = Branch::where('console_branch_id', $role->pivot->console_branch_id)->first();
                $branchName = $branch?->name;
            }

            return [
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'level' => $role->level,
                ],
                'scope' => ScopeType::fromContext(
                    $role->pivot->console_organization_id,
                    $role->pivot->console_branch_id
                )->value,
                'console_organization_id' => $role->pivot->console_organization_id,
                'console_branch_id' => $role->pivot->console_branch_id,
                'organization_name' => $organizationName,
                'branch_name' => $branchName,
            ];
        });
    }

    /**
     * Assign a role to a user.
     *
     * @param  array{role_id: string, console_organization_id?: string|null, console_branch_id?: string|null}  $data
     * @return array{success: bool, error?: string, message?: string}
     */
    public function assignRole(User $user, array $data): array
    {
        $roleId = $data['role_id'];
        $organizationId = $data['console_organization_id'] ?? null;
        $branchId = $data['console_branch_id'] ?? null;

        // Validate role exists
        $role = Role::find($roleId);
        if (! $role) {
            return [
                'success' => false,
                'error' => 'ROLE_NOT_FOUND',
                'message' => 'Role not found',
            ];
        }

        // Check for duplicate assignment
        $existingAssignment = $user->roles()
            ->where('roles.id', $roleId)
            ->wherePivot('console_organization_id', $organizationId)
            ->wherePivot('console_branch_id', $branchId)
            ->exists();

        if ($existingAssignment) {
            return [
                'success' => false,
                'error' => 'DUPLICATE_ASSIGNMENT',
                'message' => 'User already has this role assignment',
            ];
        }

        // Attach role with scope
        $user->roles()->attach($roleId, [
            'console_organization_id' => $organizationId,
            'console_branch_id' => $branchId,
        ]);

        return [
            'success' => true,
            'message' => 'Role assigned successfully',
        ];
    }

    /**
     * Remove a role from a user.
     *
     * @return array{success: bool, error?: string, message?: string}
     */
    public function removeRole(User $user, string $roleId, ?string $organizationId = null, ?string $branchId = null): array
    {
        // Find the assignment
        $assignment = $user->roles()
            ->where('roles.id', $roleId)
            ->wherePivot('console_organization_id', $organizationId)
            ->wherePivot('console_branch_id', $branchId)
            ->first();

        if (! $assignment) {
            return [
                'success' => false,
                'error' => 'ASSIGNMENT_NOT_FOUND',
                'message' => 'Role assignment not found',
            ];
        }

        // Detach with specific pivot conditions
        $user->roles()->wherePivot('console_organization_id', $organizationId)
            ->wherePivot('console_branch_id', $branchId)
            ->detach($roleId);

        return [
            'success' => true,
            'message' => 'Role removed successfully',
        ];
    }

    /**
     * Sync user's roles (replace all).
     *
     * @param  array<array{role_id: string, console_organization_id?: string|null, console_branch_id?: string|null}>  $assignments
     */
    public function syncRoles(User $user, array $assignments, ?string $organizationId = null): array
    {
        // If org context, only sync roles for that org
        if ($organizationId) {
            // Remove existing roles for this org
            $user->roles()
                ->wherePivot('console_organization_id', $organizationId)
                ->detach();

            // Also remove global roles if specified
            $globalAssignments = collect($assignments)->filter(fn ($a) => ($a['console_organization_id'] ?? null) === null);
            if ($globalAssignments->isNotEmpty()) {
                $user->roles()
                    ->wherePivot('console_organization_id', null)
                    ->detach();
            }
        } else {
            // Sync all roles
            $user->roles()->detach();
        }

        // Attach new roles
        foreach ($assignments as $assignment) {
            $user->roles()->attach($assignment['role_id'], [
                'console_organization_id' => $assignment['console_organization_id'] ?? null,
                'console_branch_id' => $assignment['console_branch_id'] ?? null,
            ]);
        }

        return [
            'success' => true,
            'message' => 'Roles synced successfully',
            'count' => count($assignments),
        ];
    }
}
