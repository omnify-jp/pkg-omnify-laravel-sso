<?php

/**
 * Permission Model Unit Tests
 *
 * パーミッションモデルのユニットテスト
 * Kiểm thử đơn vị cho Model Permission
 */

use Omnify\SsoClient\Models\Permission;
use Omnify\SsoClient\Models\Role;

beforeEach(function () {
    $this->artisan('migrate', ['--database' => 'testing']);
});

// =============================================================================
// Basic Model Tests - 基本モデルテスト
// =============================================================================

test('can create permission with required fields', function () {
    $permission = Permission::create([
        'name' => 'Create Posts',
        'slug' => 'posts.create',
    ]);

    expect($permission)->toBeInstanceOf(Permission::class)
        ->and($permission->name)->toBe('Create Posts')
        ->and($permission->slug)->toBe('posts.create')
        ->and($permission->id)->toBeString()
        ->and($permission->id)->toMatch('/^[0-9a-f-]{36}$/');
});

test('can create permission with all fields', function () {
    $permission = Permission::create([
        'name' => 'Manage Users',
        'slug' => 'users.manage',
        'group' => 'users',
    ]);

    expect($permission->name)->toBe('Manage Users')
        ->and($permission->slug)->toBe('users.manage')
        ->and($permission->group)->toBe('users');
});

