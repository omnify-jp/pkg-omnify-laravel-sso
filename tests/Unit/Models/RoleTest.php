<?php

/**
 * Role Model Unit Tests
 *
 * ロールモデルのユニットテスト
 * Kiểm thử đơn vị cho Model Role
 *
 * Updated for UUID primary keys
 */

use Illuminate\Support\Str;
use Omnify\Core\Models\Permission;
use Omnify\Core\Models\Role;
use Omnify\Core\Models\User;

beforeEach(function () {
    $this->artisan('migrate', ['--database' => 'testing']);
});

// =============================================================================
// Basic Model Tests - 基本モデルテスト
// =============================================================================

test('can create role with required fields', function () {
    $role = Role::create([
        'name' => 'Administrator',
        'slug' => 'admin',
    ]);

    expect($role)->toBeInstanceOf(Role::class)
        ->and($role->name)->toBe('Administrator')
        ->and($role->slug)->toBe('admin')
        ->and($role->id)->toBeString()
        ->and(Str::isUuid($role->id))->toBeTrue();
});

test('can create role with all fields', function () {
    $role = Role::create([
        'name' => 'Super Admin',
        'slug' => 'super-admin',
        'description' => 'Has all permissions',
        'level' => 100,
    ]);

    expect($role->name)->toBe('Super Admin')
        ->and($role->slug)->toBe('super-admin')
        ->and($role->description)->toBe('Has all permissions')
        ->and($role->level)->toBe(100);
});

test('role id is uuid', function () {
    $role = Role::create(['name' => 'Test', 'slug' => 'test']);

    expect($role->id)->toBeString()
        ->and(Str::isUuid($role->id))->toBeTrue();
});

