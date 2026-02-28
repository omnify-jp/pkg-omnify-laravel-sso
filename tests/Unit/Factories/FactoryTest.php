<?php

declare(strict_types=1);

use Omnify\Core\Models\Permission;
use Omnify\Core\Models\Role;
use Omnify\Core\Models\RolePermission;
use Omnify\Core\Models\User;

// =============================================================================
// UserFactory Tests
// =============================================================================

describe('UserFactory', function () {
    test('creates a valid user with all required fields', function () {
        $user = User::factory()->create();

        expect($user)->toBeInstanceOf(User::class);
        expect($user->id)->toBeString(); // UUID
        expect($user->name)->toBeString()->not->toBeEmpty();
        expect($user->email)->toBeString()->toContain('@');
        // SSO users don't have passwords - authentication is via Console tokens
        expect($user->console_user_id)->not->toBeNull();
    });

    test('creates multiple unique users', function () {
        $users = User::factory()->count(5)->create();

        expect($users)->toHaveCount(5);

        $emails = $users->pluck('email')->toArray();
        expect(array_unique($emails))->toHaveCount(5);

        $consoleIds = $users->pluck('console_user_id')->toArray();
        expect(array_unique($consoleIds))->toHaveCount(5);
    });

    test('withoutConsoleUserId state creates user without SSO data', function () {
        $user = User::factory()->withoutConsoleUserId()->create();

        expect($user->console_user_id)->toBeNull();
        expect($user->console_access_token)->toBeNull();
        expect($user->console_refresh_token)->toBeNull();
        expect($user->console_token_expires_at)->toBeNull();
    });

    test('unverified state is no-op for SSO users (verified via Console)', function () {
        // SSO users don't have email_verified_at column - verification is handled by Console
        $user = User::factory()->unverified()->create();

        // unverified() is a no-op for SSO users - should create normally
        expect($user->id)->not->toBeNull();
        expect($user->email)->not->toBeNull();
    });

    test('withRole state assigns specific role', function () {
        // Create the role first
        $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
        $user = User::factory()->withRole('admin')->create();

        // SSO uses many-to-many roles, not single role_id
        expect($user->roles)->toHaveCount(1);
        expect($user->roles->first()->slug)->toBe('admin');
    });

    test('allows overriding attributes', function () {
        $user = User::factory()->create([
            'name' => 'Custom Name',
            'email' => 'custom@test.com',
        ]);

        expect($user->name)->toBe('Custom Name');
        expect($user->email)->toBe('custom@test.com');
    });

    test('make returns unsaved instance', function () {
        $user = User::factory()->make();

        expect($user->id)->toBeNull();
        expect($user->exists)->toBeFalse();
        expect($user->name)->not->toBeNull();
    });
});

// =============================================================================
// RoleFactory Tests
// =============================================================================

describe('RoleFactory', function () {
    test('creates a valid role', function () {
        $role = Role::factory()->create();

        expect($role)->toBeInstanceOf(Role::class);
        expect($role->id)->toBeString()->toMatch('/^[0-9a-f-]{36}$/'); // UUID
        expect($role->name)->toBeString()->not->toBeEmpty();
        expect($role->slug)->toBeString()->not->toBeEmpty();
    });

    test('creates multiple unique roles', function () {
        $roles = Role::factory()->count(3)->create();

        expect($roles)->toHaveCount(3);

        $slugs = $roles->pluck('slug')->toArray();
        expect(array_unique($slugs))->toHaveCount(3);
    });

    test('allows overriding attributes', function () {
        $role = Role::factory()->create([
            'name' => 'Super Admin',
            'slug' => 'super-admin',
            'level' => 999,
        ]);

        expect($role->name)->toBe('Super Admin');
        expect($role->slug)->toBe('super-admin');
        expect($role->level)->toBe(999);
    });

    test('has correct default level', function () {
        $role = Role::factory()->create();

        expect($role->level)->toBeInt();
    });
});

// =============================================================================
// PermissionFactory Tests
// =============================================================================

