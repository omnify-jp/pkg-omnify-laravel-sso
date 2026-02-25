<?php

/**
 * SSO Role Check Middleware Tests
 *
 * Tests for role-based access control using scoped role assignments.
 * Uses the new Branch-Level Permissions system (Option B - Scoped Role Assignments).
 *
 * @see DOCUMENTATION.md#branch-level-permissions
 */

use Illuminate\Support\Facades\Route;
use Omnify\SsoClient\Models\Branch;
use Omnify\SsoClient\Models\Role;
use Omnify\SsoClient\Models\User;
use Omnify\SsoClient\Services\OrganizationAccessService;

beforeEach(function () {
    // Fixed UUIDs for testing
    $this->organizationId = 'a1b2c3d4-e5f6-4789-abcd-ef0123456789';
    $this->tokyoBranchId = 'b1b2c3d4-e5f6-4789-abcd-ef0123456789';
    $this->osakaBranchId = 'c1b2c3d4-e5f6-4789-abcd-ef0123456789';

    // Role level configuration
    config(['omnify-auth.role_levels' => [
        'admin' => 100,
        'manager' => 50,
        'member' => 10,
    ]]);

    // Create roles matching config levels
    Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
    Role::create(['name' => 'Manager', 'slug' => 'manager', 'level' => 50]);
    Role::create(['name' => 'Member', 'slug' => 'member', 'level' => 10]);

    // Create branches for testing
    \Omnify\SsoClient\Models\Branch::create([
        'console_branch_id' => $this->tokyoBranchId,
        'console_organization_id' => $this->organizationId,
        'slug' => 'TKY',
        'name' => 'Tokyo Branch',
        'is_headquarters' => true,
    ]);
    \Omnify\SsoClient\Models\Branch::create([
        'console_branch_id' => $this->osakaBranchId,
        'console_organization_id' => $this->organizationId,
        'slug' => 'OSK',
        'name' => 'Osaka Branch',
        'is_headquarters' => false,
    ]);

    // Mock OrganizationAccessService for org validation
    // Note: service_role is legacy - actual role check uses scoped role assignments
    $organizationAccessService = \Mockery::mock(OrganizationAccessService::class);
    $organizationAccessService->shouldReceive('checkAccess')
        ->andReturn([
            'organization_id' => $this->organizationId,
            'organization_slug' => 'test-org',
            'organization_role' => 'member',
            'service_role' => 'member',
            'service_role_level' => 10,
        ]);
    $this->app->instance(OrganizationAccessService::class, $organizationAccessService);

    // Test routes requiring different role levels
    Route::middleware(['sso.auth', 'sso.organization', 'sso.role:admin'])
        ->get('/test-admin-only', fn () => response()->json(['message' => 'admin access granted']));

    Route::middleware(['sso.auth', 'sso.organization', 'sso.role:manager'])
        ->get('/test-manager-only', fn () => response()->json(['message' => 'manager access granted']));

    Route::middleware(['sso.auth', 'sso.organization', 'sso.role:member'])
        ->get('/test-member-only', fn () => response()->json(['message' => 'member access granted']));
});

// =============================================================================
// Basic Role Check Tests - 基本的なロールチェックテスト
// =============================================================================

test('sso.role:admin rejects users without any role', function () {
    $user = User::factory()->create();
    // No roles assigned

    $response = $this->actingAs($user)
        ->withHeaders(['X-Organization-Id' => 'test-org'])
        ->getJson('/test-admin-only');

    $response->assertStatus(403)
        ->assertJson([
            'error' => 'INSUFFICIENT_ROLE',
            'required_role' => 'admin',
            'current_level' => 0,
        ]);
});

test('sso.role:admin rejects member role users', function () {
    $user = User::factory()->create();
    $user->assignRole('member'); // Global member role

    $response = $this->actingAs($user)
        ->withHeaders(['X-Organization-Id' => 'test-org'])
        ->getJson('/test-admin-only');

    $response->assertStatus(403)
        ->assertJson([
            'error' => 'INSUFFICIENT_ROLE',
            'required_role' => 'admin',
            'current_level' => 10,
        ]);
});

