<?php

namespace Omnify\SsoClient\Database\Seeders;

use Illuminate\Database\Seeder;
use Omnify\SsoClient\Database\Seeders\Concerns\AssignsRoles;
use Omnify\SsoClient\Database\Seeders\Concerns\FetchesConsoleData;
use Omnify\SsoClient\Models\Permission;
use Omnify\SsoClient\Models\Role;

/**
 * Seeder for default SSO roles and permissions.
 *
 * Creates standard roles (admin, manager, supervisor, member, viewer)
 * and base permissions for service administration.
 *
 * Usage in your DatabaseSeeder:
 *   $this->call(\Omnify\SsoClient\Database\Seeders\SsoRolesSeeder::class);
 */
class SsoRolesSeeder extends Seeder
{
    use AssignsRoles, FetchesConsoleData;

    public function run(): void
    {
        $this->createRoles();
        $this->createPermissions();
        $this->assignDefaultPermissions();
    }

    /**
     * Create default roles.
     * Override this method to customize roles.
     */
    protected function createRoles(): void
    {
        $roles = $this->getDefaultRoles();

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['slug' => $role['slug']],
                $role
            );
        }

        $this->logInfo('Created '.count($roles).' roles');
    }

    /**
     * Get default roles configuration.
     * Override to customize.
     */
    protected function getDefaultRoles(): array
    {
        return [
            [
                'slug' => 'admin',
                'name' => 'Administrator',
                'description' => 'Full access to all features',
                'level' => 100,
            ],
            [
                'slug' => 'manager',
                'name' => 'Manager',
                'description' => 'Can manage users, teams and view reports',
                'level' => 50,
            ],
            [
                'slug' => 'supervisor',
                'name' => 'Supervisor',
                'description' => 'Can view and approve team activities',
                'level' => 30,
            ],
            [
                'slug' => 'member',
                'name' => 'Member',
                'description' => 'Basic access for regular users',
                'level' => 10,
            ],
            [
                'slug' => 'viewer',
                'name' => 'Viewer',
                'description' => 'Read-only access to dashboards and reports',
                'level' => 5,
            ],
        ];
    }

    /**
     * Create service-admin permissions.
     */
    protected function createPermissions(): void
    {
        $permissions = $this->getDefaultPermissions();
        $created = 0;

        foreach ($permissions as $permission) {
            // Generate name from slug if not provided
            $name = $permission['name'] ?? ucwords(str_replace(['.', '_', '-'], ' ', $permission['slug']));

            Permission::updateOrCreate(
                ['slug' => $permission['slug']],
                [
                    'name' => $name,
                    'group' => $permission['group'],
                ]
            );
            $created++;
        }

        $this->logInfo("Created/Updated {$created} permissions");
    }

    /**
     * Get default permissions configuration.
     * Override to customize.
     */
    protected function getDefaultPermissions(): array
    {
        return [
            // Service Admin - Roles
            ['slug' => 'service-admin.role.view', 'group' => 'service-admin.role'],
            ['slug' => 'service-admin.role.create', 'group' => 'service-admin.role'],
            ['slug' => 'service-admin.role.edit', 'group' => 'service-admin.role'],
            ['slug' => 'service-admin.role.delete', 'group' => 'service-admin.role'],
            ['slug' => 'service-admin.role.sync-permissions', 'group' => 'service-admin.role'],

            // Service Admin - Permissions
            ['slug' => 'service-admin.permission.view', 'group' => 'service-admin.permission'],
            ['slug' => 'service-admin.permission.create', 'group' => 'service-admin.permission'],
            ['slug' => 'service-admin.permission.edit', 'group' => 'service-admin.permission'],
            ['slug' => 'service-admin.permission.delete', 'group' => 'service-admin.permission'],
            ['slug' => 'service-admin.permission.sync', 'group' => 'service-admin.permission'],

            // Service Admin - Users
            ['slug' => 'service-admin.user.view', 'group' => 'service-admin.user'],
            ['slug' => 'service-admin.user.create', 'group' => 'service-admin.user'],
            ['slug' => 'service-admin.user.edit', 'group' => 'service-admin.user'],
            ['slug' => 'service-admin.user.delete', 'group' => 'service-admin.user'],
            ['slug' => 'service-admin.user.assign-roles', 'group' => 'service-admin.user'],

            // Service Admin - Teams
            ['slug' => 'service-admin.team.view', 'group' => 'service-admin.team'],
            ['slug' => 'service-admin.team.create', 'group' => 'service-admin.team'],
            ['slug' => 'service-admin.team.edit', 'group' => 'service-admin.team'],
            ['slug' => 'service-admin.team.delete', 'group' => 'service-admin.team'],

            // Dashboard
            ['slug' => 'dashboard.view', 'group' => 'dashboard'],
            ['slug' => 'dashboard.analytics', 'group' => 'dashboard'],
        ];
    }

    /**
     * Assign default permissions to roles.
     */
    protected function assignDefaultPermissions(): void
    {
        $admin = Role::where('slug', 'admin')->first();
        $manager = Role::where('slug', 'manager')->first();
        $supervisor = Role::where('slug', 'supervisor')->first();
        $member = Role::where('slug', 'member')->first();
        $viewer = Role::where('slug', 'viewer')->first();

        // Admin gets all permissions
        if ($admin) {
            $admin->permissions()->sync(Permission::pluck('id'));
            $this->logInfo('Assigned '.Permission::count().' permissions to Administrator');
        }

        // Manager gets most permissions except delete and sync
        if ($manager) {
            $managerPerms = Permission::where(function ($q) {
                $q->whereNotIn('slug', [
                    'service-admin.role.delete',
                    'service-admin.permission.delete',
                    'service-admin.user.delete',
                    'service-admin.team.delete',
                    'service-admin.permission.sync',
                ]);
            })->pluck('id');
            $manager->permissions()->sync($managerPerms);
            $this->logInfo("Assigned {$managerPerms->count()} permissions to Manager");
        }

        // Supervisor gets view + some edit permissions
        if ($supervisor) {
            $supervisorPerms = Permission::where(function ($q) {
                $q->where('slug', 'like', '%.view')
                    ->orWhere('slug', 'like', 'dashboard.%')
                    ->orWhereIn('slug', [
                        'service-admin.user.edit',
                        'service-admin.team.edit',
                    ]);
            })->pluck('id');
            $supervisor->permissions()->sync($supervisorPerms);
            $this->logInfo("Assigned {$supervisorPerms->count()} permissions to Supervisor");
        }

        // Member gets view-only permissions
        if ($member) {
            $memberPerms = Permission::where('slug', 'like', '%.view')
                ->orWhere('slug', 'dashboard.view')
                ->pluck('id');
            $member->permissions()->sync($memberPerms);
            $this->logInfo("Assigned {$memberPerms->count()} permissions to Member");
        }

        // Viewer gets dashboard only
        if ($viewer) {
            $viewerPerms = Permission::whereIn('slug', ['dashboard.view'])->pluck('id');
            $viewer->permissions()->sync($viewerPerms);
            $this->logInfo("Assigned {$viewerPerms->count()} permissions to Viewer");
        }
    }
}