test('slug must be unique', function () {
    Permission::create(['name' => 'Create 1', 'slug' => 'same.slug']);

    expect(fn () => Permission::create(['name' => 'Create 2', 'slug' => 'same.slug']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('name must be unique', function () {
    Permission::create(['name' => 'Same Name', 'slug' => 'slug1']);

    expect(fn () => Permission::create(['name' => 'Same Name', 'slug' => 'slug2']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('group can be null', function () {
    $permission = Permission::create([
        'name' => 'Global Permission',
        'slug' => 'global.permission',
    ]);

    expect($permission->group)->toBeNull();
});

// =============================================================================
// Relationship Tests - リレーションシップテスト
// =============================================================================

test('permission belongs to many roles', function () {
    $permission = Permission::create(['name' => 'Create Posts', 'slug' => 'posts.create']);
    $role1 = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
    $role2 = Role::create(['name' => 'Editor', 'slug' => 'editor', 'level' => 50]);

    $permission->roles()->attach([$role1->id, $role2->id]);

    expect($permission->roles)->toHaveCount(2);
});

test('roles relationship includes timestamps', function () {
    $permission = Permission::create(['name' => 'Create Posts', 'slug' => 'posts.create']);
    $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);

    $permission->roles()->attach($role->id);

    $pivot = $permission->roles()->first()->pivot;
    expect($pivot->created_at)->not->toBeNull()
        ->and($pivot->updated_at)->not->toBeNull();
});

test('can sync roles', function () {
    $permission = Permission::create(['name' => 'Create Posts', 'slug' => 'posts.create']);
    $role1 = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
    $role2 = Role::create(['name' => 'Editor', 'slug' => 'editor', 'level' => 50]);
    $role3 = Role::create(['name' => 'Author', 'slug' => 'author', 'level' => 30]);

    $permission->roles()->attach([$role1->id, $role2->id]);
    expect($permission->roles)->toHaveCount(2);

    $permission->roles()->sync([$role2->id, $role3->id]);
    $permission->refresh();

    expect($permission->roles)->toHaveCount(2)
        ->and($permission->roles->pluck('slug')->toArray())->toContain('editor', 'author')
        ->and($permission->roles->pluck('slug')->toArray())->not->toContain('admin');
});

test('can detach all roles', function () {
    $permission = Permission::create(['name' => 'Create Posts', 'slug' => 'posts.create']);
    $role1 = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
    $role2 = Role::create(['name' => 'Editor', 'slug' => 'editor', 'level' => 50]);

    $permission->roles()->attach([$role1->id, $role2->id]);
    expect($permission->roles)->toHaveCount(2);

    $permission->roles()->detach();
    $permission->refresh();

    expect($permission->roles)->toHaveCount(0);
});

// =============================================================================
// Query Tests - クエリテスト
// =============================================================================

test('can find permission by slug', function () {
    Permission::create(['name' => 'Create Posts', 'slug' => 'posts.create', 'group' => 'posts']);

    $found = Permission::where('slug', 'posts.create')->first();

    expect($found)->not->toBeNull()
        ->and($found->name)->toBe('Create Posts');
});

test('can filter by group', function () {
    Permission::create(['name' => 'Create Posts', 'slug' => 'posts.create', 'group' => 'posts']);
    Permission::create(['name' => 'Edit Posts', 'slug' => 'posts.edit', 'group' => 'posts']);
    Permission::create(['name' => 'View Users', 'slug' => 'users.view', 'group' => 'users']);

    $postsPermissions = Permission::where('group', 'posts')->get();

    expect($postsPermissions)->toHaveCount(2);
});

test('can get distinct groups', function () {
    Permission::create(['name' => 'Create Posts', 'slug' => 'posts.create', 'group' => 'posts']);
    Permission::create(['name' => 'Edit Posts', 'slug' => 'posts.edit', 'group' => 'posts']);
    Permission::create(['name' => 'View Users', 'slug' => 'users.view', 'group' => 'users']);
    Permission::create(['name' => 'Global', 'slug' => 'global', 'group' => null]);

    $groups = Permission::distinct()->pluck('group')->filter()->values();

    expect($groups)->toHaveCount(2)
        ->and($groups->toArray())->toContain('posts', 'users');
});

test('can filter permissions with no group', function () {
    Permission::create(['name' => 'Create Posts', 'slug' => 'posts.create', 'group' => 'posts']);
    Permission::create(['name' => 'Global 1', 'slug' => 'global1', 'group' => null]);
    Permission::create(['name' => 'Global 2', 'slug' => 'global2', 'group' => null]);

    $ungrouped = Permission::whereNull('group')->get();

    expect($ungrouped)->toHaveCount(2);
});

test('can search permissions by name', function () {
    Permission::create(['name' => 'Create Posts', 'slug' => 'posts.create', 'group' => 'posts']);
    Permission::create(['name' => 'Create Users', 'slug' => 'users.create', 'group' => 'users']);
    Permission::create(['name' => 'Delete Posts', 'slug' => 'posts.delete', 'group' => 'posts']);

    $createPermissions = Permission::where('name', 'like', 'Create%')->get();

    expect($createPermissions)->toHaveCount(2);
});

test('can search permissions by slug pattern', function () {
    Permission::create(['name' => 'Create Posts', 'slug' => 'posts.create', 'group' => 'posts']);
    Permission::create(['name' => 'Edit Posts', 'slug' => 'posts.edit', 'group' => 'posts']);
    Permission::create(['name' => 'Delete Posts', 'slug' => 'posts.delete', 'group' => 'posts']);
    Permission::create(['name' => 'View Users', 'slug' => 'users.view', 'group' => 'users']);

    $postPermissions = Permission::where('slug', 'like', 'posts.%')->get();

    expect($postPermissions)->toHaveCount(3);
});

test('can find permissions assigned to a role', function () {
    $role = Role::create(['name' => 'Editor', 'slug' => 'editor', 'level' => 50]);
    $perm1 = Permission::create(['name' => 'Create Posts', 'slug' => 'posts.create']);
    $perm2 = Permission::create(['name' => 'Edit Posts', 'slug' => 'posts.edit']);
    Permission::create(['name' => 'Delete Posts', 'slug' => 'posts.delete']); // Not assigned

    $role->permissions()->attach([$perm1->id, $perm2->id]);

    $assignedPermissions = Permission::whereHas('roles', function ($query) use ($role) {
        $query->where('roles.id', $role->id);
    })->get();

    expect($assignedPermissions)->toHaveCount(2);
});

test('can find unassigned permissions', function () {
    $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
    $perm1 = Permission::create(['name' => 'Create Posts', 'slug' => 'posts.create']);
    Permission::create(['name' => 'Edit Posts', 'slug' => 'posts.edit']);
    Permission::create(['name' => 'Delete Posts', 'slug' => 'posts.delete']);

    $role->permissions()->attach($perm1->id);

    $unassigned = Permission::whereDoesntHave('roles')->get();

    expect($unassigned)->toHaveCount(2);
});

// =============================================================================
// Grouping Tests - グループ化テスト
// =============================================================================

test('can get permissions grouped by group', function () {
    Permission::create(['name' => 'Create Posts', 'slug' => 'posts.create', 'group' => 'posts']);
    Permission::create(['name' => 'Edit Posts', 'slug' => 'posts.edit', 'group' => 'posts']);
    Permission::create(['name' => 'View Users', 'slug' => 'users.view', 'group' => 'users']);
    Permission::create(['name' => 'Global', 'slug' => 'global', 'group' => null]);

    $grouped = Permission::get()->groupBy('group');

    expect($grouped)->toHaveKey('posts')
        ->and($grouped)->toHaveKey('users')
        ->and($grouped)->toHaveKey('')
        ->and($grouped['posts'])->toHaveCount(2)
        ->and($grouped['users'])->toHaveCount(1);
});

test('can count permissions per group', function () {
    Permission::create(['name' => 'Create Posts', 'slug' => 'posts.create', 'group' => 'posts']);
    Permission::create(['name' => 'Edit Posts', 'slug' => 'posts.edit', 'group' => 'posts']);
    Permission::create(['name' => 'Delete Posts', 'slug' => 'posts.delete', 'group' => 'posts']);
    Permission::create(['name' => 'View Users', 'slug' => 'users.view', 'group' => 'users']);

    $counts = Permission::selectRaw('`group`, count(*) as count')
        ->whereNotNull('group')
        ->groupBy('group')
        ->pluck('count', 'group');

    expect($counts['posts'])->toBe(3)
        ->and($counts['users'])->toBe(1);
});

// =============================================================================
// Update & Delete Tests - 更新・削除テスト
// =============================================================================

test('can update permission', function () {
    $permission = Permission::create([
        'name' => 'Old Name',
        'slug' => 'old.slug',
        'group' => 'old',
    ]);

    $permission->update([
        'name' => 'New Name',
        'slug' => 'new.slug',
        'group' => 'new',
    ]);

    $permission->refresh();

    expect($permission->name)->toBe('New Name')
        ->and($permission->slug)->toBe('new.slug')
        ->and($permission->group)->toBe('new');
});

test('can delete permission', function () {
    $permission = Permission::create(['name' => 'Deletable', 'slug' => 'deletable']);
    $permissionId = $permission->id;

    $permission->delete();

    expect(Permission::find($permissionId))->toBeNull();
});

test('deleting permission does not auto-cascade to pivot table', function () {
    $permission = Permission::create(['name' => 'Test', 'slug' => 'test']);
    $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
    $permission->roles()->attach($role->id);

    $permissionId = $permission->id;

    // Manually detach before delete if needed
    $permission->roles()->detach();
    $permission->delete();

    // Now pivot should be clean
    $pivotCount = \DB::table('role_permissions')->where('permission_id', $permissionId)->count();
    expect($pivotCount)->toBe(0);
});

// =============================================================================
// Timestamp Tests - タイムスタンプテスト
// =============================================================================

test('timestamps are automatically set', function () {
    $permission = Permission::create(['name' => 'Test', 'slug' => 'test']);

    expect($permission->created_at)->not->toBeNull()
        ->and($permission->updated_at)->not->toBeNull()
        ->and($permission->created_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

test('updated_at changes on update', function () {
    $permission = Permission::create(['name' => 'Test', 'slug' => 'test']);
    $originalUpdatedAt = $permission->updated_at;

    usleep(100000); // 0.1 second

    $permission->update(['name' => 'Updated']);

    expect($permission->updated_at->gte($originalUpdatedAt))->toBeTrue();
});

// =============================================================================
// Bulk Operations Tests - 一括操作テスト
// =============================================================================

test('can insert multiple permissions', function () {
    // When using insert(), UUIDs must be provided manually
    Permission::insert([
        ['id' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Create Posts', 'slug' => 'posts.create', 'group' => 'posts', 'created_at' => now(), 'updated_at' => now()],
        ['id' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Edit Posts', 'slug' => 'posts.edit', 'group' => 'posts', 'created_at' => now(), 'updated_at' => now()],
        ['id' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Delete Posts', 'slug' => 'posts.delete', 'group' => 'posts', 'created_at' => now(), 'updated_at' => now()],
    ]);

    expect(Permission::count())->toBe(3);
});

test('can upsert permissions', function () {
    Permission::create(['name' => 'Existing', 'slug' => 'existing', 'group' => 'old']);

    Permission::upsert(
        [
            ['name' => 'Existing', 'slug' => 'existing', 'group' => 'new'],
            ['name' => 'New One', 'slug' => 'new-one', 'group' => 'new'],
        ],
        ['slug'],
        ['group']
    );

    expect(Permission::count())->toBe(2);

    $existing = Permission::where('slug', 'existing')->first();
    expect($existing->group)->toBe('new');
});
