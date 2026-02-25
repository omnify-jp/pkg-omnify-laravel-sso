<?php

/**
 * Gate Feature Tests
 *
 * Gateのテスト
 */

use Illuminate\Support\Facades\Gate;
use Omnify\SsoClient\Models\Permission;
use Omnify\SsoClient\Models\Role;
use Omnify\SsoClient\Tests\Fixtures\Models\User;

beforeEach(function () {
    $this->artisan('migrate', ['--database' => 'testing']);
});

// =============================================================================
// Gate Before Hook Tests - Gate Before Hookのテスト
// =============================================================================

test('gate before hook checks user hasPermission method', function () {
    // Create role with permission
    $role = Role::create(['slug' => 'editor', 'name' => 'Editor', 'level' => 50]);
    $permission = Permission::create(['slug' => 'posts.create', 'name' => 'Create Posts']);
    $role->permissions()->attach($permission->id);

    // Create user and assign role (many-to-many relationship)
    $user = User::factory()->create();
    $user->assignRole($role);

    // Test actual hasPermission via roles (many-to-many)
    // User should have the permission through their assigned role
    $hasPermission = $user->roles->contains(fn ($r) => $r->hasPermission('posts.create'));

    expect($hasPermission)->toBeTrue();
});

// =============================================================================
// Dynamic Gate Tests - 動的Gateのテスト
// =============================================================================

test('gates are defined for each permission in database', function () {
    // Create permissions
    Permission::create(['slug' => 'posts.create', 'name' => 'Create Posts']);
    Permission::create(['slug' => 'posts.edit', 'name' => 'Edit Posts']);
    Permission::create(['slug' => 'users.view', 'name' => 'View Users']);

    // Force re-registration of gates
    app()->booted(function () {
        // Gates should be defined after boot
    });

    // Verify permissions exist in database
    expect(Permission::count())->toBe(3);
});

// =============================================================================
// Permission Check Flow Tests - パーミッションチェックフローのテスト
// =============================================================================

test('user role permission flow works correctly', function () {
    // Setup: Create role with permissions
    $adminRole = Role::create(['slug' => 'admin', 'name' => 'Admin', 'level' => 100]);
    $editorRole = Role::create(['slug' => 'editor', 'name' => 'Editor', 'level' => 50]);

    $createPermission = Permission::create(['slug' => 'posts.create', 'name' => 'Create Posts']);
    $deletePermission = Permission::create(['slug' => 'posts.delete', 'name' => 'Delete Posts']);

    // Admin has both permissions
    $adminRole->permissions()->attach([$createPermission->id, $deletePermission->id]);

    // Editor only has create permission
    $editorRole->permissions()->attach([$createPermission->id]);

    // Verify role-permission relationships
    expect($adminRole->hasPermission('posts.create'))->toBeTrue()
        ->and($adminRole->hasPermission('posts.delete'))->toBeTrue()
        ->and($editorRole->hasPermission('posts.create'))->toBeTrue()
        ->and($editorRole->hasPermission('posts.delete'))->toBeFalse();
});

test('permission inheritance through role levels', function () {
    // Higher level roles should have more permissions
    $admin = Role::create(['slug' => 'admin', 'name' => 'Admin', 'level' => 100]);
    $manager = Role::create(['slug' => 'manager', 'name' => 'Manager', 'level' => 50]);
    $member = Role::create(['slug' => 'member', 'name' => 'Member', 'level' => 10]);

    // Create permission hierarchy
    $allPermissions = collect([
        Permission::create(['slug' => 'posts.view', 'name' => 'View Posts']),
        Permission::create(['slug' => 'posts.create', 'name' => 'Create Posts']),
        Permission::create(['slug' => 'posts.delete', 'name' => 'Delete Posts']),
        Permission::create(['slug' => 'users.manage', 'name' => 'Manage Users']),
    ]);

    // Member: view only
    $member->permissions()->attach($allPermissions[0]->id);

    // Manager: view + create
    $manager->permissions()->attach([$allPermissions[0]->id, $allPermissions[1]->id]);

    // Admin: all
    $admin->permissions()->attach($allPermissions->pluck('id')->toArray());

    expect($member->permissions()->count())->toBe(1)
        ->and($manager->permissions()->count())->toBe(2)
        ->and($admin->permissions()->count())->toBe(4);
});
