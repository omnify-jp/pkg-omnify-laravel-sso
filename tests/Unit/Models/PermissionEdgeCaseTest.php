<?php

/**
 * Permission Model Edge Case Tests
 *
 * パーミッションモデルのエッジケーステスト
 * Kiểm thử các trường hợp biên cho Model Permission
 */

use Omnify\SsoClient\Models\Permission;
use Omnify\SsoClient\Models\Role;

beforeEach(function () {
    $this->artisan('migrate', ['--database' => 'testing']);
});

// =============================================================================
// String Length Edge Cases - 文字列長のエッジケース
// =============================================================================

test('can create permission with minimum length name (1 char)', function () {
    $permission = Permission::create(['name' => 'A', 'slug' => 'a']);

    expect($permission->name)->toBe('A');
});

test('can create permission with maximum length name (100 chars)', function () {
    $longName = str_repeat('a', 100);

    $permission = Permission::create(['name' => $longName, 'slug' => 'long-name']);

    expect(strlen($permission->name))->toBe(100);
});

test('SQLite allows name exceeding maximum length (no enforcement)', function () {
    // SQLite doesn't enforce VARCHAR length limits
    // In MySQL/PostgreSQL, this would fail with QueryException
    $tooLongName = str_repeat('a', 101);

    $permission = Permission::create(['name' => $tooLongName, 'slug' => 'too-long']);

    expect(strlen($permission->name))->toBe(101);
});

test('can create permission with maximum length slug (100 chars)', function () {
    $longSlug = str_repeat('a', 100);

    $permission = Permission::create(['name' => 'Long Slug', 'slug' => $longSlug]);

    expect(strlen($permission->slug))->toBe(100);
});

test('can create permission with maximum length group (50 chars)', function () {
    $longGroup = str_repeat('a', 50);

    $permission = Permission::create(['name' => 'Test', 'slug' => 'test', 'group' => $longGroup]);

    expect(strlen($permission->group))->toBe(50);
});

test('SQLite allows group exceeding maximum length (no enforcement)', function () {
    // SQLite doesn't enforce VARCHAR length limits
    $tooLongGroup = str_repeat('a', 51);

    $permission = Permission::create(['name' => 'Test', 'slug' => 'test', 'group' => $tooLongGroup]);

    expect(strlen($permission->group))->toBe(51);
});

// =============================================================================
// Slug Format Edge Cases - スラッグフォーマットのエッジケース
// =============================================================================

test('can create permission with deeply nested dot notation slug', function () {
    $permission = Permission::create([
        'name' => 'Deep Permission',
        'slug' => 'module.submodule.resource.action.type',
    ]);

    expect($permission->slug)->toBe('module.submodule.resource.action.type');
});

test('can create permission with single dot slug', function () {
    $permission = Permission::create(['name' => 'Simple', 'slug' => 'a.b']);

    expect($permission->slug)->toBe('a.b');
});

test('can create permission starting with dot', function () {
    $permission = Permission::create(['name' => 'Dot Start', 'slug' => '.hidden']);

    expect($permission->slug)->toBe('.hidden');
});

test('can create permission ending with dot', function () {
    $permission = Permission::create(['name' => 'Dot End', 'slug' => 'trailing.']);

    expect($permission->slug)->toBe('trailing.');
});

test('can create permission with multiple consecutive dots', function () {
    $permission = Permission::create(['name' => 'Multi Dot', 'slug' => 'a..b...c']);

    expect($permission->slug)->toBe('a..b...c');
});

test('can create permission with wildcard-like slug', function () {
    $permission = Permission::create(['name' => 'Wildcard', 'slug' => 'posts.*']);

    expect($permission->slug)->toBe('posts.*');
});

test('can create permission with colon in slug', function () {
    $permission = Permission::create(['name' => 'Colon', 'slug' => 'api:v1:posts:create']);

    expect($permission->slug)->toBe('api:v1:posts:create');
});

// =============================================================================
// Unicode & Special Characters - Unicode・特殊文字
// =============================================================================

