<?php

/**
 * PermissionAdminController Feature Tests
 *
 * パーミッション管理コントローラーのテスト
 */

use Omnify\SsoClient\Models\Permission;
use Omnify\SsoClient\Models\Role;
use Omnify\SsoClient\Tests\Fixtures\Models\User;

beforeEach(function () {
    $this->artisan('migrate', ['--database' => 'testing']);
});

// =============================================================================
// Index Tests - 一覧取得のテスト
// =============================================================================

test('index returns all permissions', function () {
    Permission::create(['slug' => 'posts.create', 'name' => 'Create Posts', 'group' => 'posts']);
    Permission::create(['slug' => 'posts.edit', 'name' => 'Edit Posts', 'group' => 'posts']);
    Permission::create(['slug' => 'users.view', 'name' => 'View Users', 'group' => 'users']);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/admin/sso/permissions');

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [['id', 'slug', 'name', 'group']],
            'groups',
        ]);
});

test('index filters by group', function () {
    Permission::create(['slug' => 'posts.create', 'name' => 'Create Posts', 'group' => 'posts']);
    Permission::create(['slug' => 'posts.edit', 'name' => 'Edit Posts', 'group' => 'posts']);
    Permission::create(['slug' => 'users.view', 'name' => 'View Users', 'group' => 'users']);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/admin/sso/permissions?group=posts');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

test('index searches by slug and name', function () {
    Permission::create(['slug' => 'posts.create', 'name' => 'Create Posts', 'group' => 'posts']);
    Permission::create(['slug' => 'posts.edit', 'name' => 'Edit Posts', 'group' => 'posts']);
    Permission::create(['slug' => 'users.view', 'name' => 'View Users', 'group' => 'users']);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/admin/sso/permissions?search=create');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'posts.create');
});

test('index returns grouped permissions when requested', function () {
    Permission::create(['slug' => 'posts.create', 'name' => 'Create Posts', 'group' => 'posts']);
    Permission::create(['slug' => 'posts.edit', 'name' => 'Edit Posts', 'group' => 'posts']);
    Permission::create(['slug' => 'users.view', 'name' => 'View Users', 'group' => 'users']);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/admin/sso/permissions?grouped=true');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'posts',
            'users',
        ]);
});

test('index includes roles_count', function () {
    $permission = Permission::create(['slug' => 'posts.create', 'name' => 'Create Posts']);
    $role1 = Role::create(['slug' => 'admin', 'name' => 'Admin', 'level' => 100]);
    $role2 = Role::create(['slug' => 'editor', 'name' => 'Editor', 'level' => 50]);
    $permission->roles()->attach([$role1->id, $role2->id]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/admin/sso/permissions');

    $response->assertStatus(200)
        ->assertJsonPath('data.0.roles_count', 2);
});

// =============================================================================
// Store Tests - 作成のテスト
// =============================================================================

test('store creates a new permission', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/admin/sso/permissions', [
        'slug' => 'reports.export',
        'name' => 'Export Reports',
        'group' => 'reports',
        'description' => 'Can export reports',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.slug', 'reports.export')
        ->assertJsonPath('data.name', 'Export Reports')
        ->assertJsonPath('data.group', 'reports');

    $this->assertDatabaseHas('permissions', [
        'slug' => 'reports.export',
    ]);
});

test('store validates required fields', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/admin/sso/permissions', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['slug', 'name']);
});

test('store validates unique slug', function () {
    Permission::create(['slug' => 'existing', 'name' => 'Existing']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/admin/sso/permissions', [
        'slug' => 'existing',
        'name' => 'Another',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['slug']);
});

// =============================================================================
// Show Tests - 詳細取得のテスト
// =============================================================================

test('show returns permission with roles', function () {
    $permission = Permission::create(['slug' => 'posts.create', 'name' => 'Create Posts']);
    $role = Role::create(['slug' => 'editor', 'name' => 'Editor', 'level' => 50]);
    $permission->roles()->attach($role->id);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson("/api/admin/sso/permissions/{$permission->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.slug', 'posts.create')
        ->assertJsonCount(1, 'data.roles');
});

test('show returns 404 for non-existent permission', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/admin/sso/permissions/9999');

    $response->assertStatus(404);
});

// =============================================================================
// Update Tests - 更新のテスト
// =============================================================================

test('update modifies permission', function () {
    $permission = Permission::create(['slug' => 'posts.create', 'name' => 'Create Posts', 'group' => 'posts']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->putJson("/api/admin/sso/permissions/{$permission->id}", [
        'name' => 'Create New Posts',
        'group' => 'content',
        'description' => 'Updated description',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.name', 'Create New Posts')
        ->assertJsonPath('data.group', 'content');

    $this->assertDatabaseHas('permissions', [
        'id' => $permission->id,
        'name' => 'Create New Posts',
        'group' => 'content',
    ]);
});

test('update does not change slug', function () {
    $permission = Permission::create(['slug' => 'original', 'name' => 'Original']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->putJson("/api/admin/sso/permissions/{$permission->id}", [
        'slug' => 'changed',
        'name' => 'Changed',
    ]);

    $response->assertStatus(200);

    $this->assertDatabaseHas('permissions', [
        'id' => $permission->id,
        'slug' => 'original', // unchanged
    ]);
});

// =============================================================================
// Destroy Tests - 削除のテスト
// =============================================================================

test('destroy deletes permission', function () {
    $permission = Permission::create(['slug' => 'deletable', 'name' => 'Deletable']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->deleteJson("/api/admin/sso/permissions/{$permission->id}");

    $response->assertStatus(204);

    $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);
});

test('destroy detaches permission from roles', function () {
    $permission = Permission::create(['slug' => 'deletable', 'name' => 'Deletable']);
    $role = Role::create(['slug' => 'editor', 'name' => 'Editor', 'level' => 50]);
    $role->permissions()->attach($permission->id);

    expect($role->permissions()->count())->toBe(1);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->deleteJson("/api/admin/sso/permissions/{$permission->id}");

    $response->assertStatus(204);

    expect($role->fresh()->permissions()->count())->toBe(0);
});

// =============================================================================
// Matrix Tests - マトリクスのテスト
// =============================================================================

test('matrix returns roles and permissions matrix', function () {
    $admin = Role::create(['slug' => 'admin', 'name' => 'Admin', 'level' => 100]);
    $editor = Role::create(['slug' => 'editor', 'name' => 'Editor', 'level' => 50]);

    $permission1 = Permission::create(['slug' => 'posts.create', 'name' => 'Create Posts', 'group' => 'posts']);
    $permission2 = Permission::create(['slug' => 'posts.edit', 'name' => 'Edit Posts', 'group' => 'posts']);
    $permission3 = Permission::create(['slug' => 'users.view', 'name' => 'View Users', 'group' => 'users']);

    $admin->permissions()->attach([$permission1->id, $permission2->id, $permission3->id]);
    $editor->permissions()->attach([$permission1->id, $permission2->id]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/admin/sso/permission-matrix');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'roles',
            'permissions',
            'matrix',
        ])
        ->assertJsonCount(2, 'roles')
        ->assertJsonPath('matrix.admin', ['posts.create', 'posts.edit', 'users.view'])
        ->assertJsonPath('matrix.editor', ['posts.create', 'posts.edit']);
});
