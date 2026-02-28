<?php

/**
 * UserAdminController (Standalone) Feature Tests
 *
 * Tests for standalone user management pages:
 *   GET  /admin/users/create  -> create (Inertia page)
 *   POST /admin/users         -> store  (redirect)
 *
 * Middleware: ['web', 'auth']
 */

use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Omnify\Core\Models\Role;
use Omnify\Core\Models\User;

beforeEach(function () {
    config()->set('omnify-auth.mode', 'standalone');
    config()->set('omnify-auth.standalone.admin_enabled', true);
});

// =============================================================================
// Auth Guard Tests - 認証ガードテスト
// =============================================================================

describe('auth guard', function () {
    test('unauthenticated user gets redirected from create page', function () {
        $response = $this->get('/admin/users/create');

        $response->assertRedirect('/login');
    });

    test('unauthenticated user cannot store user', function () {
        $response = $this->post('/admin/users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/login');
    });
});

// =============================================================================
// Create Page Tests - 作成ページテスト
// =============================================================================

describe('GET /admin/users/create', function () {
    test('returns create page with roles list', function () {
        $admin = User::factory()->standalone()->create();
        $roleA = Role::factory()->create(['name' => 'Manager', 'level' => 50]);
        $roleB = Role::factory()->create(['name' => 'Staff', 'level' => 10]);

        $response = $this->actingAs($admin)->get('/admin/users/create');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('roles', 2)
            ->where('roles.0.id', $roleA->id)
            ->where('roles.0.name', 'Manager')
            ->where('roles.1.id', $roleB->id)
            ->where('roles.1.name', 'Staff')
        );
    });

    test('roles are ordered by level descending', function () {
        $admin = User::factory()->standalone()->create();
        $low = Role::factory()->create(['name' => 'Viewer', 'level' => 10]);
        $high = Role::factory()->create(['name' => 'Admin', 'level' => 100]);
        $mid = Role::factory()->create(['name' => 'Manager', 'level' => 50]);

        $response = $this->actingAs($admin)->get('/admin/users/create');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('roles', 3)
            ->where('roles.0.id', $high->id)
            ->where('roles.0.level', 100)
            ->where('roles.1.id', $mid->id)
            ->where('roles.1.level', 50)
            ->where('roles.2.id', $low->id)
            ->where('roles.2.level', 10)
        );
    });
});

// =============================================================================
// Store Tests - ユーザー作成テスト
// =============================================================================

describe('POST /admin/users', function () {
    test('creates user with valid data', function () {
        $admin = User::factory()->standalone()->create();

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('access.users'));

        $this->assertDatabaseHas('users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
        ]);
    });

    test('sets is_standalone to true', function () {
        $admin = User::factory()->standalone()->create();

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Standalone User',
            'email' => 'standalone@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'standalone@example.com')->first();

        expect($user)->not->toBeNull();
        expect($user->is_standalone)->toBeTrue();
    });

    test('hashes password', function () {
        $admin = User::factory()->standalone()->create();
        $plainPassword = 'password123';

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Hash Test',
            'email' => 'hashtest@example.com',
            'password' => $plainPassword,
            'password_confirmation' => $plainPassword,
        ]);

        $user = User::where('email', 'hashtest@example.com')->first();

        expect($user)->not->toBeNull();
        expect($user->password)->not->toBe($plainPassword);
        expect(Hash::check($plainPassword, $user->password))->toBeTrue();
    });

    test('assigns role when role_id provided', function () {
        $admin = User::factory()->standalone()->create();
        $role = Role::factory()->create(['name' => 'Manager', 'slug' => 'manager', 'level' => 50]);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Role User',
            'email' => 'roleuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_id' => $role->id,
        ]);

        $user = User::where('email', 'roleuser@example.com')->first();

        expect($user)->not->toBeNull();
        expect($user->roles)->toHaveCount(1);
        expect($user->roles->first()->id)->toBe($role->id);
    });

    test('creates user without role when role_id not provided', function () {
        $admin = User::factory()->standalone()->create();

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'No Role User',
            'email' => 'norole@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'norole@example.com')->first();

        expect($user)->not->toBeNull();
        expect($user->roles)->toHaveCount(0);
    });

    test('redirects after successful creation', function () {
        $admin = User::factory()->standalone()->create();

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Redirect User',
            'email' => 'redirect@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('access.users'));
        $response->assertSessionHas('success');
    });

    // =========================================================================
    // Validation Tests - バリデーションテスト
    // =========================================================================

    test('validates name is required', function () {
        $admin = User::factory()->standalone()->create();

        $response = $this->actingAs($admin)->post('/admin/users', [
            'email' => 'valid@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('name');
    });

    test('validates email is required', function () {
        $admin = User::factory()->standalone()->create();

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Test User',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
    });

    test('validates email format', function () {
        $admin = User::factory()->standalone()->create();

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Test User',
            'email' => 'not-an-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
    });

    test('validates email uniqueness', function () {
        $admin = User::factory()->standalone()->create();
        User::factory()->standalone()->create(['email' => 'taken@example.com']);

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Duplicate Email',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
    });

    test('validates password is required', function () {
        $admin = User::factory()->standalone()->create();

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Test User',
            'email' => 'nopass@example.com',
        ]);

        $response->assertSessionHasErrors('password');
    });

    test('validates password minimum length', function () {
        $admin = User::factory()->standalone()->create();

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Test User',
            'email' => 'shortpass@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertSessionHasErrors('password');
    });

    test('validates password confirmation', function () {
        $admin = User::factory()->standalone()->create();

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Test User',
            'email' => 'mismatch@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different456',
        ]);

        $response->assertSessionHasErrors('password');
    });

    test('validates role_id exists when provided', function () {
        $admin = User::factory()->standalone()->create();

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Test User',
            'email' => 'badrole@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_id' => 99999,
        ]);

        $response->assertSessionHasErrors('role_id');
    });
});
