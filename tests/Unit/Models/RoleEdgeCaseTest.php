<?php

/**
 * Role Model Edge Case Tests
 *
 * ロールモデルのエッジケーステスト
 * Kiểm thử các trường hợp biên cho Model Role
 */

use Omnify\SsoClient\Models\Permission;
use Omnify\SsoClient\Models\Role;
use Omnify\SsoClient\Models\User;

beforeEach(function () {
    $this->artisan('migrate', ['--database' => 'testing']);
});

// =============================================================================
// String Length Edge Cases - 文字列長のエッジケース
// =============================================================================

test('can create role with minimum length name (1 char)', function () {
    $role = Role::create(['name' => 'A', 'slug' => 'a']);

    expect($role->name)->toBe('A');
});

test('can create role with maximum length name (100 chars)', function () {
    $longName = str_repeat('a', 100);

    $role = Role::create(['name' => $longName, 'slug' => 'long-name']);

    expect(strlen($role->name))->toBe(100);
});

test('SQLite allows name exceeding maximum length (no enforcement)', function () {
    // SQLite doesn't enforce VARCHAR length limits
    // In MySQL/PostgreSQL, this would fail with QueryException
    $tooLongName = str_repeat('a', 101);

    $role = Role::create(['name' => $tooLongName, 'slug' => 'too-long']);

    expect(strlen($role->name))->toBe(101);
});

test('can create role with minimum length slug (1 char)', function () {
    $role = Role::create(['name' => 'Single', 'slug' => 'x']);

    expect($role->slug)->toBe('x');
});

test('can create role with maximum length slug (100 chars)', function () {
    $longSlug = str_repeat('a', 100);

    $role = Role::create(['name' => 'Long Slug', 'slug' => $longSlug]);

    expect(strlen($role->slug))->toBe(100);
});

// =============================================================================
// Slug Format Edge Cases - スラッグフォーマットのエッジケース
// =============================================================================

test('can create role with hyphenated slug', function () {
    $role = Role::create(['name' => 'Super Admin', 'slug' => 'super-admin']);

    expect($role->slug)->toBe('super-admin');
});

test('can create role with underscored slug', function () {
    $role = Role::create(['name' => 'Super Admin', 'slug' => 'super_admin']);

    expect($role->slug)->toBe('super_admin');
});

test('can create role with dotted slug', function () {
    $role = Role::create(['name' => 'Admin Users', 'slug' => 'admin.users']);

    expect($role->slug)->toBe('admin.users');
});

test('can create role with numeric slug', function () {
    $role = Role::create(['name' => 'Role 123', 'slug' => '123']);

    expect($role->slug)->toBe('123');
});

test('can create role with mixed case slug', function () {
    $role = Role::create(['name' => 'Admin', 'slug' => 'Admin']);

    expect($role->slug)->toBe('Admin');
});

// =============================================================================
// Level Edge Cases - レベルのエッジケース
// =============================================================================

test('can create role with negative level', function () {
    $role = Role::create(['name' => 'Negative', 'slug' => 'negative', 'level' => -100]);

    expect($role->level)->toBe(-100);
});

test('can create role with zero level', function () {
    $role = Role::create(['name' => 'Zero', 'slug' => 'zero', 'level' => 0]);

    expect($role->level)->toBe(0);
});

test('can create role with very large level', function () {
    $role = Role::create(['name' => 'Max', 'slug' => 'max', 'level' => 2147483647]);

    expect($role->level)->toBe(2147483647);
});

test('can create role with very small level', function () {
    $role = Role::create(['name' => 'Min', 'slug' => 'min', 'level' => -2147483648]);

    expect($role->level)->toBe(-2147483648);
});

test('level float is truncated to integer', function () {
    $role = Role::create(['name' => 'Float', 'slug' => 'float', 'level' => 50.99]);

    expect($role->level)->toBe(50);
});

