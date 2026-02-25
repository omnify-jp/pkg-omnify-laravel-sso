<?php

/**
 * SsoReadOnlyController Feature Tests
 *
 * 読み取り専用コントローラーのテスト
 * Tests for roles and permissions read-only endpoints
 */

use Illuminate\Support\Str;
use Omnify\SsoClient\Models\Permission;
use Omnify\SsoClient\Models\Role;
use Omnify\SsoClient\Models\User;

beforeEach(function () {
    $this->artisan('migrate', ['--database' => 'testing']);
});

// =============================================================================
// Roles Endpoint Tests - ロールエンドポイントテスト
// =============================================================================

test('roles endpoint requires authentication', function () {
    $response = $this->getJson('/api/sso/roles');

    $response->assertStatus(401);
});

test('roles endpoint returns all roles', function () {
    $user = User::factory()->create();

    Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
    Role::create(['name' => 'Editor', 'slug' => 'editor', 'level' => 50]);
    Role::create(['name' => 'Member', 'slug' => 'member', 'level' => 10]);

    $this->actingAs($user);
    $response = $this->getJson('/api/sso/roles');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'slug', 'level', 'permissions_count'],
            ],
        ])
        ->assertJsonCount(3, 'data');
});

test('roles are ordered by level descending', function () {
    $user = User::factory()->create();

    Role::create(['name' => 'Member', 'slug' => 'member', 'level' => 10]);
    Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
    Role::create(['name' => 'Editor', 'slug' => 'editor', 'level' => 50]);

    $this->actingAs($user);
    $response = $this->getJson('/api/sso/roles');

    $response->assertStatus(200);

    $slugs = collect($response->json('data'))->pluck('slug')->toArray();
    expect($slugs)->toBe(['admin', 'editor', 'member']);
});

// =============================================================================
// Single Role Endpoint Tests - 単一ロールエンドポイントテスト
// =============================================================================

test('single role endpoint requires authentication', function () {
    $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);

    $response = $this->getJson("/api/sso/roles/{$role->id}");

    $response->assertStatus(401);
});

test('single role endpoint returns role with permissions', function () {
    $user = User::factory()->create();

    $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
    $perm1 = Permission::create(['name' => 'Create Users', 'slug' => 'users.create']);
    $perm2 = Permission::create(['name' => 'Delete Users', 'slug' => 'users.delete']);
    $role->permissions()->attach([$perm1->id, $perm2->id]);

    $this->actingAs($user);
    $response = $this->getJson("/api/sso/roles/{$role->id}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id', 'name', 'slug', 'level', 'permissions',
            ],
        ])
        ->assertJsonPath('data.slug', 'admin')
        ->assertJsonCount(2, 'data.permissions');
});

test('single role endpoint returns 404 for non-existent role', function () {
    $user = User::factory()->create();
    $fakeUuid = (string) Str::uuid();

    $this->actingAs($user);
    $response = $this->getJson("/api/sso/roles/{$fakeUuid}");

    $response->assertStatus(404);
});

// =============================================================================
// Permissions Endpoint Tests - 権限エンドポイントテスト
// =============================================================================

test('permissions endpoint requires authentication', function () {
    $response = $this->getJson('/api/sso/permissions');

    $response->assertStatus(401);
});

test('permissions endpoint returns all permissions', function () {
    $user = User::factory()->create();

    Permission::create(['name' => 'Create Users', 'slug' => 'users.create', 'group' => 'users']);
    Permission::create(['name' => 'Delete Users', 'slug' => 'users.delete', 'group' => 'users']);
    Permission::create(['name' => 'View Reports', 'slug' => 'reports.view', 'group' => 'reports']);

    $this->actingAs($user);
    $response = $this->getJson('/api/sso/permissions');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'slug', 'group'],
            ],
            'groups',
        ])
        ->assertJsonCount(3, 'data');
});

test('permissions can be filtered by group', function () {
    $user = User::factory()->create();

    Permission::create(['name' => 'Create Users', 'slug' => 'users.create', 'group' => 'users']);
    Permission::create(['name' => 'Delete Users', 'slug' => 'users.delete', 'group' => 'users']);
    Permission::create(['name' => 'View Reports', 'slug' => 'reports.view', 'group' => 'reports']);

    $this->actingAs($user);
    $response = $this->getJson('/api/sso/permissions?group=users');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

test('permissions can be searched by slug', function () {
    $user = User::factory()->create();

    Permission::create(['name' => 'Create Users', 'slug' => 'users.create', 'group' => 'users']);
    Permission::create(['name' => 'Delete Users', 'slug' => 'users.delete', 'group' => 'users']);
    Permission::create(['name' => 'View Reports', 'slug' => 'reports.view', 'group' => 'reports']);

    $this->actingAs($user);
    $response = $this->getJson('/api/sso/permissions?search=users');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

test('permissions can be returned grouped', function () {
    $user = User::factory()->create();

    Permission::create(['name' => 'Create Users', 'slug' => 'users.create', 'group' => 'users']);
    Permission::create(['name' => 'Delete Users', 'slug' => 'users.delete', 'group' => 'users']);
    Permission::create(['name' => 'View Reports', 'slug' => 'reports.view', 'group' => 'reports']);

    $this->actingAs($user);
    $response = $this->getJson('/api/sso/permissions?grouped=true');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'users' => [],
            'reports' => [],
        ]);
});

// =============================================================================
// Permission Matrix Endpoint Tests - 権限マトリックスエンドポイントテスト
// =============================================================================

test('permission matrix endpoint requires authentication', function () {
    $response = $this->getJson('/api/sso/permission-matrix');

    $response->assertStatus(401);
});

test('permission matrix endpoint returns roles and permissions mapping', function () {
    $user = User::factory()->create();

    $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
    $memberRole = Role::create(['name' => 'Member', 'slug' => 'member', 'level' => 10]);

    $perm1 = Permission::create(['name' => 'Create Users', 'slug' => 'users.create', 'group' => 'users']);
    $perm2 = Permission::create(['name' => 'View Reports', 'slug' => 'reports.view', 'group' => 'reports']);

    $adminRole->permissions()->attach([$perm1->id, $perm2->id]);
    $memberRole->permissions()->attach([$perm2->id]);

    $this->actingAs($user);
    $response = $this->getJson('/api/sso/permission-matrix');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'roles',
            'permissions',
            'matrix',
        ])
        ->assertJsonPath('matrix.admin', ['users.create', 'reports.view'])
        ->assertJsonPath('matrix.member', ['reports.view']);
});
