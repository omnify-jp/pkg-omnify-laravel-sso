<?php

/**
 * RoleAdminController Feature Tests
 *
 * ロール管理コントローラーのテスト
 */

use Omnify\Core\Models\Permission;
use Omnify\Core\Models\Role;
use Omnify\Core\Tests\Fixtures\Models\User;

beforeEach(function () {
    // ロールとパーミッションのマイグレーションを実行
    $this->artisan('migrate', ['--database' => 'testing']);
});

// =============================================================================
// Index Tests - 一覧取得のテスト
// =============================================================================

test('index returns all roles ordered by level desc', function () {
    $admin = Role::create(['slug' => 'admin', 'name' => 'Admin', 'level' => 100]);
    $manager = Role::create(['slug' => 'manager', 'name' => 'Manager', 'level' => 50]);
    $member = Role::create(['slug' => 'member', 'name' => 'Member', 'level' => 10]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/admin/sso/roles');

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.slug', 'admin')
        ->assertJsonPath('data.1.slug', 'manager')
        ->assertJsonPath('data.2.slug', 'member');
});

test('index includes permissions_count', function () {
    $role = Role::create(['slug' => 'editor', 'name' => 'Editor', 'level' => 30]);
    $permission1 = Permission::create(['slug' => 'posts.create', 'name' => 'Create Posts']);
    $permission2 = Permission::create(['slug' => 'posts.edit', 'name' => 'Edit Posts']);
    $role->permissions()->attach([$permission1->id, $permission2->id]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/admin/sso/roles');

    $response->assertStatus(200)
        ->assertJsonPath('data.0.permissions_count', 2);
});

// =============================================================================
// Scope Filter Tests - スコープフィルターのテスト
// =============================================================================

test('index filters roles by scope=global returns only global roles', function () {
    $organizationId = \Illuminate\Support\Str::uuid()->toString();

    // Create global role (console_organization_id = null)
    Role::create(['slug' => 'global-admin', 'name' => 'Global Admin', 'level' => 100, 'console_organization_id' => null]);
    Role::create(['slug' => 'global-viewer', 'name' => 'Global Viewer', 'level' => 5, 'console_organization_id' => null]);

    // Create org-specific role
    Role::create(['slug' => 'org-manager', 'name' => 'Org Manager', 'level' => 50, 'console_organization_id' => $organizationId]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/admin/sso/roles?filter[scope]=global');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);

    // All returned roles should have null console_organization_id
    foreach ($response->json('data') as $role) {
        expect($role['console_organization_id'])->toBeNull();
    }
});

test('index filters roles by scope=org returns only organization roles', function () {
    $organizationId = \Illuminate\Support\Str::uuid()->toString();

    // Create global role
    Role::create(['slug' => 'global-admin', 'name' => 'Global Admin', 'level' => 100, 'console_organization_id' => null]);

    // Create org-specific roles
    Role::create(['slug' => 'org-manager', 'name' => 'Org Manager', 'level' => 50, 'console_organization_id' => $organizationId]);
    Role::create(['slug' => 'org-member', 'name' => 'Org Member', 'level' => 10, 'console_organization_id' => $organizationId]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/admin/sso/roles?filter[scope]=org', [
        'X-Organization-Id' => $organizationId,
    ]);

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);

    // All returned roles should have console_organization_id set
    foreach ($response->json('data') as $role) {
        expect($role['console_organization_id'])->not->toBeNull();
    }
});

test('index filters roles by filter[organization_id] returns roles for specific organization', function () {
    $organizationId1 = \Illuminate\Support\Str::uuid()->toString();
    $organizationId2 = \Illuminate\Support\Str::uuid()->toString();

    // Create roles for different orgs
    Role::create(['slug' => 'org1-manager', 'name' => 'Org1 Manager', 'level' => 50, 'console_organization_id' => $organizationId1]);
    Role::create(['slug' => 'org1-member', 'name' => 'Org1 Member', 'level' => 10, 'console_organization_id' => $organizationId1]);
    Role::create(['slug' => 'org2-manager', 'name' => 'Org2 Manager', 'level' => 50, 'console_organization_id' => $organizationId2]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson("/api/admin/sso/roles?filter[organization_id]={$organizationId1}");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);

    // All returned roles should belong to org1
    foreach ($response->json('data') as $role) {
        expect($role['console_organization_id'])->toBe($organizationId1);
    }
});

test('index scope=all returns both global and org roles', function () {
    $organizationId = \Illuminate\Support\Str::uuid()->toString();

    Role::create(['slug' => 'global-admin', 'name' => 'Global Admin', 'level' => 100, 'console_organization_id' => null]);
    Role::create(['slug' => 'org-manager', 'name' => 'Org Manager', 'level' => 50, 'console_organization_id' => $organizationId]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/admin/sso/roles?filter[scope]=all', [
        'X-Organization-Id' => $organizationId,
    ]);

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
});

