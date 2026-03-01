<?php

/**
 * Admin Guard Feature Tests (Standalone Mode)
 *
 * 管理者ガードのフィーチャーテスト（スタンドアローンモード）
 * Tests admin guard registration, middleware, login/logout, and guard isolation.
 *
 * Routes:
 *   GET  /admin/login   → showLogin  (Inertia, guest middleware)
 *   POST /admin/login   → login      (attempt admin guard)
 *   POST /admin/logout  → logout     (admin guard)
 */

use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia;
use Omnify\Core\Models\Admin;
use Omnify\Core\Models\User;

// =============================================================================
// Guard Registration — ガード登録
// =============================================================================

describe('guard registration', function () {
    test('admin guard is registered in standalone mode', function () {
        config()->set('omnify-auth.mode', 'standalone');

        // Re-trigger guard registration
        app()->make(\Omnify\Core\CoreServiceProvider::class, ['app' => app()])->register();

        $guardConfig = config('auth.guards.admin');

        expect($guardConfig)->not->toBeNull()
            ->and($guardConfig['driver'])->toBe('session')
            ->and($guardConfig['provider'])->toBe('admins');
    });

    test('admins provider is registered in standalone mode', function () {
        config()->set('omnify-auth.mode', 'standalone');

        app()->make(\Omnify\Core\CoreServiceProvider::class, ['app' => app()])->register();

        $providerConfig = config('auth.providers.admins');

        expect($providerConfig)->not->toBeNull()
            ->and($providerConfig['driver'])->toBe('eloquent')
            ->and($providerConfig['model'])->toBe(Admin::class);
    });

    test('admin guard is NOT registered in console mode', function () {
        config()->set('omnify-auth.mode', 'console');

        // Clear any previously registered guard
        config()->set('auth.guards.admin', null);
        config()->set('auth.providers.admins', null);

        app()->make(\Omnify\Core\CoreServiceProvider::class, ['app' => app()])->register();

        expect(config('auth.guards.admin'))->toBeNull()
            ->and(config('auth.providers.admins'))->toBeNull();
    });

    test('admin model is configurable via config', function () {
        config()->set('omnify-auth.mode', 'standalone');
        config()->set('omnify-auth.admin_model', 'App\\Models\\CustomAdmin');

        app()->make(\Omnify\Core\CoreServiceProvider::class, ['app' => app()])->register();

        expect(config('auth.providers.admins.model'))->toBe('App\\Models\\CustomAdmin');
    });
});

// =============================================================================
// Admin Login Page — 管理者ログインページ
// =============================================================================

describe('admin login page', function () {
    beforeEach(function () {
        config()->set('omnify-auth.mode', 'standalone');
    });

    test('shows admin login page to unauthenticated visitors', function () {
        $response = $this->get('/admin/login');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/auth/login')
            );
    });

    test('redirects authenticated admin away from login page', function () {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->get('/admin/login');

        $response->assertRedirect('/admin');
    });

    test('does NOT redirect regular User away from admin login page', function () {
        $user = User::factory()->standalone()->withPassword('password')->create();

        $response = $this->actingAs($user, 'web')->get('/admin/login');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/auth/login')
            );
    });
});

// =============================================================================
// Admin Login — 管理者ログイン
// =============================================================================

