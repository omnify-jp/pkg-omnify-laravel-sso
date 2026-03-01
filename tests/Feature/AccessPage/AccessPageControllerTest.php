<?php

/**
 * AccessPageController Feature Tests
 *
 * IAM (Access Management) ページコントローラーのテスト
 *
 * Routes prefix: admin/iam, Middleware: ['web'], Name prefix: access.
 * Tests cover: overview, scope explorer, users, roles, assignments, permissions.
 */

use Inertia\Testing\AssertableInertia;
use Omnify\Core\Models\Branch;
use Omnify\Core\Models\Organization;
use Omnify\Core\Models\Permission;
use Omnify\Core\Models\Role;
use Omnify\Core\Models\User;

// =============================================================================
// Overview — GET /settings/iam/
// =============================================================================

describe('overview', function () {
    it('returns Inertia page', function () {
        $user = User::factory()->standalone()->create();

        $this->actingAs($user)
            ->get('/settings/iam/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/iam/overview')
            );
    });

    it('returns correct stats', function () {
        // Create test data
        User::factory()->standalone()->count(3)->create();
        $globalRole = Role::factory()->create(['console_organization_id' => null]);
        $orgRole = Role::factory()->create(['console_organization_id' => 'org-1']);
        Permission::factory()->count(5)->create();

        $user = User::factory()->standalone()->create();

        $this->actingAs($user)
            ->get('/settings/iam/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/iam/overview')
                ->has('stats')
                ->where('stats.total_users', 4) // 3 + acting user
                ->where('stats.total_roles', 2)
                ->where('stats.global_roles', 1)
                ->where('stats.total_permissions', 5)
            );
    });

    it('returns recent assignments', function () {
        $user = User::factory()->standalone()->create();
        $role = Role::factory()->create(['console_organization_id' => null]);

        // Attach role to user
        $user->roles()->attach($role->id, [
            'console_organization_id' => null,
            'console_branch_id' => null,
        ]);

        $this->actingAs($user)
            ->get('/settings/iam/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/iam/overview')
                ->has('recent_assignments', 1)
                ->where('recent_assignments.0.user.id', $user->id)
                ->where('recent_assignments.0.role.id', $role->id)
                ->where('recent_assignments.0.scope_type', 'global')
            );
    });
});

// =============================================================================
// Scope Explorer — GET /settings/iam/scope-explorer
// =============================================================================

describe('scope explorer', function () {
    it('returns Inertia page', function () {
        $user = User::factory()->standalone()->create();

        $this->actingAs($user)
            ->get('/settings/iam/scope-explorer')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/iam/scope-explorer')
            );
    });

    it('returns organizations tree', function () {
        $org = Organization::factory()->create();
        $branch = Branch::factory()->create([
            'console_organization_id' => $org->console_organization_id,
        ]);

        $user = User::factory()->standalone()->create();

        $this->actingAs($user)
            ->get('/settings/iam/scope-explorer')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/iam/scope-explorer')
                ->has('organizations') // at least 1 (other tests may create orgs)
                ->has('branches')
                ->has('assignments')
            );
    });
});

// =============================================================================
// Users — GET /settings/iam/users
// =============================================================================

describe('users', function () {
    it('returns Inertia page with paginated users', function () {
        $user = User::factory()->standalone()->create();

        $this->actingAs($user)
            ->get('/settings/iam/users')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/iam/users')
                ->has('users.data')
                ->has('users.meta')
                ->where('users.meta.per_page', 20)
            );
    });

    it('supports search filter', function () {
        $alice = User::factory()->standalone()->create(['name' => 'Alice Smith', 'email' => 'alice@example.com']);
        $bob = User::factory()->standalone()->create(['name' => 'Bob Jones', 'email' => 'bob@example.com']);

        $this->actingAs($alice)
            ->get('/settings/iam/users?search=Alice')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/iam/users')
                ->where('filters.search', 'Alice')
                ->has('users.data', 1)
                ->where('users.data.0.name', 'Alice Smith')
            );
    });

    it('includes roles_count', function () {
        $user = User::factory()->standalone()->create();
        $role1 = Role::factory()->create(['console_organization_id' => null]);
        $role2 = Role::factory()->create(['console_organization_id' => null]);
        $user->roles()->attach([$role1->id, $role2->id]);

        $this->actingAs($user)
            ->get('/settings/iam/users')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/iam/users')
                ->where('users.data.0.roles_count', 2)
            );
    });
});

// =============================================================================
// User Show — GET /settings/iam/users/{userId}
// =============================================================================

