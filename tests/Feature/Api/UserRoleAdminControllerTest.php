<?php

/**
 * UserRoleAdminController Feature Tests
 *
 * ユーザーロール割り当てコントローラーのテスト
 * - GET /api/admin/sso/users/{userId}/roles → index
 * - POST /api/admin/sso/users/{userId}/roles → store
 * - PUT /api/admin/sso/users/{userId}/roles/sync → sync
 * - DELETE /api/admin/sso/users/{userId}/roles/{roleId} → destroy
 *
 * Admin routes middleware is simplified to ['api'] in TestCase config
 * (omnify-auth.routes.admin_middleware), so no auth middleware check needed.
 */

use Illuminate\Support\Str;
use Omnify\Core\Models\Organization;
use Omnify\Core\Models\Branch;
use Omnify\Core\Models\Role;
use Omnify\Core\Models\User;

beforeEach(function () {
    // Create an admin user for acting as
    $this->adminUser = User::factory()->create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
    ]);

    $this->actingAs($this->adminUser);
});

// =============================================================================
// Index Tests — GET /api/admin/sso/users/{userId}/roles
// =============================================================================

describe('GET /api/admin/sso/users/{userId}/roles', function () {
    test('returns user role assignments', function () {
        $user = User::factory()->create();
        $role = Role::create(['slug' => 'manager', 'name' => 'Manager', 'level' => 50]);

        $user->assignRole($role);

        $response = $this->getJson("/api/admin/sso/users/{$user->id}/roles");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'role' => ['id', 'name', 'slug', 'level'],
                        'scope',
                        'console_organization_id',
                        'console_branch_id',
                    ],
                ],
            ]);

        expect($response->json('data'))->toHaveCount(1);
        expect($response->json('data.0.role.slug'))->toBe('manager');
    });

    test('returns empty for user with no roles', function () {
        $user = User::factory()->create();

        $response = $this->getJson("/api/admin/sso/users/{$user->id}/roles");

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(0);
    });

    test('returns scope info for global, org-wide, and branch assignments', function () {
        $user = User::factory()->create();
        $organizationId = (string) Str::uuid();
        $branchId = (string) Str::uuid();

        $globalRole = Role::create(['slug' => 'super-admin', 'name' => 'Super Admin', 'level' => 100]);
        $orgRole = Role::create(['slug' => 'org-manager', 'name' => 'Org Manager', 'level' => 50]);
        $branchRole = Role::create(['slug' => 'branch-staff', 'name' => 'Branch Staff', 'level' => 10]);

        // Assign roles with different scopes
        $user->assignRole($globalRole, null, null);          // global
        $user->assignRole($orgRole, $organizationId, null);  // org-wide
        $user->assignRole($branchRole, $organizationId, $branchId); // branch

        $response = $this->getJson("/api/admin/sso/users/{$user->id}/roles");

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(3);

        $scopes = collect($response->json('data'))->pluck('scope')->toArray();
        expect($scopes)->toContain('global', 'org-wide', 'branch');
    });

    test('returns 404 for non-existent user', function () {
        $fakeId = (string) Str::uuid();

        $response = $this->getJson("/api/admin/sso/users/{$fakeId}/roles");

        $response->assertNotFound()
            ->assertJson(['error' => 'USER_NOT_FOUND']);
    });

    test('returns all roles without org filter', function () {
        $user = User::factory()->create();
        $org1 = (string) Str::uuid();

        $role1 = Role::create(['slug' => 'admin', 'name' => 'Admin', 'level' => 100]);
        $role2 = Role::create(['slug' => 'manager', 'name' => 'Manager', 'level' => 50]);

        $user->assignRole($role1, null, null);        // global
        $user->assignRole($role2, $org1, null);       // org-wide

        // Without X-Organization-Id header → returns all assignments
        $response = $this->getJson("/api/admin/sso/users/{$user->id}/roles");

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(2);

        $slugs = collect($response->json('data'))->pluck('role.slug')->toArray();
        expect($slugs)->toContain('admin', 'manager');
    });
});