describe('PermissionFactory', function () {
    test('creates a valid permission', function () {
        $permission = Permission::factory()->create();

        expect($permission)->toBeInstanceOf(Permission::class);
        expect($permission->id)->toBeString()->toMatch('/^[0-9a-f-]{36}$/'); // UUID
        expect($permission->name)->toBeString()->not->toBeEmpty();
        expect($permission->slug)->toBeString()->not->toBeEmpty();
    });

    test('creates multiple unique permissions', function () {
        $permissions = Permission::factory()->count(5)->create();

        expect($permissions)->toHaveCount(5);

        $slugs = $permissions->pluck('slug')->toArray();
        expect(array_unique($slugs))->toHaveCount(5);
    });

    test('allows setting group', function () {
        $permission = Permission::factory()->create([
            'group' => 'users',
        ]);

        expect($permission->group)->toBe('users');
    });

    test('allows overriding attributes', function () {
        $permission = Permission::factory()->create([
            'name' => 'Create Users',
            'slug' => 'users.create',
            'group' => 'users',
        ]);

        expect($permission->name)->toBe('Create Users');
        expect($permission->slug)->toBe('users.create');
        expect($permission->group)->toBe('users');
    });
});

// =============================================================================
// RolePermissionFactory Tests
// =============================================================================

describe('RolePermissionFactory', function () {
    test('creates a valid role-permission relationship', function () {
        $role = Role::factory()->create();
        $permission = Permission::factory()->create();

        $rolePermission = RolePermission::factory()->create([
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);

        expect($rolePermission)->toBeInstanceOf(RolePermission::class);
        expect($rolePermission->role_id)->toBe($role->id);
        expect($rolePermission->permission_id)->toBe($permission->id);
    });

    test('creates with auto-generated role and permission', function () {
        $rolePermission = RolePermission::factory()->create();

        expect($rolePermission->role_id)->not->toBeNull();
        expect($rolePermission->permission_id)->not->toBeNull();
    });
});

// =============================================================================
// Factory Relationship Tests
// =============================================================================

describe('Factory Relationships', function () {
    test('user can be created with role relationship', function () {
        // SSO uses many-to-many roles, not single role_id
        $role = Role::factory()->create(['name' => 'Test Role', 'slug' => 'test-role']);
        $user = User::factory()->create();
        $user->assignRole($role);

        expect($user->roles)->toHaveCount(1);
        expect($user->roles->first()->id)->toBe($role->id);
    });

    test('role can have many permissions through pivot', function () {
        $role = Role::factory()->create();
        $permissions = Permission::factory()->count(3)->create();

        foreach ($permissions as $permission) {
            RolePermission::factory()->create([
                'role_id' => $role->id,
                'permission_id' => $permission->id,
            ]);
        }

        expect($role->permissions)->toHaveCount(3);
    });

});

// =============================================================================
// Factory Edge Cases
// =============================================================================

describe('Factory Edge Cases', function () {
    test('factory handles concurrent creation without conflicts', function () {
        // Create many records at once
        $users = User::factory()->count(10)->create();
        $roles = Role::factory()->count(10)->create();
        $permissions = Permission::factory()->count(10)->create();

        expect($users)->toHaveCount(10);
        expect($roles)->toHaveCount(10);
        expect($permissions)->toHaveCount(10);

        // All should have unique IDs
        expect($users->pluck('id')->unique())->toHaveCount(10);
        expect($roles->pluck('id')->unique())->toHaveCount(10);
        expect($permissions->pluck('id')->unique())->toHaveCount(10);
    });

    test('factory respects database constraints', function () {
        // Email is unique within an organization
        $orgId = (string) \Illuminate\Support\Str::uuid();
        $user1 = User::factory()->create(['email' => 'unique@test.com', 'console_organization_id' => $orgId]);

        expect(fn () => User::factory()->create(['email' => 'unique@test.com', 'console_organization_id' => $orgId]))
            ->toThrow(\Illuminate\Database\QueryException::class);
    });

    test('factory with chained states works correctly', function () {
        $user = User::factory()
            ->withoutConsoleUserId()
            ->unverified()
            ->create();

        expect($user->console_user_id)->toBeNull();
        expect($user->email_verified_at)->toBeNull();
    });
});