describe('user show', function () {
    it('returns user with role assignments', function () {
        $user = User::factory()->standalone()->create();
        $role = Role::factory()->create(['console_organization_id' => null]);
        $user->roles()->attach($role->id, [
            'console_organization_id' => null,
            'console_branch_id' => null,
        ]);

        $this->actingAs($user)
            ->get("/settings/iam/users/{$user->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/iam/user-detail')
                ->where('user.id', $user->id)
                ->has('assignments', 1)
                ->where('assignments.0.role.id', $role->id)
                ->where('assignments.0.scope_type', 'global')
            );
    });

    it('returns all permissions for assignment form', function () {
        $user = User::factory()->standalone()->create();
        Permission::factory()->count(3)->create();

        $this->actingAs($user)
            ->get("/settings/iam/users/{$user->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/iam/user-detail')
                ->has('all_permissions', 3)
            );
    });

    it('returns 404 for non-existent user', function () {
        $user = User::factory()->standalone()->create();

        $this->actingAs($user)
            ->get('/settings/iam/users/non-existent-uuid')
            ->assertNotFound();
    });
});

// =============================================================================
// Roles — GET /settings/iam/roles
// =============================================================================

describe('roles', function () {
    it('returns Inertia page', function () {
        $user = User::factory()->standalone()->create();

        $this->actingAs($user)
            ->get('/settings/iam/roles')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/iam/roles')
                ->has('roles')
            );
    });

    it('includes permissions_count', function () {
        $role = Role::factory()->create(['console_organization_id' => null]);
        $permissions = Permission::factory()->count(3)->create();
        $role->permissions()->attach($permissions->pluck('id'));

        $user = User::factory()->standalone()->create();

        $this->actingAs($user)
            ->get('/settings/iam/roles')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/iam/roles')
                ->where('roles.0.permissions_count', 3)
            );
    });
});

// =============================================================================
// Role Show — GET /settings/iam/roles/{roleId}
// =============================================================================

describe('role show', function () {
    it('returns role with permissions', function () {
        $role = Role::factory()->create(['console_organization_id' => null]);
        $permission = Permission::factory()->create();
        $role->permissions()->attach($permission->id);

        $user = User::factory()->standalone()->create();

        $this->actingAs($user)
            ->get("/settings/iam/roles/{$role->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/iam/role-detail')
                ->where('role.id', $role->id)
                ->has('permissions', 1)
                ->where('permissions.0.id', $permission->id)
            );
    });

    it('returns role assignments', function () {
        $role = Role::factory()->create(['console_organization_id' => null]);
        $assignedUser = User::factory()->standalone()->create();
        $assignedUser->roles()->attach($role->id, [
            'console_organization_id' => null,
            'console_branch_id' => null,
        ]);

        $actingUser = User::factory()->standalone()->create();

        $this->actingAs($actingUser)
            ->get("/settings/iam/roles/{$role->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/iam/role-detail')
                ->has('assignments', 1)
                ->where('assignments.0.user.id', $assignedUser->id)
                ->where('assignments.0.scope_type', 'global')
            );
    });

    it('returns 404 for non-existent role', function () {
        $user = User::factory()->standalone()->create();

        $this->actingAs($user)
            ->get('/settings/iam/roles/non-existent-uuid')
            ->assertNotFound();
    });
});

// =============================================================================
// Role Create — GET /settings/iam/roles/create
// =============================================================================

describe('role create', function () {
    it('returns form page with permissions grouped', function () {
        Permission::factory()->create(['group' => 'posts', 'slug' => 'posts.create', 'name' => 'Create Posts']);
        Permission::factory()->create(['group' => 'posts', 'slug' => 'posts.edit', 'name' => 'Edit Posts']);
        Permission::factory()->create(['group' => 'users', 'slug' => 'users.manage', 'name' => 'Manage Users']);

        $user = User::factory()->standalone()->create();

        $this->actingAs($user)
            ->get('/settings/iam/roles/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/iam/role-create')
                ->has('all_permissions', 3)
            );
    });
});

// =============================================================================
// Role Store — POST /settings/iam/roles
// =============================================================================