test('index includes organization info for org-specific roles', function () {
    $organizationId = \Illuminate\Support\Str::uuid()->toString();

    // Create organization cache
    \Omnify\Core\Models\Organization::create([
        'console_organization_id' => $organizationId,
        'name' => 'Test Organization',
        'slug' => 'TEST-ORG',
    ]);

    Role::create(['slug' => 'org-manager', 'name' => 'Org Manager', 'level' => 50, 'console_organization_id' => $organizationId]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson("/api/admin/sso/roles?filter[organization_id]={$organizationId}");

    $response->assertStatus(200);
    expect($response->json('data.0.organization'))->not->toBeNull();
    expect($response->json('data.0.organization.name'))->toBe('Test Organization');
});

// =============================================================================
// Store Tests - 作成のテスト
// =============================================================================

test('store creates a new role', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/admin/sso/roles', [
        'slug' => 'reviewer',
        'name' => 'Reviewer',
        'level' => 25,
        'description' => 'Can review content',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.slug', 'reviewer')
        ->assertJsonPath('data.name', 'Reviewer')
        ->assertJsonPath('data.level', 25);

    $this->assertDatabaseHas('roles', [
        'slug' => 'reviewer',
        'name' => 'Reviewer',
    ]);
});

test('store validates required fields', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/admin/sso/roles', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['slug', 'name', 'level']);
});

test('store validates unique slug in same org', function () {
    // Create role with null console_organization_id (global)
    Role::create(['slug' => 'existing', 'name' => 'Existing', 'level' => 10, 'console_organization_id' => null]);
    $user = User::factory()->create();

    // Try to create another global role with same slug
    $response = $this->actingAs($user)->postJson('/api/admin/sso/roles', [
        'slug' => 'existing',
        'name' => 'Another',
        'level' => 20,
        'scope' => 'global',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'error' => 'DUPLICATE_ROLE',
        ]);
});

test('store validates level range', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/admin/sso/roles', [
        'slug' => 'test',
        'name' => 'Test',
        'level' => 150, // max is 100
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['level']);
});

// =============================================================================
// Store with Scope Tests - スコープ付きロール作成のテスト
// =============================================================================

test('store creates global role with scope=global', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/admin/sso/roles', [
        'slug' => 'global-reviewer',
        'name' => 'Global Reviewer',
        'level' => 25,
        'scope' => 'global',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.slug', 'global-reviewer')
        ->assertJsonPath('data.console_organization_id', null);

    $this->assertDatabaseHas('roles', [
        'slug' => 'global-reviewer',
        'console_organization_id' => null,
    ]);
});

test('store creates org-specific role with scope=org', function () {
    $organizationId = \Illuminate\Support\Str::uuid()->toString();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/admin/sso/roles', [
        'slug' => 'org-reviewer',
        'name' => 'Org Reviewer',
        'level' => 25,
        'scope' => 'org',
    ], [
        'X-Organization-Id' => $organizationId,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.slug', 'org-reviewer')
        ->assertJsonPath('data.console_organization_id', $organizationId);

    $this->assertDatabaseHas('roles', [
        'slug' => 'org-reviewer',
        'console_organization_id' => $organizationId,
    ]);
});

test('store creates org-specific role with explicit console_organization_id', function () {
    $organizationId = \Illuminate\Support\Str::uuid()->toString();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/admin/sso/roles', [
        'slug' => 'custom-org-role',
        'name' => 'Custom Org Role',
        'level' => 30,
        'console_organization_id' => $organizationId,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.console_organization_id', $organizationId);

    $this->assertDatabaseHas('roles', [
        'slug' => 'custom-org-role',
        'console_organization_id' => $organizationId,
    ]);
});