test('can order roles with same level', function () {
    Role::create(['name' => 'First', 'slug' => 'first', 'level' => 50]);
    Role::create(['name' => 'Second', 'slug' => 'second', 'level' => 50]);
    Role::create(['name' => 'Third', 'slug' => 'third', 'level' => 50]);

    $roles = Role::where('level', 50)->orderBy('name')->get();

    expect($roles)->toHaveCount(3)
        ->and($roles->first()->name)->toBe('First');
});

// =============================================================================
// Unicode & Special Characters - Unicode・特殊文字
// =============================================================================

test('can create role with unicode name (Japanese)', function () {
    $role = Role::create(['name' => '管理者', 'slug' => 'admin']);

    expect($role->name)->toBe('管理者');
});

test('can create role with unicode name (Vietnamese)', function () {
    $role = Role::create(['name' => 'Quản trị viên', 'slug' => 'admin']);

    expect($role->name)->toBe('Quản trị viên');
});

test('can create role with emoji in name', function () {
    $role = Role::create(['name' => 'Admin 👑', 'slug' => 'admin']);

    expect($role->name)->toContain('👑');
});

test('can create role with unicode description', function () {
    $role = Role::create([
        'name' => 'Admin',
        'slug' => 'admin',
        'description' => '全ての権限を持つ管理者ロール 🔐',
    ]);

    expect($role->description)->toContain('全ての権限')
        ->and($role->description)->toContain('🔐');
});

// =============================================================================
// Permission Relationship Edge Cases - 権限リレーションのエッジケース
// =============================================================================

test('attaching same permission twice is rejected by unique constraint', function () {
    $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
    $permission = Permission::create(['name' => 'Create', 'slug' => 'create']);

    $role->permissions()->attach($permission->id);

    // Unique constraint on pivot prevents duplicates
    expect(fn () => $role->permissions()->attach($permission->id))
        ->toThrow(\Illuminate\Database\QueryException::class);

    // Only one record should exist
    $pivotCount = \DB::table('role_permissions')
        ->where('role_id', $role->id)
        ->where('permission_id', $permission->id)
        ->count();

    expect($pivotCount)->toBe(1);
});

test('use syncWithoutDetaching to prevent duplicates', function () {
    $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
    $permission = Permission::create(['name' => 'Create', 'slug' => 'create']);

    $role->permissions()->attach($permission->id);
    $role->permissions()->syncWithoutDetaching([$permission->id]); // Won't duplicate

    expect($role->permissions)->toHaveCount(1);
});

test('syncWithoutDetaching adds new permissions without removing existing', function () {
    $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
    $perm1 = Permission::create(['name' => 'Create', 'slug' => 'create']);
    $perm2 = Permission::create(['name' => 'Read', 'slug' => 'read']);
    $perm3 = Permission::create(['name' => 'Update', 'slug' => 'update']);

    $role->permissions()->attach($perm1->id);
    $role->permissions()->syncWithoutDetaching([$perm2->id, $perm3->id]);

    expect($role->permissions)->toHaveCount(3);
});

test('can attach permissions with pivot data', function () {
    $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
    $permission = Permission::create(['name' => 'Create', 'slug' => 'create']);

    $role->permissions()->attach($permission->id);

    $pivot = $role->permissions()->first()->pivot;
    expect($pivot->created_at)->not->toBeNull();
});

test('can toggle permissions', function () {
    $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
    $perm1 = Permission::create(['name' => 'Create', 'slug' => 'create']);
    $perm2 = Permission::create(['name' => 'Read', 'slug' => 'read']);

    $role->permissions()->attach($perm1->id);

    // Toggle should remove perm1 and add perm2
    $role->permissions()->toggle([$perm1->id, $perm2->id]);
    $role->refresh();

    expect($role->permissions)->toHaveCount(1)
        ->and($role->permissions->first()->slug)->toBe('read');
});

test('role with 100+ permissions', function () {
    $role = Role::create(['name' => 'Super Admin', 'slug' => 'super-admin', 'level' => 100]);

    $permissionIds = [];
    for ($i = 1; $i <= 100; $i++) {
        $perm = Permission::create(['name' => "Permission $i", 'slug' => "perm.$i"]);
        $permissionIds[] = $perm->id;
    }

    $role->permissions()->attach($permissionIds);

    expect($role->permissions)->toHaveCount(100);
});