describe('role store', function () {
    it('creates role', function () {
        $user = User::factory()->standalone()->create();

        $this->actingAs($user)
            ->post('/settings/iam/roles', [
                'name' => 'Editor',
                'level' => 5,
                'description' => 'Can edit content',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('roles', [
            'name' => 'Editor',
            'slug' => 'editor',
            'level' => 5,
        ]);
    });

    it('attaches permissions', function () {
        $user = User::factory()->standalone()->create();
        $p1 = Permission::factory()->create();
        $p2 = Permission::factory()->create();

        $this->actingAs($user)
            ->post('/settings/iam/roles', [
                'name' => 'Editor',
                'level' => 5,
                'permission_ids' => [$p1->id, $p2->id],
            ])
            ->assertRedirect();

        $role = Role::where('slug', 'editor')->first();
        expect($role)->not->toBeNull();
        expect($role->permissions()->count())->toBe(2);
    });

    it('validates name required', function () {
        $user = User::factory()->standalone()->create();

        $this->actingAs($user)
            ->post('/settings/iam/roles', [
                'level' => 5,
            ])
            ->assertSessionHasErrors(['name']);
    });

    it('validates level range 1-10', function () {
        $user = User::factory()->standalone()->create();

        $this->actingAs($user)
            ->post('/settings/iam/roles', [
                'name' => 'Test Role',
                'level' => 0,
            ])
            ->assertSessionHasErrors(['level']);

        $this->actingAs($user)
            ->post('/settings/iam/roles', [
                'name' => 'Test Role 2',
                'level' => 11,
            ])
            ->assertSessionHasErrors(['level']);
    });

    it('redirects to role show', function () {
        $user = User::factory()->standalone()->create();

        $response = $this->actingAs($user)
            ->post('/settings/iam/roles', [
                'name' => 'Reviewer',
                'level' => 3,
            ]);

        $role = Role::where('slug', 'reviewer')->first();
        expect($role)->not->toBeNull();

        $response->assertRedirect(route('access.roles.show', $role->id));
    });
});

// =============================================================================
// Role Edit — GET /settings/iam/roles/{roleId}/edit
// =============================================================================

describe('role edit', function () {
    it('returns form with role data and permissions', function () {
        $role = Role::factory()->create(['console_organization_id' => null]);
        $permission = Permission::factory()->create();
        $role->permissions()->attach($permission->id);

        // Create additional permissions for the all_permissions list
        Permission::factory()->count(2)->create();

        $user = User::factory()->standalone()->create();

        $this->actingAs($user)
            ->get("/settings/iam/roles/{$role->id}/edit")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/iam/role-edit')
                ->where('role.id', $role->id)
                ->where('role.name', $role->name)
                ->has('permissions', 1)
                ->has('all_permissions', 3)
            );
    });
});

// =============================================================================
// Role Update — PUT /settings/iam/roles/{roleId}
// =============================================================================

describe('role update', function () {
    it('modifies role', function () {
        $role = Role::factory()->create([
            'name' => 'Old Name',
            'slug' => 'old-name',
            'level' => 3,
            'console_organization_id' => null,
        ]);

        $user = User::factory()->standalone()->create();

        $this->actingAs($user)
            ->put("/settings/iam/roles/{$role->id}", [
                'name' => 'New Name',
                'level' => 7,
                'description' => 'Updated description',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'New Name',
            'slug' => 'new-name',
            'level' => 7,
        ]);
    });

    it('syncs permissions', function () {
        $role = Role::factory()->create(['console_organization_id' => null, 'level' => 5]);
        $oldPermission = Permission::factory()->create();
        $newPermission = Permission::factory()->create();
        $role->permissions()->attach($oldPermission->id);

        $user = User::factory()->standalone()->create();

        $this->actingAs($user)
            ->put("/settings/iam/roles/{$role->id}", [
                'name' => $role->name,
                'level' => 5,
                'permission_ids' => [$newPermission->id],
            ])
            ->assertRedirect();

        $role->refresh();
        $permissionIds = $role->permissions()->pluck('permissions.id')->all();
        expect($permissionIds)->toContain($newPermission->id);
        expect($permissionIds)->not->toContain($oldPermission->id);
    });

    it('redirects to role show', function () {
        $role = Role::factory()->create(['console_organization_id' => null]);

        $user = User::factory()->standalone()->create();

        $response = $this->actingAs($user)
            ->put("/settings/iam/roles/{$role->id}", [
                'name' => 'Updated Role',
                'level' => 5,
            ]);

        $response->assertRedirect(route('access.roles.show', $role->id));
    });
});

// =============================================================================
// Assignments — GET /settings/iam/assignments
// =============================================================================

describe('assignments', function () {
    it('returns Inertia page', function () {
        $user = User::factory()->standalone()->create();

        $this->actingAs($user)
            ->get('/settings/iam/assignments')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/iam/assignments')
                ->has('assignments')
            );
    });

    it('returns assignments with user and role data', function () {
        $user = User::factory()->standalone()->create();
        $role = Role::factory()->create(['console_organization_id' => null]);
        $user->roles()->attach($role->id, [
            'console_organization_id' => null,
            'console_branch_id' => null,
        ]);

        $this->actingAs($user)
            ->get('/settings/iam/assignments')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/iam/assignments')
                ->has('assignments', 1)
                ->where('assignments.0.user.id', $user->id)
                ->where('assignments.0.role.id', $role->id)
                ->where('assignments.0.scope_type', 'global')
            );
    });

    it('returns org-wide scope assignments', function () {
        $org = Organization::factory()->create();
        $user = User::factory()->standalone()->create();
        $role = Role::factory()->create(['console_organization_id' => null]);
        $user->roles()->attach($role->id, [
            'console_organization_id' => $org->console_organization_id,
            'console_branch_id' => null,
        ]);

        $this->actingAs($user)
            ->get('/settings/iam/assignments')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/iam/assignments')
                ->has('assignments', 1)
                ->where('assignments.0.scope_type', 'org-wide')
                ->where('assignments.0.organization_name', $org->name)
            );
    });
});