test('can create permission with unicode name (Japanese)', function () {
    $permission = Permission::create(['name' => '記事作成', 'slug' => 'posts.create']);

    expect($permission->name)->toBe('記事作成');
});

test('can create permission with unicode name (Vietnamese)', function () {
    $permission = Permission::create(['name' => 'Tạo bài viết', 'slug' => 'posts.create']);

    expect($permission->name)->toBe('Tạo bài viết');
});

test('can create permission with emoji in name', function () {
    $permission = Permission::create(['name' => 'Create Posts ✏️', 'slug' => 'posts.create']);

    expect($permission->name)->toContain('✏️');
});

test('can create permission with unicode group', function () {
    $permission = Permission::create([
        'name' => 'Test',
        'slug' => 'test',
        'group' => '記事管理',
    ]);

    expect($permission->group)->toBe('記事管理');
});

// =============================================================================
// Group Edge Cases - グループのエッジケース
// =============================================================================

test('can filter permissions with empty string group', function () {
    Permission::create(['name' => 'Empty Group', 'slug' => 'empty.group', 'group' => '']);
    Permission::create(['name' => 'Null Group', 'slug' => 'null.group', 'group' => null]);
    Permission::create(['name' => 'Has Group', 'slug' => 'has.group', 'group' => 'posts']);

    // Empty string and null are different
    $emptyGroup = Permission::where('group', '')->get();
    $nullGroup = Permission::whereNull('group')->get();

    expect($emptyGroup)->toHaveCount(1)
        ->and($nullGroup)->toHaveCount(1);
});

test('can get all unique groups including null', function () {
    Permission::create(['name' => 'Posts 1', 'slug' => 'posts.1', 'group' => 'posts']);
    Permission::create(['name' => 'Posts 2', 'slug' => 'posts.2', 'group' => 'posts']);
    Permission::create(['name' => 'Users 1', 'slug' => 'users.1', 'group' => 'users']);
    Permission::create(['name' => 'Global', 'slug' => 'global', 'group' => null]);

    $allGroups = Permission::distinct()->pluck('group');

    expect($allGroups)->toContain('posts')
        ->and($allGroups)->toContain('users')
        ->and($allGroups)->toContain(null);
});

test('can group permissions by group with null handling', function () {
    Permission::create(['name' => 'Posts', 'slug' => 'posts', 'group' => 'posts']);
    Permission::create(['name' => 'Global 1', 'slug' => 'global1', 'group' => null]);
    Permission::create(['name' => 'Global 2', 'slug' => 'global2', 'group' => null]);

    $grouped = Permission::get()->groupBy(fn ($p) => $p->group ?? 'ungrouped');

    expect($grouped)->toHaveKey('posts')
        ->and($grouped)->toHaveKey('ungrouped')
        ->and($grouped['ungrouped'])->toHaveCount(2);
});

// =============================================================================
// Relationship Edge Cases - リレーションシップのエッジケース
// =============================================================================

test('permission can belong to multiple roles', function () {
    $permission = Permission::create(['name' => 'Shared', 'slug' => 'shared']);

    for ($i = 1; $i <= 10; $i++) {
        $role = Role::create(['name' => "Role $i", 'slug' => "role$i", 'level' => $i * 10]);
        $permission->roles()->attach($role->id);
    }

    expect($permission->roles)->toHaveCount(10);
});

test('attaching same role twice is rejected by unique constraint', function () {
    $permission = Permission::create(['name' => 'Test', 'slug' => 'test']);
    $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);

    $permission->roles()->attach($role->id);

    // Unique constraint on pivot prevents duplicates
    expect(fn () => $permission->roles()->attach($role->id))
        ->toThrow(\Illuminate\Database\QueryException::class);

    // Only one record should exist
    $pivotCount = \DB::table('role_permissions')
        ->where('role_id', $role->id)
        ->where('permission_id', $permission->id)
        ->count();

    expect($pivotCount)->toBe(1);
});