describe('admin login', function () {
    beforeEach(function () {
        config()->set('omnify-auth.mode', 'standalone');

        $this->admin = Admin::factory()->withPassword('admin-password')->create([
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
        ]);
    });

    test('admin can login with valid credentials', function () {
        $response = $this->post('/admin/login', [
            'email' => 'admin@test.com',
            'password' => 'admin-password',
        ]);

        $response->assertRedirect('/admin');
        expect(Auth::guard('admin')->check())->toBeTrue()
            ->and(Auth::guard('admin')->user()->id)->toBe($this->admin->id);
    });

    test('admin login fails with wrong password', function () {
        $response = $this->post('/admin/login', [
            'email' => 'admin@test.com',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect()
            ->assertSessionHasErrors('email');

        expect(Auth::guard('admin')->check())->toBeFalse();
    });

    test('admin login fails with non-existent email', function () {
        $response = $this->post('/admin/login', [
            'email' => 'nonexistent@test.com',
            'password' => 'admin-password',
        ]);

        $response->assertRedirect()
            ->assertSessionHasErrors('email');

        expect(Auth::guard('admin')->check())->toBeFalse();
    });

    test('admin login validates required fields', function () {
        $response = $this->post('/admin/login', []);

        $response->assertSessionHasErrors(['email', 'password']);
    });

    test('admin login validates email format', function () {
        $response = $this->post('/admin/login', [
            'email' => 'not-an-email',
            'password' => 'admin-password',
        ]);

        $response->assertSessionHasErrors('email');
    });

    test('admin login regenerates session', function () {
        $oldSessionId = session()->getId();

        $this->post('/admin/login', [
            'email' => 'admin@test.com',
            'password' => 'admin-password',
        ]);

        expect(session()->getId())->not->toBe($oldSessionId);
    });

    test('admin login respects remember me', function () {
        $response = $this->post('/admin/login', [
            'email' => 'admin@test.com',
            'password' => 'admin-password',
            'remember' => true,
        ]);

        $response->assertRedirect('/admin');

        $admin = Auth::guard('admin')->user();
        expect($admin->remember_token)->not->toBeNull();
    });
});

// =============================================================================
// Admin Logout — 管理者ログアウト
// =============================================================================

describe('admin logout', function () {
    beforeEach(function () {
        config()->set('omnify-auth.mode', 'standalone');

        $this->admin = Admin::factory()->create();
    });

    test('authenticated admin can logout', function () {
        $response = $this->actingAs($this->admin, 'admin')
            ->post('/admin/logout');

        $response->assertRedirect(route('admin.login'));
        expect(Auth::guard('admin')->check())->toBeFalse();
    });

    test('unauthenticated user cannot access logout route', function () {
        $response = $this->post('/admin/logout');

        $response->assertRedirect('/admin/login');
    });
});

// =============================================================================
// AdminAuthenticate Middleware — 管理者認証ミドルウェア
// =============================================================================

describe('AdminAuthenticate middleware', function () {
    beforeEach(function () {
        config()->set('omnify-auth.mode', 'standalone');
    });

    test('unauthenticated user is redirected to admin login', function () {
        $response = $this->get('/admin/organizations');

        $response->assertRedirect(route('admin.login'));
    });

    test('authenticated admin can access admin routes', function () {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')
            ->get('/admin/organizations');

        $response->assertOk();
    });

    test('returns 401 for unauthenticated JSON requests', function () {
        $response = $this->getJson('/admin/organizations');

        $response->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    });
});

// =============================================================================
// Guard Isolation — ガード分離
// =============================================================================

describe('guard isolation', function () {
    beforeEach(function () {
        config()->set('omnify-auth.mode', 'standalone');
    });

    test('regular User authenticated via web guard CANNOT access admin routes', function () {
        $user = User::factory()->standalone()->withPassword('password')->create();

        $response = $this->actingAs($user, 'web')
            ->get('/admin/organizations');

        $response->assertRedirect(route('admin.login'));
    });

    test('regular User credentials do NOT work on admin login', function () {
        User::factory()->standalone()->withPassword('user-pass')->create([
            'email' => 'user@test.com',
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'user@test.com',
            'password' => 'user-pass',
        ]);

        $response->assertSessionHasErrors('email');
        expect(Auth::guard('admin')->check())->toBeFalse();
    });

    test('admin guard session is independent from web guard', function () {
        $admin = Admin::factory()->withPassword('admin-pass')->create();

        User::factory()->standalone()->withPassword('user-pass')->create();

        // Login as admin
        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'admin-pass',
        ]);

        expect(Auth::guard('admin')->check())->toBeTrue()
            ->and(Auth::guard('web')->check())->toBeFalse();
    });

    test('admin credentials do NOT work on regular user login', function () {
        Admin::factory()->withPassword('admin-pass')->create([
            'email' => 'admin@test.com',
        ]);

        $response = $this->post('/login', [
            'email' => 'admin@test.com',
            'password' => 'admin-pass',
        ]);

        $response->assertSessionHasErrors('email');
        expect(Auth::guard('web')->check())->toBeFalse();
    });
});

// =============================================================================
// Config Customization — 設定カスタマイズ
// =============================================================================

describe('config customization', function () {
    beforeEach(function () {
        config()->set('omnify-auth.mode', 'standalone');
    });

    test('admin login page path is configurable', function () {
        config()->set('omnify-auth.standalone.pages.admin_login', 'custom/admin-login');

        $response = $this->get('/admin/login');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('custom/admin-login')
            );
    });

    test('admin redirect after logout is configurable', function () {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')
            ->post('/admin/logout');

        $response->assertRedirect(route('admin.login'));
    });
});