// =============================================================================
// Assignment Create — GET /settings/iam/assignments/create
// =============================================================================

describe('assignment create', function () {
    it('returns form with users, roles, orgs, branches', function () {
        User::factory()->standalone()->count(2)->create();
        Role::factory()->count(2)->create();
        Organization::factory()->count(1)->create();
        Branch::factory()->count(1)->create();

        $user = User::factory()->standalone()->create();

        $this->actingAs($user)
            ->get('/settings/iam/assignments/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/iam/assignment-create')
                ->has('users', 3) // 2 + acting user
                ->has('roles', 2)
                ->has('organizations') // at least 1 (other tests may create orgs)
                ->has('branches')
            );
    });
});

// =============================================================================
// Assignment Store — POST /settings/iam/assignments
// =============================================================================

describe('assignment store', function () {
    it('creates global assignment', function () {
        $user = User::factory()->standalone()->create();
        $role = Role::factory()->create(['console_organization_id' => null]);

        $targetUser = User::factory()->standalone()->create();

        $this->actingAs($user)
            ->post('/settings/iam/assignments', [
                'user_id' => $targetUser->id,
                'role_id' => $role->id,
                'scope_type' => 'global',
            ])
            ->assertRedirect(route('access.assignments'));

        expect($targetUser->roles()->where('roles.id', $role->id)->exists())->toBeTrue();
    });

    it('creates org-wide assignment', function () {
        $org = Organization::factory()->create();
        $user = User::factory()->standalone()->create();
        $role = Role::factory()->create(['console_organization_id' => null]);

        $targetUser = User::factory()->standalone()->create();

        $this->actingAs($user)
            ->post('/settings/iam/assignments', [
                'user_id' => $targetUser->id,
                'role_id' => $role->id,
                'scope_type' => 'org-wide',
                'organization_id' => $org->console_organization_id,
            ])
            ->assertRedirect(route('access.assignments'));

        $pivot = $targetUser->roles()
            ->withPivot('console_organization_id', 'console_branch_id')
            ->where('roles.id', $role->id)
            ->first();

        expect($pivot)->not->toBeNull();
        expect($pivot->pivot->console_organization_id)->toBe($org->console_organization_id);
        expect($pivot->pivot->console_branch_id)->toBeNull();
    });

    it('creates branch assignment', function () {
        $org = Organization::factory()->create();
        $branch = Branch::factory()->create([
            'console_organization_id' => $org->console_organization_id,
        ]);
        $user = User::factory()->standalone()->create();
        $role = Role::factory()->create(['console_organization_id' => null]);

        $targetUser = User::factory()->standalone()->create();

        $this->actingAs($user)
            ->post('/settings/iam/assignments', [
                'user_id' => $targetUser->id,
                'role_id' => $role->id,
                'scope_type' => 'branch',
                'organization_id' => $org->console_organization_id,
                'branch_id' => $branch->console_branch_id,
            ])
            ->assertRedirect(route('access.assignments'));

        $pivot = $targetUser->roles()
            ->withPivot('console_organization_id', 'console_branch_id')
            ->where('roles.id', $role->id)
            ->first();

        expect($pivot)->not->toBeNull();
        expect($pivot->pivot->console_organization_id)->toBe($org->console_organization_id);
        expect($pivot->pivot->console_branch_id)->toBe($branch->console_branch_id);
    });

    it('validates user_id required', function () {
        $user = User::factory()->standalone()->create();

        $this->actingAs($user)
            ->post('/settings/iam/assignments', [
                'role_id' => 'some-role-id',
                'scope_type' => 'global',
            ])
            ->assertSessionHasErrors(['user_id']);
    });

    it('validates role_id required', function () {
        $user = User::factory()->standalone()->create();
        $targetUser = User::factory()->standalone()->create();

        $this->actingAs($user)
            ->post('/settings/iam/assignments', [
                'user_id' => $targetUser->id,
                'scope_type' => 'global',
            ])
            ->assertSessionHasErrors(['role_id']);
    });

    it('validates scope_type required', function () {
        $user = User::factory()->standalone()->create();
        $targetUser = User::factory()->standalone()->create();
        $role = Role::factory()->create(['console_organization_id' => null]);

        $this->actingAs($user)
            ->post('/settings/iam/assignments', [
                'user_id' => $targetUser->id,
                'role_id' => $role->id,
            ])
            ->assertSessionHasErrors(['scope_type']);
    });

    it('validates scope_type must be valid value', function () {
        $user = User::factory()->standalone()->create();
        $targetUser = User::factory()->standalone()->create();
        $role = Role::factory()->create(['console_organization_id' => null]);

        $this->actingAs($user)
            ->post('/settings/iam/assignments', [
                'user_id' => $targetUser->id,
                'role_id' => $role->id,
                'scope_type' => 'invalid',
            ])
            ->assertSessionHasErrors(['scope_type']);
    });

    it('validates user_id must exist in users table', function () {
        $user = User::factory()->standalone()->create();
        $role = Role::factory()->create(['console_organization_id' => null]);

        $this->actingAs($user)
            ->post('/settings/iam/assignments', [
                'user_id' => 'non-existent-user-id',
                'role_id' => $role->id,
                'scope_type' => 'global',
            ])
            ->assertSessionHasErrors(['user_id']);
    });

    it('validates role_id must exist in roles table', function () {
        $user = User::factory()->standalone()->create();
        $targetUser = User::factory()->standalone()->create();

        $this->actingAs($user)
            ->post('/settings/iam/assignments', [
                'user_id' => $targetUser->id,
                'role_id' => 'non-existent-role-id',
                'scope_type' => 'global',
            ])
            ->assertSessionHasErrors(['role_id']);
    });
});