// =============================================================================
// Store Tests — POST /api/admin/sso/users/{userId}/roles
// =============================================================================

describe('POST /api/admin/sso/users/{userId}/roles', function () {
    test('assigns global role to user', function () {
        $user = User::factory()->create();
        $role = Role::create(['slug' => 'admin', 'name' => 'Admin', 'level' => 100]);

        $response = $this->postJson("/api/admin/sso/users/{$user->id}/roles", [
            'role_id' => $role->id,
        ]);

        $response->assertStatus(201)
            ->assertJson(['message' => 'Role assigned successfully']);

        // Verify assignment exists
        $assignments = $user->getRoleAssignments();
        expect($assignments)->toHaveCount(1);
        expect($assignments->first()->slug)->toBe('admin');
    });

    test('assigns org-wide role to user', function () {
        $user = User::factory()->create();
        $role = Role::create(['slug' => 'org-manager', 'name' => 'Org Manager', 'level' => 50]);
        $organizationId = (string) Str::uuid();

        $response = $this->postJson("/api/admin/sso/users/{$user->id}/roles", [
            'role_id' => $role->id,
            'console_organization_id' => $organizationId,
        ]);

        $response->assertStatus(201);

        $assignments = $user->getRoleAssignments();
        expect($assignments)->toHaveCount(1);
        expect($assignments->first()->pivot->console_organization_id)->toBe($organizationId);
        expect($assignments->first()->pivot->console_branch_id)->toBeNull();
    });

    test('assigns branch-specific role to user', function () {
        $user = User::factory()->create();
        $role = Role::create(['slug' => 'branch-staff', 'name' => 'Branch Staff', 'level' => 10]);
        $organizationId = (string) Str::uuid();
        $branchId = (string) Str::uuid();

        $response = $this->postJson("/api/admin/sso/users/{$user->id}/roles", [
            'role_id' => $role->id,
            'console_organization_id' => $organizationId,
            'console_branch_id' => $branchId,
        ]);

        $response->assertStatus(201);

        $assignments = $user->getRoleAssignments();
        expect($assignments)->toHaveCount(1);
        expect($assignments->first()->pivot->console_organization_id)->toBe($organizationId);
        expect($assignments->first()->pivot->console_branch_id)->toBe($branchId);
    });

    test('validates role_id is required', function () {
        $user = User::factory()->create();

        $response = $this->postJson("/api/admin/sso/users/{$user->id}/roles", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role_id']);
    });

    test('validates role_id must exist in roles table', function () {
        $user = User::factory()->create();
        $fakeRoleId = (string) Str::uuid();

        $response = $this->postJson("/api/admin/sso/users/{$user->id}/roles", [
            'role_id' => $fakeRoleId,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role_id']);
    });

    test('returns 201 on success', function () {
        $user = User::factory()->create();
        $role = Role::create(['slug' => 'member', 'name' => 'Member', 'level' => 10]);

        $response = $this->postJson("/api/admin/sso/users/{$user->id}/roles", [
            'role_id' => $role->id,
        ]);

        $response->assertStatus(201);
    });

    test('prevents duplicate assignment', function () {
        $user = User::factory()->create();
        $role = Role::create(['slug' => 'admin', 'name' => 'Admin', 'level' => 100]);

        // First assignment succeeds
        $this->postJson("/api/admin/sso/users/{$user->id}/roles", [
            'role_id' => $role->id,
        ])->assertStatus(201);

        // Second identical assignment fails with 422
        $response = $this->postJson("/api/admin/sso/users/{$user->id}/roles", [
            'role_id' => $role->id,
        ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'DUPLICATE_ASSIGNMENT']);
    });

    test('allows different roles for different scopes', function () {
        $user = User::factory()->create();
        $role1 = Role::create(['slug' => 'manager-org1', 'name' => 'Manager Org1', 'level' => 50]);
        $role2 = Role::create(['slug' => 'manager-org2', 'name' => 'Manager Org2', 'level' => 50]);
        $org1 = (string) Str::uuid();
        $org2 = (string) Str::uuid();

        // Assign role1 to org1
        $this->postJson("/api/admin/sso/users/{$user->id}/roles", [
            'role_id' => $role1->id,
            'console_organization_id' => $org1,
        ])->assertStatus(201);

        // Assign role2 to org2
        $this->postJson("/api/admin/sso/users/{$user->id}/roles", [
            'role_id' => $role2->id,
            'console_organization_id' => $org2,
        ])->assertStatus(201);

        expect($user->getRoleAssignments())->toHaveCount(2);
    });

    test('returns 404 for non-existent user', function () {
        $role = Role::create(['slug' => 'admin', 'name' => 'Admin', 'level' => 100]);
        $fakeUserId = (string) Str::uuid();

        $response = $this->postJson("/api/admin/sso/users/{$fakeUserId}/roles", [
            'role_id' => $role->id,
        ]);

        $response->assertNotFound()
            ->assertJson(['error' => 'USER_NOT_FOUND']);
    });
});

// =============================================================================
// Sync Tests — PUT /api/admin/sso/users/{userId}/roles/sync
// =============================================================================

describe('PUT /api/admin/sso/users/{userId}/roles/sync', function () {
    test('replaces all role assignments', function () {
        $user = User::factory()->create();
        $oldRole = Role::create(['slug' => 'member', 'name' => 'Member', 'level' => 10]);
        $newRole = Role::create(['slug' => 'admin', 'name' => 'Admin', 'level' => 100]);

        // Assign old role
        $user->assignRole($oldRole);
        expect($user->getRoleAssignments())->toHaveCount(1);

        // Sync with new role only
        $response = $this->putJson("/api/admin/sso/users/{$user->id}/roles/sync", [
            'assignments' => [
                ['role_id' => $newRole->id],
            ],
        ]);

        $response->assertOk();

        $user->refresh();
        $assignments = $user->getRoleAssignments();
        expect($assignments)->toHaveCount(1);
        expect($assignments->first()->slug)->toBe('admin');
    });

    test('sync with empty array is rejected by validation', function () {
        $user = User::factory()->create();
        $role = Role::create(['slug' => 'admin', 'name' => 'Admin', 'level' => 100]);

        $user->assignRole($role);
        expect($user->getRoleAssignments())->toHaveCount(1);

        // Controller requires 'assignments' to be a non-empty array
        $response = $this->putJson("/api/admin/sso/users/{$user->id}/roles/sync", [
            'assignments' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['assignments']);
    });

    test('sync assigns multiple roles with different scopes', function () {
        $user = User::factory()->create();
        $role1 = Role::create(['slug' => 'admin', 'name' => 'Admin', 'level' => 100]);
        $role2 = Role::create(['slug' => 'manager', 'name' => 'Manager', 'level' => 50]);
        $organizationId = (string) Str::uuid();

        $response = $this->putJson("/api/admin/sso/users/{$user->id}/roles/sync", [
            'assignments' => [
                ['role_id' => $role1->id],
                ['role_id' => $role2->id, 'console_organization_id' => $organizationId],
            ],
        ]);

        $response->assertOk();

        $user->refresh();
        expect($user->getRoleAssignments())->toHaveCount(2);
    });

    test('sync validates assignments is required array', function () {
        $user = User::factory()->create();

        $response = $this->putJson("/api/admin/sso/users/{$user->id}/roles/sync", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['assignments']);
    });

    test('sync validates role_id exists for each assignment', function () {
        $user = User::factory()->create();
        $fakeRoleId = (string) Str::uuid();

        $response = $this->putJson("/api/admin/sso/users/{$user->id}/roles/sync", [
            'assignments' => [
                ['role_id' => $fakeRoleId],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['assignments.0.role_id']);
    });

    test('sync returns 404 for non-existent user', function () {
        $role = Role::create(['slug' => 'admin', 'name' => 'Admin', 'level' => 100]);
        $fakeUserId = (string) Str::uuid();

        $response = $this->putJson("/api/admin/sso/users/{$fakeUserId}/roles/sync", [
            'assignments' => [
                ['role_id' => $role->id],
            ],
        ]);

        $response->assertNotFound()
            ->assertJson(['error' => 'USER_NOT_FOUND']);
    });
});

// =============================================================================
// Destroy Tests — DELETE /api/admin/sso/users/{userId}/roles/{roleId}
// =============================================================================

describe('DELETE /api/admin/sso/users/{userId}/roles/{roleId}', function () {
    test('removes global role assignment', function () {
        $user = User::factory()->create();
        $role = Role::create(['slug' => 'admin', 'name' => 'Admin', 'level' => 100]);

        $user->assignRole($role);
        expect($user->getRoleAssignments())->toHaveCount(1);

        $response = $this->deleteJson("/api/admin/sso/users/{$user->id}/roles/{$role->id}");

        $response->assertOk()
            ->assertJson(['message' => 'Role removed successfully']);

        $user->refresh();
        expect($user->getRoleAssignments())->toHaveCount(0);
    });

    test('removes scoped role assignment with query parameters', function () {
        $user = User::factory()->create();
        $role = Role::create(['slug' => 'manager', 'name' => 'Manager', 'level' => 50]);
        $organizationId = (string) Str::uuid();

        $user->assignRole($role, $organizationId, null);
        expect($user->getRoleAssignments())->toHaveCount(1);

        $response = $this->deleteJson(
            "/api/admin/sso/users/{$user->id}/roles/{$role->id}?console_organization_id={$organizationId}"
        );

        $response->assertOk();

        $user->refresh();
        expect($user->getRoleAssignments())->toHaveCount(0);
    });

    test('returns 404 for non-assigned role', function () {
        $user = User::factory()->create();
        $role = Role::create(['slug' => 'admin', 'name' => 'Admin', 'level' => 100]);

        // Role exists but is not assigned to user
        $response = $this->deleteJson("/api/admin/sso/users/{$user->id}/roles/{$role->id}");

        $response->assertNotFound()
            ->assertJson(['error' => 'ASSIGNMENT_NOT_FOUND']);
    });

    test('returns 404 for non-existent user', function () {
        $role = Role::create(['slug' => 'admin', 'name' => 'Admin', 'level' => 100]);
        $fakeUserId = (string) Str::uuid();

        $response = $this->deleteJson("/api/admin/sso/users/{$fakeUserId}/roles/{$role->id}");

        $response->assertNotFound()
            ->assertJson(['error' => 'USER_NOT_FOUND']);
    });

    test('only removes the specific role leaving others intact', function () {
        $user = User::factory()->create();
        $role1 = Role::create(['slug' => 'manager', 'name' => 'Manager', 'level' => 50]);
        $role2 = Role::create(['slug' => 'staff', 'name' => 'Staff', 'level' => 10]);
        $org1 = (string) Str::uuid();
        $org2 = (string) Str::uuid();

        $user->assignRole($role1, $org1, null);
        $user->assignRole($role2, $org2, null);
        expect($user->getRoleAssignments())->toHaveCount(2);

        // Remove only role1 in org1
        $response = $this->deleteJson(
            "/api/admin/sso/users/{$user->id}/roles/{$role1->id}?console_organization_id={$org1}"
        );

        $response->assertOk();

        $user->refresh();
        $remaining = $user->getRoleAssignments();
        expect($remaining)->toHaveCount(1);
        expect($remaining->first()->slug)->toBe('staff');
    });

    test('removes branch-scoped assignment with both org and branch parameters', function () {
        $user = User::factory()->create();
        $role = Role::create(['slug' => 'staff', 'name' => 'Staff', 'level' => 10]);
        $organizationId = (string) Str::uuid();
        $branchId = (string) Str::uuid();

        $user->assignRole($role, $organizationId, $branchId);
        expect($user->getRoleAssignments())->toHaveCount(1);

        $response = $this->deleteJson(
            "/api/admin/sso/users/{$user->id}/roles/{$role->id}?console_organization_id={$organizationId}&console_branch_id={$branchId}"
        );

        $response->assertOk();

        $user->refresh();
        expect($user->getRoleAssignments())->toHaveCount(0);
    });
});
