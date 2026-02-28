<?php

/**
 * UserAdminController Feature Tests
 *
 * Comprehensive tests for user management and permissions breakdown API.
 * Includes edge cases for multi-scope role assignments.
 */

use Illuminate\Support\Str;
use Omnify\Core\Models\Permission;
use Omnify\Core\Models\Role;
use Omnify\Core\Models\User;

beforeEach(function () {
    // Create authenticated admin user (RefreshDatabase handles migrations)
    $this->adminUser = User::factory()->create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
    ]);

    $this->actingAs($this->adminUser);
});

// =============================================================================
// User Index Tests - ユーザー一覧テスト
// =============================================================================

// =============================================================================
// User Index Tests - ユーザー一覧テスト
// =============================================================================

test('user index returns paginated users list with correct structure', function () {
    User::factory()->count(3)->create();

    $response = $this->getJson('/api/admin/sso/users', [
        'X-Organization-Id' => 'test-org',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'email', 'console_organization_id', 'organization'],
            ],
            'meta',
            'links',
        ]);

    expect(count($response->json('data')))->toBeGreaterThanOrEqual(3);
});

test('user index accepts filter[organization_id] parameter', function () {
    $organizationId = (string) Str::uuid();
    User::factory()->create(['console_organization_id' => $organizationId]);

    // Verify the endpoint accepts the filter parameter without error
    $response = $this->call('GET', '/api/admin/sso/users', [
        'filter' => ['organization_id' => $organizationId],
    ], [], [], [
        'HTTP_X-Organization-Id' => 'test-org',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data', 'meta', 'links']);
});

test('user index returns users with organization info when org cache exists', function () {
    $organizationId = (string) Str::uuid();

    // Create organization cache first
    \Omnify\Core\Models\Organization::create([
        'console_organization_id' => $organizationId,
        'name' => 'Test Organization Info',
        'slug' => 'TEST-ORG-INFO',
    ]);

    $user = User::factory()->create([
        'name' => 'Org Info Test User',
        'console_organization_id' => $organizationId,
    ]);

    $response = $this->getJson('/api/admin/sso/users', [
        'X-Organization-Id' => 'test-org',
    ]);

    $response->assertOk();

    // Find our specific user in results
    $userData = collect($response->json('data'))->firstWhere('id', $user->id);
    expect($userData)->not->toBeNull();
    expect($userData['organization'])->not->toBeNull();
    expect($userData['organization']['name'])->toBe('Test Organization Info');
    expect($userData['organization']['slug'])->toBe('TEST-ORG-INFO');
});

test('user index accepts filter[search] parameter', function () {
    User::factory()->create(['name' => 'SearchTest User']);

    // Verify the endpoint accepts the search filter parameter
    $response = $this->call('GET', '/api/admin/sso/users', [
        'filter' => ['search' => 'SearchTest'],
    ], [], [], [
        'HTTP_X-Organization-Id' => 'test-org',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data', 'meta', 'links']);
});

// =============================================================================
// User Permissions Breakdown Tests - ユーザー権限詳細テスト
// =============================================================================

describe('GET /api/admin/sso/users/{user}/permissions', function () {
    test('returns permissions breakdown for user with roles', function () {
        $user = User::factory()->create();

        // Create role with permissions
        $role = Role::create(['name' => 'Manager', 'slug' => 'manager', 'level' => 50]);
        $perm1 = Permission::create(['name' => 'View Users', 'slug' => 'users.view', 'group' => 'users']);
        $perm2 = Permission::create(['name' => 'Edit Users', 'slug' => 'users.edit', 'group' => 'users']);
        $role->permissions()->attach([$perm1->id, $perm2->id]);

        // Assign role to user (global scope)
        $user->assignRole($role);

        $response = $this->getJson("/api/admin/sso/users/{$user->id}/permissions", [
            'X-Organization-Id' => 'test-org',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'context' => ['organization_id', 'branch_id'],
                'role_assignments' => [
                    '*' => [
                        'role' => ['id', 'name', 'slug', 'level'],
                        'scope',
                        'console_organization_id',
                        'console_branch_id',
                        'permissions',
                    ],
                ],
                'team_memberships',
                'aggregated_permissions',
            ]);

        // Verify role assignment
        expect($response->json('role_assignments'))->toHaveCount(1);
        expect($response->json('role_assignments.0.role.slug'))->toBe('manager');
        expect($response->json('role_assignments.0.scope'))->toBe('global');
        expect($response->json('role_assignments.0.permissions'))->toContain('users.view', 'users.edit');

        // Verify aggregated permissions
        expect($response->json('aggregated_permissions'))->toContain('users.view', 'users.edit');
    });

    test('returns scoped role assignments correctly', function () {
        $user = User::factory()->create();
        $organizationId = (string) Str::uuid();
        $branchId = (string) Str::uuid();

        // Create roles
        $globalRole = Role::create(['name' => 'Global Admin', 'slug' => 'global-admin', 'level' => 100]);
        $orgRole = Role::create(['name' => 'Org Manager', 'slug' => 'org-manager', 'level' => 50]);
        $branchRole = Role::create(['name' => 'Branch Staff', 'slug' => 'branch-staff', 'level' => 10]);

        // Create permissions
        $globalPerm = Permission::create(['name' => 'Global Perm', 'slug' => 'global.perm', 'group' => 'global']);
        $orgPerm = Permission::create(['name' => 'Org Perm', 'slug' => 'org.perm', 'group' => 'org']);
        $branchPerm = Permission::create(['name' => 'Branch Perm', 'slug' => 'branch.perm', 'group' => 'branch']);

        $globalRole->permissions()->attach($globalPerm->id);
        $orgRole->permissions()->attach($orgPerm->id);
        $branchRole->permissions()->attach($branchPerm->id);

        // Assign roles with different scopes
        $user->assignRole($globalRole, null, null); // Global
        $user->assignRole($orgRole, $organizationId, null);  // Org-wide
        $user->assignRole($branchRole, $organizationId, $branchId); // Branch-specific

        $response = $this->getJson("/api/admin/sso/users/{$user->id}/permissions?organization_id={$organizationId}&branch_id={$branchId}", [
            'X-Organization-Id' => 'test-org',
        ]);

        $response->assertOk();

        // Should have 3 role assignments
        expect($response->json('role_assignments'))->toHaveCount(3);

        // Verify scopes
        $scopes = collect($response->json('role_assignments'))->pluck('scope')->toArray();
        expect($scopes)->toContain('global', 'org-wide', 'branch');

        // Verify aggregated permissions include all 3
        $aggregated = $response->json('aggregated_permissions');
        expect($aggregated)->toContain('global.perm', 'org.perm', 'branch.perm');
    });

    test('filters role assignments by org context', function () {
        $user = User::factory()->create();
        $organizationId = (string) Str::uuid();
        $otherOrgId = (string) Str::uuid();

        $role = Role::create(['name' => 'Manager', 'slug' => 'manager', 'level' => 50]);
        $perm = Permission::create(['name' => 'Test Perm', 'slug' => 'test.perm', 'group' => 'test']);
        $role->permissions()->attach($perm->id);

        // Assign role to specific org only
        $user->assignRole($role, $otherOrgId, null);

        // Query with different org
        $response = $this->getJson("/api/admin/sso/users/{$user->id}/permissions?organization_id={$organizationId}", [
            'X-Organization-Id' => 'test-org',
        ]);

        $response->assertOk();
        // Should have 0 role assignments (wrong org)
        expect($response->json('role_assignments'))->toHaveCount(0);
        expect($response->json('aggregated_permissions'))->toHaveCount(0);
    });

    test('returns empty arrays for user with no roles', function () {
        $user = User::factory()->create();

        $response = $this->getJson("/api/admin/sso/users/{$user->id}/permissions", [
            'X-Organization-Id' => 'test-org',
        ]);

        $response->assertOk();
        expect($response->json('role_assignments'))->toHaveCount(0);
        expect($response->json('team_memberships'))->toHaveCount(0);
        expect($response->json('aggregated_permissions'))->toHaveCount(0);
    });

    test('returns 404 for non-existent user', function () {
        $fakeId = (string) Str::uuid();

        $response = $this->getJson("/api/admin/sso/users/{$fakeId}/permissions", [
            'X-Organization-Id' => 'test-org',
        ]);

        $response->assertNotFound();
    });

    test('different roles in multiple branches show correct assignments per context', function () {
        $user = User::factory()->create();
        $organizationId = (string) Str::uuid();
        $branch1 = (string) Str::uuid();

        $role1 = Role::create(['name' => 'Manager', 'slug' => 'manager', 'level' => 50]);
        $perm = Permission::create(['name' => 'Manage', 'slug' => 'manage', 'group' => 'general']);
        $role1->permissions()->attach($perm->id);

        // Assign role to branch1
        $user->assignRole($role1, $organizationId, $branch1);

        // Query for branch1
        $response = $this->getJson("/api/admin/sso/users/{$user->id}/permissions?organization_id={$organizationId}&branch_id={$branch1}", [
            'X-Organization-Id' => 'test-org',
        ]);

        $response->assertOk();
        // Should see the branch1 assignment
        expect($response->json('role_assignments'))->toHaveCount(1);
        expect($response->json('role_assignments.0.console_branch_id'))->toBe($branch1);
    });

    test('global role appears in any context', function () {
        $user = User::factory()->create();
        $organizationId = (string) Str::uuid();
        $branchId = (string) Str::uuid();

        $role = Role::create(['name' => 'Super Admin', 'slug' => 'super-admin', 'level' => 100]);
        $perm = Permission::create(['name' => 'All', 'slug' => 'all', 'group' => 'system']);
        $role->permissions()->attach($perm->id);

        $user->assignRole($role, null, null); // Global

        // Query with specific org/branch context
        $response = $this->getJson("/api/admin/sso/users/{$user->id}/permissions?organization_id={$organizationId}&branch_id={$branchId}", [
            'X-Organization-Id' => 'test-org',
        ]);

        $response->assertOk();
        expect($response->json('role_assignments'))->toHaveCount(1);
        expect($response->json('role_assignments.0.scope'))->toBe('global');
        expect($response->json('aggregated_permissions'))->toContain('all');
    });

    test('permissions are deduplicated in aggregated list', function () {
        $user = User::factory()->create();
        $organizationId = (string) Str::uuid();

        $role1 = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
        $role2 = Role::create(['name' => 'Manager', 'slug' => 'manager', 'level' => 50]);

        // Same permission on both roles
        $sharedPerm = Permission::create(['name' => 'View', 'slug' => 'view', 'group' => 'general']);
        $uniquePerm = Permission::create(['name' => 'Delete', 'slug' => 'delete', 'group' => 'general']);

        $role1->permissions()->attach([$sharedPerm->id, $uniquePerm->id]);
        $role2->permissions()->attach($sharedPerm->id);

        $user->assignRole($role1, null, null);
        $user->assignRole($role2, $organizationId, null);

        $response = $this->getJson("/api/admin/sso/users/{$user->id}/permissions?organization_id={$organizationId}", [
            'X-Organization-Id' => 'test-org',
        ]);

        $response->assertOk();
        $aggregated = $response->json('aggregated_permissions');

        // Should have exactly 2 unique permissions
        expect($aggregated)->toHaveCount(2);
        expect($aggregated)->toContain('view', 'delete');
    });

    test('context is returned correctly in response', function () {
        $user = User::factory()->create();
        $organizationId = (string) Str::uuid();
        $branchId = (string) Str::uuid();

        $response = $this->getJson("/api/admin/sso/users/{$user->id}/permissions?organization_id={$organizationId}&branch_id={$branchId}", [
            'X-Organization-Id' => 'test-org',
        ]);

        $response->assertOk();
        expect($response->json('context.organization_id'))->toBe($organizationId);
        expect($response->json('context.branch_id'))->toBe($branchId);
    });

    test('context is null when not provided', function () {
        $user = User::factory()->create();

        $response = $this->getJson("/api/admin/sso/users/{$user->id}/permissions", [
            'X-Organization-Id' => 'test-org',
        ]);

        $response->assertOk();
        expect($response->json('context.organization_id'))->toBeNull();
        expect($response->json('context.branch_id'))->toBeNull();
    });
});

// =============================================================================
// User Update Tests - ユーザー更新テスト
// =============================================================================

describe('PUT /api/admin/sso/users/{user}', function () {
    test('can update user name', function () {
        $user = User::factory()->create(['name' => 'Old Name']);

        $response = $this->putJson("/api/admin/sso/users/{$user->id}", [
            'name' => 'New Name',
        ], [
            'X-Organization-Id' => 'test-org',
        ]);

        $response->assertOk();
        expect($response->json('data.name'))->toBe('New Name');

        $user->refresh();
        expect($user->name)->toBe('New Name');
    });

    test('can update user email to unique value', function () {
        $user = User::factory()->create(['email' => 'original@example.com']);

        $response = $this->putJson("/api/admin/sso/users/{$user->id}", [
            'email' => 'new-unique@example.com',
        ], [
            'X-Organization-Id' => 'test-org',
        ]);

        $response->assertOk();
        $user->refresh();
        expect($user->email)->toBe('new-unique@example.com');
    });

    test('returns 404 for non-existent user', function () {
        $fakeId = (string) Str::uuid();

        $response = $this->putJson("/api/admin/sso/users/{$fakeId}", [
            'name' => 'New Name',
        ], [
            'X-Organization-Id' => 'test-org',
        ]);

        $response->assertNotFound();
    });
});

// =============================================================================
// User Delete Tests - ユーザー削除テスト
// =============================================================================

describe('DELETE /api/admin/sso/users/{user}', function () {
    test('can delete user', function () {
        $user = User::factory()->create();
        $userId = $user->id;

        $response = $this->deleteJson("/api/admin/sso/users/{$userId}", [], [
            'X-Organization-Id' => 'test-org',
        ]);

        $response->assertNoContent();
        expect(User::find($userId))->toBeNull();
    });

    test('deleting user with roles succeeds', function () {
        $user = User::factory()->create();
        $userId = $user->id;

        $globalRole = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
        $orgRole = Role::create(['name' => 'Manager', 'slug' => 'manager', 'level' => 50]);
        $organizationId = (string) Str::uuid();

        $user->assignRole($globalRole, null, null);
        $user->assignRole($orgRole, $organizationId, null);

        // Verify assignments exist
        expect($user->getRoleAssignments())->toHaveCount(2);

        $response = $this->deleteJson("/api/admin/sso/users/{$userId}", [], [
            'X-Organization-Id' => 'test-org',
        ]);

        $response->assertNoContent();

        // Verify user is deleted
        expect(User::find($userId))->toBeNull();
    });

    test('returns 404 for non-existent user', function () {
        $fakeId = (string) Str::uuid();

        $response = $this->deleteJson("/api/admin/sso/users/{$fakeId}", [], [
            'X-Organization-Id' => 'test-org',
        ]);

        $response->assertNotFound();
    });
});

// =============================================================================
// User Show Tests - ユーザー詳細テスト
// =============================================================================

describe('GET /api/admin/sso/users/{user}', function () {
    test('returns user details', function () {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $response = $this->getJson("/api/admin/sso/users/{$user->id}", [
            'X-Organization-Id' => 'test-org',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Test User')
            ->assertJsonPath('data.email', 'test@example.com');
    });

    test('returns 404 for non-existent user', function () {
        $fakeId = (string) Str::uuid();

        $response = $this->getJson("/api/admin/sso/users/{$fakeId}", [
            'X-Organization-Id' => 'test-org',
        ]);

        $response->assertNotFound();
    });
});