// =============================================================================
// Assignment Delete — DELETE /settings/iam/assignments/{userId}/{roleId}
// =============================================================================

describe('assignment delete', function () {
    it('removes assignment', function () {
        $user = User::factory()->standalone()->create();
        $targetUser = User::factory()->standalone()->create();
        $role = Role::factory()->create(['console_organization_id' => null]);

        $targetUser->roles()->attach($role->id, [
            'console_organization_id' => null,
            'console_branch_id' => null,
        ]);

        expect($targetUser->roles()->where('roles.id', $role->id)->exists())->toBeTrue();

        $this->actingAs($user)
            ->delete("/settings/iam/assignments/{$targetUser->id}/{$role->id}")
            ->assertRedirect();

        expect($targetUser->roles()->where('roles.id', $role->id)->exists())->toBeFalse();
    });

    it('redirects after delete', function () {
        $user = User::factory()->standalone()->create();
        $targetUser = User::factory()->standalone()->create();
        $role = Role::factory()->create(['console_organization_id' => null]);

        $targetUser->roles()->attach($role->id, [
            'console_organization_id' => null,
            'console_branch_id' => null,
        ]);

        $this->actingAs($user)
            ->delete("/settings/iam/assignments/{$targetUser->id}/{$role->id}")
            ->assertRedirect()
            ->assertSessionHas('success');
    });
});

// =============================================================================
// Permissions — GET /settings/iam/permissions
// =============================================================================

describe('permissions', function () {
    it('returns Inertia page', function () {
        $user = User::factory()->standalone()->create();

        $this->actingAs($user)
            ->get('/settings/iam/permissions')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/iam/permissions')
                ->has('permissions')
            );
    });

    it('groups permissions by group', function () {
        Permission::factory()->create(['group' => 'posts', 'slug' => 'posts.create', 'name' => 'Create Posts']);
        Permission::factory()->create(['group' => 'posts', 'slug' => 'posts.edit', 'name' => 'Edit Posts']);
        Permission::factory()->create(['group' => 'users', 'slug' => 'users.manage', 'name' => 'Manage Users']);

        $user = User::factory()->standalone()->create();

        $this->actingAs($user)
            ->get('/settings/iam/permissions')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/iam/permissions')
                ->has('permissions', 3)
                ->where('permissions.0.group', 'posts')
                ->where('permissions.1.group', 'posts')
                ->where('permissions.2.group', 'users')
            );
    });
});