test('sso.role:admin rejects manager role users', function () {
    $user = User::factory()->create();
    $user->assignRole('manager'); // Global manager role

    $response = $this->actingAs($user)
        ->withHeaders(['X-Organization-Id' => 'test-org'])
        ->getJson('/test-admin-only');

    $response->assertStatus(403)
        ->assertJson([
            'error' => 'INSUFFICIENT_ROLE',
            'required_role' => 'admin',
            'current_level' => 50,
        ]);
});

test('sso.role:admin allows admin role users', function () {
    $user = User::factory()->create();
    $user->assignRole('admin'); // Global admin role

    $response = $this->actingAs($user)
        ->withHeaders(['X-Organization-Id' => 'test-org'])
        ->getJson('/test-admin-only');

    $response->assertStatus(200)
        ->assertJson(['message' => 'admin access granted']);
});

test('sso.role:manager allows manager role users', function () {
    $user = User::factory()->create();
    $user->assignRole('manager');

    $response = $this->actingAs($user)
        ->withHeaders(['X-Organization-Id' => 'test-org'])
        ->getJson('/test-manager-only');

    $response->assertStatus(200)
        ->assertJson(['message' => 'manager access granted']);
});

test('sso.role:manager allows admin role users (higher role)', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $response = $this->actingAs($user)
        ->withHeaders(['X-Organization-Id' => 'test-org'])
        ->getJson('/test-manager-only');

    $response->assertStatus(200)
        ->assertJson(['message' => 'manager access granted']);
});

test('sso.role:member allows all authenticated users with role', function () {
    $user = User::factory()->create();
    $user->assignRole('member');

    $response = $this->actingAs($user)
        ->withHeaders(['X-Organization-Id' => 'test-org'])
        ->getJson('/test-member-only');

    $response->assertStatus(200)
        ->assertJson(['message' => 'member access granted']);
});

// =============================================================================
// Scoped Role Tests - スコープ付きロールテスト (Branch-Level Permissions)
// =============================================================================

test('org-wide role grants access within organization', function () {
    $user = User::factory()->create();
    $organizationId = $this->organizationId;

    // Assign admin role scoped to this org
    $user->assignRole('admin', $organizationId);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Organization-Id' => 'test-org'])
        ->getJson('/test-admin-only');

    $response->assertStatus(200)
        ->assertJson(['message' => 'admin access granted']);
});

test('branch-specific role grants access at that branch', function () {
    $user = User::factory()->create();
    $organizationId = $this->organizationId;
    $branchId = $this->tokyoBranchId;

    // Assign admin role scoped to specific branch
    $user->assignRole('admin', $organizationId, $branchId);

    $response = $this->actingAs($user)
        ->withHeaders([
            'X-Organization-Id' => 'test-org',
            'X-Branch-Id' => $branchId,
        ])
        ->getJson('/test-admin-only');

    $response->assertStatus(200)
        ->assertJson(['message' => 'admin access granted']);
});

test('global role grants access everywhere', function () {
    $user = User::factory()->create();

    // Assign global admin role (no org/branch scope)
    $user->assignRole('admin');

    // Should work without any org/branch context
    $response = $this->actingAs($user)
        ->withHeaders(['X-Organization-Id' => 'test-org'])
        ->getJson('/test-admin-only');

    $response->assertStatus(200)
        ->assertJson(['message' => 'admin access granted']);
});

test('user with multiple scoped roles uses highest level', function () {
    $user = User::factory()->create();
    $organizationId = $this->organizationId;

    // Assign member globally, manager in org
    $user->assignRole('member');
    $user->assignRole('manager', $organizationId);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Organization-Id' => 'test-org'])
        ->getJson('/test-manager-only');

    $response->assertStatus(200)
        ->assertJson(['message' => 'manager access granted']);
});