test('slug must be unique within same organization', function () {
    $orgId = (string) Str::uuid();
    Role::create(['name' => 'Admin 1', 'slug' => 'admin', 'console_organization_id' => $orgId]);

    expect(fn () => Role::create(['name' => 'Admin 2', 'slug' => 'admin', 'console_organization_id' => $orgId]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('name must be unique within same organization', function () {
    $orgId = (string) Str::uuid();
    Role::create(['name' => 'Administrator', 'slug' => 'admin1', 'console_organization_id' => $orgId]);

    expect(fn () => Role::create(['name' => 'Administrator', 'slug' => 'admin2', 'console_organization_id' => $orgId]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('description can be null', function () {
    $role = Role::create(['name' => 'Basic', 'slug' => 'basic']);

    expect($role->description)->toBeNull();
});

test('level defaults to 0 when set', function () {
    $role = Role::create(['name' => 'Default', 'slug' => 'default', 'level' => 0]);

    expect($role->level)->toBe(0);
});

// =============================================================================
// Casting Tests - キャストテスト
// =============================================================================

test('level is cast to integer', function () {
    $role = Role::create([
        'name' => 'Test',
        'slug' => 'test',
        'level' => '50',
    ]);

    expect($role->level)->toBeInt()
        ->and($role->level)->toBe(50);
});

// =============================================================================
// Relationship Tests - リレーションシップテスト
// =============================================================================

test('role has many permissions', function () {
    $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
    $permission1 = Permission::create(['name' => 'Create Users', 'slug' => 'users.create']);
    $permission2 = Permission::create(['name' => 'Delete Users', 'slug' => 'users.delete']);

    $role->permissions()->attach([$permission1->id, $permission2->id]);

    expect($role->permissions)->toHaveCount(2);
});

test('permissions relationship includes timestamps', function () {
    $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
    $permission = Permission::create(['name' => 'Create Users', 'slug' => 'users.create']);

    $role->permissions()->attach($permission->id);

    $pivot = $role->permissions()->first()->pivot;
    expect($pivot->created_at)->not->toBeNull()
        ->and($pivot->updated_at)->not->toBeNull();
});

test('can sync permissions', function () {
    $role = Role::create(['name' => 'Editor', 'slug' => 'editor', 'level' => 50]);
    $perm1 = Permission::create(['name' => 'Create Posts', 'slug' => 'posts.create']);
    $perm2 = Permission::create(['name' => 'Edit Posts', 'slug' => 'posts.edit']);
    $perm3 = Permission::create(['name' => 'Delete Posts', 'slug' => 'posts.delete']);

    $role->permissions()->attach([$perm1->id, $perm2->id]);
    expect($role->permissions)->toHaveCount(2);

    $role->permissions()->sync([$perm2->id, $perm3->id]);
    $role->refresh();

    expect($role->permissions)->toHaveCount(2)
        ->and($role->permissions->pluck('slug')->toArray())->toContain('posts.edit', 'posts.delete')
        ->and($role->permissions->pluck('slug')->toArray())->not->toContain('posts.create');
});

test('can detach all permissions', function () {
    $role = Role::create(['name' => 'Editor', 'slug' => 'editor', 'level' => 50]);
    $perm1 = Permission::create(['name' => 'Create Posts', 'slug' => 'posts.create']);
    $perm2 = Permission::create(['name' => 'Edit Posts', 'slug' => 'posts.edit']);

    $role->permissions()->attach([$perm1->id, $perm2->id]);
    expect($role->permissions)->toHaveCount(2);

    $role->permissions()->detach();
    $role->refresh();

    expect($role->permissions)->toHaveCount(0);
});

test('role has many users (ManyToMany)', function () {
    $role = Role::create(['name' => 'Member', 'slug' => 'member', 'level' => 10]);

    $user1 = User::create(['name' => 'User 1', 'email' => 'user1@test.com']);
    $user2 = User::create(['name' => 'User 2', 'email' => 'user2@test.com']);

    $user1->roles()->attach($role->id);
    $user2->roles()->attach($role->id);

    $role->refresh();
    $usersWithRole = User::whereHas('roles', fn ($q) => $q->where('roles.id', $role->id))->get();

    expect($usersWithRole)->toHaveCount(2);
});

// =============================================================================
// Permission Check Methods Tests - 権限チェックメソッドテスト
// =============================================================================

test('hasPermission returns true when role has permission', function () {
    $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
    $permission = Permission::create(['name' => 'Create Users', 'slug' => 'users.create']);
    $role->permissions()->attach($permission->id);

    expect($role->hasPermission('users.create'))->toBeTrue();
});

test('hasPermission returns false when role does not have permission', function () {
    $role = Role::create(['name' => 'Member', 'slug' => 'member', 'level' => 10]);

    expect($role->hasPermission('users.create'))->toBeFalse();
});

test('hasPermission returns false for non-existent permission', function () {
    $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
    $permission = Permission::create(['name' => 'Create Users', 'slug' => 'users.create']);
    $role->permissions()->attach($permission->id);

    expect($role->hasPermission('non.existent'))->toBeFalse();
});

test('hasAnyPermission returns true when role has at least one permission', function () {
    $role = Role::create(['name' => 'Editor', 'slug' => 'editor', 'level' => 50]);
    $permission = Permission::create(['name' => 'Create Posts', 'slug' => 'posts.create']);
    $role->permissions()->attach($permission->id);

    expect($role->hasAnyPermission(['posts.create', 'posts.delete']))->toBeTrue();
});

test('hasAnyPermission returns false when role has none of the permissions', function () {
    $role = Role::create(['name' => 'Member', 'slug' => 'member', 'level' => 10]);

    expect($role->hasAnyPermission(['posts.create', 'posts.delete']))->toBeFalse();
});

test('hasAllPermissions returns true when role has all permissions', function () {
    $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
    $perm1 = Permission::create(['name' => 'Create Posts', 'slug' => 'posts.create']);
    $perm2 = Permission::create(['name' => 'Delete Posts', 'slug' => 'posts.delete']);
    $role->permissions()->attach([$perm1->id, $perm2->id]);

    expect($role->hasAllPermissions(['posts.create', 'posts.delete']))->toBeTrue();
});

test('hasAllPermissions returns false when role is missing some permissions', function () {
    $role = Role::create(['name' => 'Editor', 'slug' => 'editor', 'level' => 50]);
    $permission = Permission::create(['name' => 'Create Posts', 'slug' => 'posts.create']);
    Permission::create(['name' => 'Delete Posts', 'slug' => 'posts.delete']);
    $role->permissions()->attach($permission->id);

    expect($role->hasAllPermissions(['posts.create', 'posts.delete']))->toBeFalse();
});

test('hasAllPermissions returns true for empty permission array', function () {
    $role = Role::create(['name' => 'Test', 'slug' => 'test', 'level' => 0]);

    expect($role->hasAllPermissions([]))->toBeTrue();
});

// =============================================================================
// Query Tests - クエリテスト
// =============================================================================

test('can find role by slug', function () {
    Role::create(['name' => 'Administrator', 'slug' => 'admin', 'level' => 100]);

    $found = Role::where('slug', 'admin')->first();

    expect($found)->not->toBeNull()
        ->and($found->name)->toBe('Administrator');
});

test('can order roles by level', function () {
    Role::create(['name' => 'Member', 'slug' => 'member', 'level' => 10]);
    Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
    Role::create(['name' => 'Editor', 'slug' => 'editor', 'level' => 50]);

    $roles = Role::orderBy('level', 'desc')->get();

    expect($roles->first()->slug)->toBe('admin')
        ->and($roles->last()->slug)->toBe('member');
});

test('can filter roles by minimum level', function () {
    Role::create(['name' => 'Member', 'slug' => 'member', 'level' => 10]);
    Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
    Role::create(['name' => 'Editor', 'slug' => 'editor', 'level' => 50]);

    $highLevelRoles = Role::where('level', '>=', 50)->get();

    expect($highLevelRoles)->toHaveCount(2);
});

test('can search roles with permission', function () {
    $role1 = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
    $role2 = Role::create(['name' => 'Editor', 'slug' => 'editor', 'level' => 50]);
    Role::create(['name' => 'Member', 'slug' => 'member', 'level' => 10]);

    $permission = Permission::create(['name' => 'Create Posts', 'slug' => 'posts.create']);
    $role1->permissions()->attach($permission->id);
    $role2->permissions()->attach($permission->id);

    $rolesWithPermission = Role::whereHas('permissions', function ($query) {
        $query->where('slug', 'posts.create');
    })->get();

    expect($rolesWithPermission)->toHaveCount(2);
});

// =============================================================================
// Update & Delete Tests - 更新・削除テスト
// =============================================================================

test('can update role', function () {
    $role = Role::create(['name' => 'Old Name', 'slug' => 'old-slug', 'level' => 10]);

    $role->update([
        'name' => 'New Name',
        'slug' => 'new-slug',
        'level' => 50,
    ]);

    $role->refresh();

    expect($role->name)->toBe('New Name')
        ->and($role->slug)->toBe('new-slug')
        ->and($role->level)->toBe(50);
});

test('can delete role', function () {
    $role = Role::create(['name' => 'Deletable', 'slug' => 'deletable', 'level' => 10]);
    $roleId = $role->id;

    $role->delete();

    expect(Role::find($roleId))->toBeNull();
});

// =============================================================================
// Timestamp Tests - タイムスタンプテスト
// =============================================================================

test('timestamps are automatically set', function () {
    $role = Role::create(['name' => 'Test', 'slug' => 'test', 'level' => 10]);

    expect($role->created_at)->not->toBeNull()
        ->and($role->updated_at)->not->toBeNull()
        ->and($role->created_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

// =============================================================================
// Factory Tests - ファクトリーテスト
// =============================================================================

test('factory creates valid role', function () {
    $role = Role::factory()->create();

    expect($role)->toBeInstanceOf(Role::class)
        ->and($role->id)->toBeString()
        ->and(Str::isUuid($role->id))->toBeTrue()
        ->and($role->name)->toBeString()
        ->and($role->slug)->toBeString();
});