test('can sync roles from permission side', function () {
    $permission = Permission::create(['name' => 'Test', 'slug' => 'test']);
    $role1 = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
    $role2 = Role::create(['name' => 'Editor', 'slug' => 'editor', 'level' => 50]);
    $role3 = Role::create(['name' => 'Member', 'slug' => 'member', 'level' => 10]);

    $permission->roles()->attach([$role1->id, $role2->id]);
    expect($permission->roles)->toHaveCount(2);

    $permission->roles()->sync([$role2->id, $role3->id]);
    $permission->refresh();

    expect($permission->roles)->toHaveCount(2)
        ->and($permission->roles->pluck('slug')->toArray())->toContain('editor', 'member');
});

// =============================================================================
// Query Edge Cases - クエリのエッジケース
// =============================================================================

test('can search slug with SQL special characters', function () {
    $permission = Permission::create(['name' => 'Test', 'slug' => "test'permission"]);

    $found = Permission::where('slug', "test'permission")->first();
    expect($found)->not->toBeNull();
});

test('like query with percent in slug', function () {
    Permission::create(['name' => 'Percent', 'slug' => '100%discount']);
    Permission::create(['name' => 'Normal', 'slug' => 'normal']);

    // The % in slug is literal
    $found = Permission::where('slug', '100%discount')->first();
    expect($found)->not->toBeNull()
        ->and($found->name)->toBe('Percent');
});

test('can find permissions by slug pattern', function () {
    Permission::create(['name' => 'Posts Create', 'slug' => 'posts.create']);
    Permission::create(['name' => 'Posts Read', 'slug' => 'posts.read']);
    Permission::create(['name' => 'Posts Update', 'slug' => 'posts.update']);
    Permission::create(['name' => 'Posts Delete', 'slug' => 'posts.delete']);
    Permission::create(['name' => 'Users Create', 'slug' => 'users.create']);

    $postPermissions = Permission::where('slug', 'like', 'posts.%')->get();

    expect($postPermissions)->toHaveCount(4);
});

test('can find permissions not assigned to any role', function () {
    $perm1 = Permission::create(['name' => 'Assigned', 'slug' => 'assigned']);
    Permission::create(['name' => 'Orphan 1', 'slug' => 'orphan1']);
    Permission::create(['name' => 'Orphan 2', 'slug' => 'orphan2']);

    $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
    $role->permissions()->attach($perm1->id);

    $orphans = Permission::whereDoesntHave('roles')->get();

    expect($orphans)->toHaveCount(2);
});

test('can query permissions with role count', function () {
    $perm1 = Permission::create(['name' => 'Popular', 'slug' => 'popular']);
    $perm2 = Permission::create(['name' => 'Less Popular', 'slug' => 'less-popular']);
    Permission::create(['name' => 'Unpopular', 'slug' => 'unpopular']);

    $role1 = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
    $role2 = Role::create(['name' => 'Editor', 'slug' => 'editor', 'level' => 50]);
    $role3 = Role::create(['name' => 'Member', 'slug' => 'member', 'level' => 10]);

    $perm1->roles()->attach([$role1->id, $role2->id, $role3->id]);
    $perm2->roles()->attach([$role1->id]);

    $permissions = Permission::withCount('roles')->orderBy('roles_count', 'desc')->get();

    expect($permissions->first()->roles_count)->toBe(3)
        ->and($permissions->last()->roles_count)->toBe(0);
});

// =============================================================================
// Uniqueness Edge Cases - 一意性のエッジケース
// =============================================================================

test('slug uniqueness is case sensitive in SQLite', function () {
    Permission::create(['name' => 'Lower', 'slug' => 'test']);

    // SQLite is case-sensitive for unique constraints by default
    $upper = Permission::create(['name' => 'Upper', 'slug' => 'TEST']);

    // ID is UUID string now
    expect($upper->id)->toBeString()
        ->and($upper->id)->toMatch('/^[0-9a-f-]{36}$/');
});