// =============================================================================
// Cross-Branch Permission Tests - クロスブランチ権限テスト
// =============================================================================

test('branch-specific role does not grant access to other branches', function () {
    $user = User::factory()->create();
    $organizationId = $this->organizationId;

    // Admin only at Tokyo branch
    $user->assignRole('admin', $organizationId, $this->tokyoBranchId);

    // Try to access with Osaka branch context - should fail
    $response = $this->actingAs($user)
        ->withHeaders([
            'X-Organization-Id' => 'test-org',
            'X-Branch-Id' => $this->osakaBranchId,
        ])
        ->getJson('/test-admin-only');

    $response->assertStatus(403)
        ->assertJson([
            'error' => 'INSUFFICIENT_ROLE',
            'current_level' => 0,
        ]);
});

test('org-wide role grants access to all branches in org', function () {
    $user = User::factory()->create();
    $organizationId = $this->organizationId;

    // Org-wide admin (no branch specified)
    $user->assignRole('admin', $organizationId);

    // Should work at any branch
    $response = $this->actingAs($user)
        ->withHeaders([
            'X-Organization-Id' => 'test-org',
            'X-Branch-Id' => $this->osakaBranchId,
        ])
        ->getJson('/test-admin-only');

    $response->assertStatus(200)
        ->assertJson(['message' => 'admin access granted']);
});

test('user can have different roles at different branches', function () {
    $user = User::factory()->create();
    $organizationId = $this->organizationId;

    // Admin at Tokyo, Member at Osaka
    $user->assignRole('admin', $organizationId, $this->tokyoBranchId);
    $user->assignRole('member', $organizationId, $this->osakaBranchId);

    // Access admin route at Tokyo - should work
    $response = $this->actingAs($user)
        ->withHeaders([
            'X-Organization-Id' => 'test-org',
            'X-Branch-Id' => $this->tokyoBranchId,
        ])
        ->getJson('/test-admin-only');

    $response->assertStatus(200);

    // Access admin route at Osaka - should fail (only member there)
    $response = $this->actingAs($user)
        ->withHeaders([
            'X-Organization-Id' => 'test-org',
            'X-Branch-Id' => $this->osakaBranchId,
        ])
        ->getJson('/test-admin-only');

    $response->assertStatus(403);
});

// =============================================================================
// HQ (Headquarters) Pattern Tests - 本社パターンテスト
// =============================================================================

test('HQ admin (org-wide) can access all branch resources', function () {
    $user = User::factory()->create();
    $organizationId = $this->organizationId;

    // HQ admin = org-wide admin (no branch restriction)
    $user->assignRole('admin', $organizationId);

    // Can access Tokyo branch
    $response = $this->actingAs($user)
        ->withHeaders([
            'X-Organization-Id' => 'test-org',
            'X-Branch-Id' => $this->tokyoBranchId,
        ])
        ->getJson('/test-admin-only');
    $response->assertStatus(200);

    // Can access Osaka branch
    $response = $this->actingAs($user)
        ->withHeaders([
            'X-Organization-Id' => 'test-org',
            'X-Branch-Id' => $this->osakaBranchId,
        ])
        ->getJson('/test-admin-only');
    $response->assertStatus(200);

    // Can access without branch context (HQ level)
    $response = $this->actingAs($user)
        ->withHeaders(['X-Organization-Id' => 'test-org'])
        ->getJson('/test-admin-only');
    $response->assertStatus(200);
});

test('branch staff cannot access HQ-only resources', function () {
    $user = User::factory()->create();
    $organizationId = $this->organizationId;

    // Staff only at Tokyo branch
    $user->assignRole('member', $organizationId, $this->tokyoBranchId);

    // Cannot access org-wide admin route (no branch context = HQ level)
    $response = $this->actingAs($user)
        ->withHeaders(['X-Organization-Id' => 'test-org'])
        ->getJson('/test-admin-only');

    $response->assertStatus(403);
});