test('store allows same slug in different organizations', function () {
    $organizationId1 = \Illuminate\Support\Str::uuid()->toString();
    $organizationId2 = \Illuminate\Support\Str::uuid()->toString();
    $user = User::factory()->create();

    // Create role in org1
    $this->actingAs($user)->postJson('/api/admin/sso/roles', [
        'slug' => 'manager',
        'name' => 'Manager',
        'level' => 50,
        'console_organization_id' => $organizationId1,
    ])->assertStatus(201);

    // Create same slug in org2 - should succeed
    $response = $this->actingAs($user)->postJson('/api/admin/sso/roles', [
        'slug' => 'manager',
        'name' => 'Manager',
        'level' => 50,
        'console_organization_id' => $organizationId2,
    ]);

    $response->assertStatus(201);

    // Both roles should exist
    expect(Role::where('slug', 'manager')->count())->toBe(2);
});

test('store prevents duplicate slug in same organization', function () {
    $organizationId = \Illuminate\Support\Str::uuid()->toString();
    $user = User::factory()->create();

    // Create first role
    Role::create(['slug' => 'manager', 'name' => 'Manager', 'level' => 50, 'console_organization_id' => $organizationId]);

    // Try to create duplicate in same org
    $response = $this->actingAs($user)->postJson('/api/admin/sso/roles', [
        'slug' => 'manager',
        'name' => 'Another Manager',
        'level' => 40,
        'console_organization_id' => $organizationId,
    ]);

    $response->assertStatus(422);
});

test('store prevents duplicate global role slug', function () {
    $user = User::factory()->create();

    // Create first global role
    Role::create(['slug' => 'super-admin', 'name' => 'Super Admin', 'level' => 100, 'console_organization_id' => null]);

    // Try to create duplicate global role
    $response = $this->actingAs($user)->postJson('/api/admin/sso/roles', [
        'slug' => 'super-admin',
        'name' => 'Another Super Admin',
        'level' => 100,
        'scope' => 'global',
    ]);

    $response->assertStatus(422);
});

// =============================================================================
// Show Tests - 詳細取得のテスト
// =============================================================================

test('show returns role with permissions', function () {
    $role = Role::create(['slug' => 'admin', 'name' => 'Admin', 'level' => 100]);
    $permission = Permission::create(['slug' => 'users.manage', 'name' => 'Manage Users']);
    $role->permissions()->attach($permission->id);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson("/api/admin/sso/roles/{$role->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.slug', 'admin')
        ->assertJsonCount(1, 'data.permissions')
        ->assertJsonPath('data.permissions.0.slug', 'users.manage');
});

test('show returns 404 for non-existent role', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/admin/sso/roles/9999');

    $response->assertStatus(404);
});

// =============================================================================
// Update Tests - 更新のテスト
// =============================================================================