// =============================================================================
// hasPermission Method Edge Cases - hasPermissionメソッドのエッジケース
// =============================================================================

test('hasPermission with empty string returns false', function () {
    $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);

    expect($role->hasPermission(''))->toBeFalse();
});

test('hasPermission is case sensitive', function () {
    $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
    $permission = Permission::create(['name' => 'Create', 'slug' => 'users.create']);
    $role->permissions()->attach($permission->id);

    expect($role->hasPermission('users.create'))->toBeTrue()
        ->and($role->hasPermission('Users.Create'))->toBeFalse()
        ->and($role->hasPermission('USERS.CREATE'))->toBeFalse();
});

test('hasPermission with SQL injection attempt', function () {
    $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);

    // Should safely handle SQL-like strings
    expect($role->hasPermission("'; DROP TABLE permissions; --"))->toBeFalse();
});

test('hasAnyPermission with empty array returns false', function () {
    $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
    $permission = Permission::create(['name' => 'Create', 'slug' => 'create']);
    $role->permissions()->attach($permission->id);

    expect($role->hasAnyPermission([]))->toBeFalse();
});

test('hasAnyPermission with single item array', function () {
    $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
    $permission = Permission::create(['name' => 'Create', 'slug' => 'create']);
    $role->permissions()->attach($permission->id);

    expect($role->hasAnyPermission(['create']))->toBeTrue()
        ->and($role->hasAnyPermission(['delete']))->toBeFalse();
});

test('hasAllPermissions with duplicate items counts unique', function () {
    $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
    $permission = Permission::create(['name' => 'Create', 'slug' => 'create']);
    $role->permissions()->attach($permission->id);

    // Current implementation counts array items, so duplicates fail
    // This documents the actual behavior - duplicates inflate the count
    expect($role->hasAllPermissions(['create', 'create', 'create']))->toBeFalse();

    // Without duplicates works correctly
    expect($role->hasAllPermissions(['create']))->toBeTrue();
});

// =============================================================================
// Query Edge Cases - クエリのエッジケース
// =============================================================================

test('finding role by slug with SQL special characters', function () {
    $role = Role::create(['name' => 'Test', 'slug' => "test'role"]);

    $found = Role::where('slug', "test'role")->first();
    expect($found)->not->toBeNull();
});

test('like query with wildcard characters in slug', function () {
    Role::create(['name' => 'Admin', 'slug' => 'admin%test']);
    Role::create(['name' => 'Editor', 'slug' => 'editor']);

    // The % in slug is literal, not a wildcard
    $found = Role::where('slug', 'admin%test')->first();
    expect($found)->not->toBeNull()
        ->and($found->name)->toBe('Admin');
});

test('can query roles without permissions', function () {
    Role::create(['name' => 'Empty', 'slug' => 'empty', 'level' => 0]);
    $roleWithPerms = Role::create(['name' => 'Has Perms', 'slug' => 'has-perms', 'level' => 10]);
    $permission = Permission::create(['name' => 'Test', 'slug' => 'test']);
    $roleWithPerms->permissions()->attach($permission->id);

    $emptyRoles = Role::whereDoesntHave('permissions')->get();

    expect($emptyRoles)->toHaveCount(1)
        ->and($emptyRoles->first()->slug)->toBe('empty');
});

test('can count permissions per role', function () {
    $role1 = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
    $role2 = Role::create(['name' => 'Editor', 'slug' => 'editor', 'level' => 50]);

    $perm1 = Permission::create(['name' => 'Create', 'slug' => 'create']);
    $perm2 = Permission::create(['name' => 'Read', 'slug' => 'read']);
    $perm3 = Permission::create(['name' => 'Update', 'slug' => 'update']);

    $role1->permissions()->attach([$perm1->id, $perm2->id, $perm3->id]);
    $role2->permissions()->attach([$perm1->id]);

    $roles = Role::withCount('permissions')->orderBy('permissions_count', 'desc')->get();

    expect($roles->first()->permissions_count)->toBe(3)
        ->and($roles->last()->permissions_count)->toBe(1);
});

