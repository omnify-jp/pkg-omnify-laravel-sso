<?php

namespace Omnify\Core\Database\Seeders\Concerns;

use Illuminate\Support\Facades\DB;
use Omnify\Core\Models\Role;
use Omnify\Core\Models\User;

/**
 * Trait for assigning roles to users with scope support.
 *
 * This trait provides methods to assign roles to users with optional scope
 * (organization and/or branch level). It supports the hierarchical permission
 * model used by Omnify SSO.
 *
 * Scope Hierarchy:
 * - Global (org=null, branch=null): Role applies everywhere
 * - Organization-wide (org=uuid, branch=null): Role applies to all branches in org
 * - Branch-specific (org=uuid, branch=uuid): Role applies only to specific branch
 *
 * @example
 * ```php
 * class MySeeder extends Seeder
 * {
 *     use AssignsRoles, FetchesConsoleData;
 *
 *     public function run(): void
 *     {
 *         $organizationData = $this->fetchOrgDataFromConsole('company-abc');
 *
 *         // Global assignment
 *         $this->assignRoleToUserByEmail('admin@example.com', 'admin');
 *
 *         // Organization-wide assignment
 *         $this->assignRoleToUserByEmail('manager@example.com', 'manager', $organizationData['organization_id']);
 *
 *         // Branch-specific assignment
 *         $tokyoBranch = $this->getBranchId($organizationData, 'TOKYO');
 *         $this->assignRoleToUserByEmail('staff@example.com', 'member', $organizationData['organization_id'], $tokyoBranch);
 *     }
 * }
 * ```
 *
 * @see \Omnify\Core\Database\Seeders\Concerns\FetchesConsoleData
 * @see \Omnify\Core\Database\Seeders\SsoRolesSeeder
 */
trait AssignsRoles
{
    /**
     * Assign a role to user with optional scope (org/branch).
     *
     * Uses updateOrInsert to handle duplicates gracefully.
     * Note: Primary key is (user_id, role_id), so scope gets updated if role already assigned.
     *
     * @param  string|null  $organizationId  Console organization ID
     * @param  string|null  $branchId  Console branch ID
     */
    protected function assignRoleToUser(User $user, Role $role, ?string $organizationId = null, ?string $branchId = null): void
    {
        DB::table('role_user')->updateOrInsert(
            [
                'user_id' => $user->id,
                'role_id' => $role->id,
            ],
            [
                'console_organization_id' => $organizationId,
                'console_branch_id' => $branchId,
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Assign role to user by email.
     *
     * @param  string  $email  User email
     * @param  string  $roleSlug  Role slug (e.g., 'admin')
     * @param  string|null  $organizationId  Console organization ID
     * @param  string|null  $branchId  Console branch ID
     * @return bool Whether assignment was successful
     */
    protected function assignRoleToUserByEmail(
        string $email,
        string $roleSlug,
        ?string $organizationId = null,
        ?string $branchId = null
    ): bool {
        $user = User::where('email', $email)->first();
        $role = Role::where('slug', $roleSlug)->first();

        if (! $user || ! $role) {
            return false;
        }

        $this->assignRoleToUser($user, $role, $organizationId, $branchId);

        return true;
    }

    /**
     * Remove all role assignments for a user in a specific scope.
     *
     * @return int Number of removed assignments
     */
    protected function removeUserRolesInScope(User $user, ?string $organizationId = null, ?string $branchId = null): int
    {
        return DB::table('role_user')
            ->where('user_id', $user->id)
            ->where('console_organization_id', $organizationId)
            ->where('console_branch_id', $branchId)
            ->delete();
    }
}