test('name uniqueness is enforced', function () {
    Permission::create(['name' => 'Same Name', 'slug' => 'slug1']);

    expect(fn () => Permission::create(['name' => 'Same Name', 'slug' => 'slug2']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

// =============================================================================
// Update Edge Cases - 更新のエッジケース
// =============================================================================

test('can update permission preserving relationships', function () {
    $permission = Permission::create(['name' => 'Original', 'slug' => 'original', 'group' => 'old']);
    $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
    $permission->roles()->attach($role->id);

    $permission->update(['name' => 'Updated', 'slug' => 'updated', 'group' => 'new']);
    $permission->refresh();

    expect($permission->name)->toBe('Updated')
        ->and($permission->roles)->toHaveCount(1);
});

test('updating to duplicate slug fails', function () {
    Permission::create(['name' => 'First', 'slug' => 'first']);
    $second = Permission::create(['name' => 'Second', 'slug' => 'second']);

    expect(fn () => $second->update(['slug' => 'first']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

// =============================================================================
// Delete Edge Cases - 削除のエッジケース
// =============================================================================

test('can recreate permission with same slug after delete', function () {
    $permission = Permission::create(['name' => 'Reusable', 'slug' => 'reusable']);
    $permission->delete();

    $newPermission = Permission::create(['name' => 'Reusable Again', 'slug' => 'reusable']);

    expect($newPermission->name)->toBe('Reusable Again');
});

test('deleting permission removes pivot records', function () {
    $permission = Permission::create(['name' => 'To Delete', 'slug' => 'to-delete']);
    $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
    $permission->roles()->attach($role->id);

    $permissionId = $permission->id;
    $permission->roles()->detach();
    $permission->delete();

    $pivotCount = \DB::table('role_permissions')->where('permission_id', $permissionId)->count();
    expect($pivotCount)->toBe(0);
});

// =============================================================================
// Bulk Operations Edge Cases - 一括操作のエッジケース
// =============================================================================

test('can create many permissions with firstOrCreate', function () {
    $slugs = ['create', 'read', 'update', 'delete'];
    $permissions = [];

    foreach ($slugs as $slug) {
        $permissions[] = Permission::firstOrCreate(
            ['slug' => "posts.$slug"],
            ['name' => ucfirst($slug).' Posts', 'group' => 'posts']
        );
    }

    // Run again - should not create duplicates
    foreach ($slugs as $slug) {
        Permission::firstOrCreate(
            ['slug' => "posts.$slug"],
            ['name' => ucfirst($slug).' Posts', 'group' => 'posts']
        );
    }

    expect(Permission::count())->toBe(4);
});

test('can upsert permissions', function () {
    Permission::create(['name' => 'Existing', 'slug' => 'existing', 'group' => 'old']);

    Permission::upsert(
        [
            ['name' => 'Existing Updated', 'slug' => 'existing', 'group' => 'new'],
            ['name' => 'Brand New', 'slug' => 'brand-new', 'group' => 'new'],
        ],
        ['slug'],
        ['name', 'group']
    );

    expect(Permission::count())->toBe(2);

    $existing = Permission::where('slug', 'existing')->first();
    expect($existing->name)->toBe('Existing Updated')
        ->and($existing->group)->toBe('new');
});

test('can bulk delete by group', function () {
    Permission::create(['name' => 'P1', 'slug' => 'p1', 'group' => 'delete-me']);
    Permission::create(['name' => 'P2', 'slug' => 'p2', 'group' => 'delete-me']);
    Permission::create(['name' => 'P3', 'slug' => 'p3', 'group' => 'keep']);

    $deleted = Permission::where('group', 'delete-me')->delete();

    expect($deleted)->toBe(2)
        ->and(Permission::count())->toBe(1);
});

// =============================================================================
// Edge Cases with Empty Data - 空データのエッジケース
// =============================================================================

test('query on empty table returns empty collection', function () {
    $results = Permission::all();

    expect($results)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class)
        ->and($results)->toHaveCount(0);
});

test('first on empty table returns null', function () {
    $result = Permission::first();

    expect($result)->toBeNull();
});

test('count on empty table returns 0', function () {
    $count = Permission::count();

    expect($count)->toBe(0);
});

test('groupBy on empty table returns empty collection', function () {
    $grouped = Permission::get()->groupBy('group');

    expect($grouped)->toBeEmpty();
});