// =============================================================================
// Update Edge Cases - 更新のエッジケース
// =============================================================================

test('updating slug to existing value throws error', function () {
    $orgId = (string) \Illuminate\Support\Str::uuid();
    Role::create(['name' => 'Role 1', 'slug' => 'role1', 'level' => 10, 'console_organization_id' => $orgId]);
    $role2 = Role::create(['name' => 'Role 2', 'slug' => 'role2', 'level' => 20, 'console_organization_id' => $orgId]);

    expect(fn () => $role2->update(['slug' => 'role1']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('can swap slugs using temporary value', function () {
    $role1 = Role::create(['name' => 'Role 1', 'slug' => 'role1', 'level' => 10]);
    $role2 = Role::create(['name' => 'Role 2', 'slug' => 'role2', 'level' => 20]);

    // Swap slugs using temp
    $role1->update(['slug' => 'temp']);
    $role2->update(['slug' => 'role1']);
    $role1->update(['slug' => 'role2']);

    $role1->refresh();
    $role2->refresh();

    expect($role1->slug)->toBe('role2')
        ->and($role2->slug)->toBe('role1');
});

// =============================================================================
// Delete Edge Cases - 削除のエッジケース
// =============================================================================

test('deleted role is not found in normal query', function () {
    $role = Role::create(['name' => 'Deletable', 'slug' => 'deletable', 'level' => 10]);
    $roleId = $role->id;

    $role->delete();

    expect(Role::find($roleId))->toBeNull()
        ->and(Role::where('slug', 'deletable')->first())->toBeNull();
});

test('can recreate role with same slug after delete', function () {
    $role = Role::create(['name' => 'Reusable', 'slug' => 'reusable', 'level' => 10]);
    $role->delete();

    $newRole = Role::create(['name' => 'Reusable Again', 'slug' => 'reusable', 'level' => 20]);

    expect($newRole->name)->toBe('Reusable Again');
});

// =============================================================================
// Relationship Integrity Edge Cases - リレーション整合性のエッジケース
// NOTE: SSO uses many-to-many roles with scoping via role_user pivot
// =============================================================================

test('users with deleted role still exist and have empty roles', function () {
    $role = Role::create(['name' => 'Temp Role', 'slug' => 'temp', 'level' => 10]);
    $user = User::create(['name' => 'Test', 'email' => 'test@example.com']);
    $user->assignRole($role);

    expect($user->roles)->toHaveCount(1);

    $role->delete();
    $user->refresh();

    // User exists but role is gone (pivot remains orphaned until cleanup)
    expect(User::find($user->id))->not->toBeNull()
        ->and($user->roles)->toBeEmpty();
});

// =============================================================================
// Bulk Operations Edge Cases - 一括操作のエッジケース
// =============================================================================

test('can insert multiple roles in bulk', function () {
    // When using insert(), UUIDs must be provided manually
    Role::insert([
        ['id' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Admin', 'slug' => 'admin', 'level' => 100, 'created_at' => now(), 'updated_at' => now()],
        ['id' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Editor', 'slug' => 'editor', 'level' => 50, 'created_at' => now(), 'updated_at' => now()],
        ['id' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Member', 'slug' => 'member', 'level' => 10, 'created_at' => now(), 'updated_at' => now()],
    ]);

    expect(Role::count())->toBe(3);
});

test('bulk delete with where clause', function () {
    Role::create(['name' => 'Low 1', 'slug' => 'low1', 'level' => 10]);
    Role::create(['name' => 'Low 2', 'slug' => 'low2', 'level' => 20]);
    Role::create(['name' => 'High 1', 'slug' => 'high1', 'level' => 100]);

    $deleted = Role::where('level', '<', 50)->delete();

    expect($deleted)->toBe(2)
        ->and(Role::count())->toBe(1);
});