test('update modifies role', function () {
    $role = Role::create(['slug' => 'editor', 'name' => 'Editor', 'level' => 30]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->putJson("/api/admin/sso/roles/{$role->id}", [
        'name' => 'Senior Editor',
        'level' => 40,
        'description' => 'Updated description',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.name', 'Senior Editor')
        ->assertJsonPath('data.level', 40);

    $this->assertDatabaseHas('roles', [
        'id' => $role->id,
        'name' => 'Senior Editor',
        'level' => 40,
    ]);
});

test('update does not change slug', function () {
    $role = Role::create(['slug' => 'original', 'name' => 'Original', 'level' => 10]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->putJson("/api/admin/sso/roles/{$role->id}", [
        'slug' => 'changed',
        'name' => 'Changed',
    ]);

    $response->assertStatus(200);

    $this->assertDatabaseHas('roles', [
        'id' => $role->id,
        'slug' => 'original', // unchanged
    ]);
});

// =============================================================================
// Destroy Tests - 削除のテスト
// =============================================================================

test('destroy deletes role', function () {
    $role = Role::create(['slug' => 'deletable', 'name' => 'Deletable', 'level' => 5]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->deleteJson("/api/admin/sso/roles/{$role->id}");

    $response->assertStatus(204);

    $this->assertSoftDeleted('roles', ['id' => $role->id]);
});

test('destroy cannot delete system roles', function () {
    $admin = Role::create(['slug' => 'admin', 'name' => 'Admin', 'level' => 100]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->deleteJson("/api/admin/sso/roles/{$admin->id}");

    $response->assertStatus(422)
        ->assertJson([
            'error' => 'CANNOT_DELETE_SYSTEM_ROLE',
        ]);

    $this->assertDatabaseHas('roles', ['id' => $admin->id]);
});

test('destroy cannot delete manager role', function () {
    $manager = Role::create(['slug' => 'manager', 'name' => 'Manager', 'level' => 50]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->deleteJson("/api/admin/sso/roles/{$manager->id}");

    $response->assertStatus(422);
});

test('destroy cannot delete member role', function () {
    $member = Role::create(['slug' => 'member', 'name' => 'Member', 'level' => 10]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->deleteJson("/api/admin/sso/roles/{$member->id}");

    $response->assertStatus(422);
});

// =============================================================================
// Permissions Tests - パーミッション関連のテスト
// =============================================================================

test('permissions returns role with its permissions', function () {
    $role = Role::create(['slug' => 'editor', 'name' => 'Editor', 'level' => 30]);
    $permission1 = Permission::create(['slug' => 'posts.create', 'name' => 'Create Posts']);
    $permission2 = Permission::create(['slug' => 'posts.edit', 'name' => 'Edit Posts']);
    $role->permissions()->attach([$permission1->id, $permission2->id]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson("/api/admin/sso/roles/{$role->id}/permissions");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'role' => ['id', 'slug', 'name'],
            'permissions',
        ])
        ->assertJsonCount(2, 'permissions');
});

test('syncPermissions attaches new permissions', function () {
    $role = Role::create(['slug' => 'editor', 'name' => 'Editor', 'level' => 30]);
    $permission1 = Permission::create(['slug' => 'posts.create', 'name' => 'Create Posts']);
    $permission2 = Permission::create(['slug' => 'posts.edit', 'name' => 'Edit Posts']);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->putJson("/api/admin/sso/roles/{$role->id}/permissions", [
        'permissions' => [$permission1->id, $permission2->id],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('attached', 2)
        ->assertJsonPath('detached', 0);

    expect($role->permissions()->count())->toBe(2);
});

test('syncPermissions detaches removed permissions', function () {
    $role = Role::create(['slug' => 'editor', 'name' => 'Editor', 'level' => 30]);
    $permission1 = Permission::create(['slug' => 'posts.create', 'name' => 'Create Posts']);
    $permission2 = Permission::create(['slug' => 'posts.edit', 'name' => 'Edit Posts']);
    $role->permissions()->attach([$permission1->id, $permission2->id]);

    $user = User::factory()->create();

    // Only keep permission1
    $response = $this->actingAs($user)->putJson("/api/admin/sso/roles/{$role->id}/permissions", [
        'permissions' => [$permission1->id],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('attached', 0)
        ->assertJsonPath('detached', 1);

    expect($role->permissions()->count())->toBe(1);
    expect($role->permissions()->first()->slug)->toBe('posts.create');
});

test('syncPermissions accepts permission slugs', function () {
    $role = Role::create(['slug' => 'editor', 'name' => 'Editor', 'level' => 30]);
    Permission::create(['slug' => 'posts.create', 'name' => 'Create Posts']);
    Permission::create(['slug' => 'posts.edit', 'name' => 'Edit Posts']);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->putJson("/api/admin/sso/roles/{$role->id}/permissions", [
        'permissions' => ['posts.create', 'posts.edit'],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('attached', 2);

    expect($role->permissions()->count())->toBe(2);
});

test('syncPermissions accepts mixed IDs and slugs', function () {
    $role = Role::create(['slug' => 'editor', 'name' => 'Editor', 'level' => 30]);
    $permission1 = Permission::create(['slug' => 'posts.create', 'name' => 'Create Posts']);
    Permission::create(['slug' => 'posts.edit', 'name' => 'Edit Posts']);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->putJson("/api/admin/sso/roles/{$role->id}/permissions", [
        'permissions' => [$permission1->id, 'posts.edit'],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('attached', 2);

    expect($role->permissions()->count())->toBe(2);
});
