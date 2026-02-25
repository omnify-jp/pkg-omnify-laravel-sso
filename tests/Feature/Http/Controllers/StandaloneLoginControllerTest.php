<?php

/**
 * StandaloneLoginController Feature Tests
 *
 * Tests for email/password authentication (omnify-auth.mode = 'standalone').
 * Covers: show login page, login validation, successful login, logout.
 */

use Illuminate\Support\Facades\Auth;
use Omnify\SsoClient\Tests\Fixtures\Models\User;

beforeEach(function () {
    // Register a dashboard route for redirect after successful login
    $this->app->make('router')
        ->get('/dashboard', fn () => response('Dashboard'))
        ->name('dashboard');

    // Ensure standalone mode is active for all tests in this file
    config(['omnify-auth.mode' => 'standalone']);
});

// =============================================================================
// Show Login Page — GET /login
// =============================================================================

test('login page is accessible for unauthenticated users', function () {
    $response = $this->get('/login', ['X-Inertia' => 'true']);

    $response->assertStatus(200);
});

test('login page renders the configured inertia component', function () {
    $response = $this->get('/login', ['X-Inertia' => 'true']);

    $response->assertStatus(200)
        ->assertJson(['component' => 'auth/login']);
});

// =============================================================================
// Login Validation — POST /login
// =============================================================================

test('login requires email field', function () {
    $response = $this->post('/login', [
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors(['email']);
});

test('login requires password field', function () {
    $response = $this->post('/login', [
        'email' => 'user@example.com',
    ]);

    $response->assertSessionHasErrors(['password']);
});

test('login requires valid email format', function () {
    $response = $this->post('/login', [
        'email' => 'not-an-email',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors(['email']);
});

test('login requires both fields when both are missing', function () {
    $response = $this->post('/login', []);

    $response->assertSessionHasErrors(['email', 'password']);
});

// =============================================================================
// Login Authentication — POST /login
// =============================================================================

test('login fails with incorrect password and returns email error', function () {
    User::factory()->withPassword('correct-password')->create([
        'email' => 'user@example.com',
    ]);

    $response = $this->post('/login', [
        'email' => 'user@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors(['email']);
    expect(Auth::check())->toBeFalse();
});

test('login fails for non-existent user', function () {
    $response = $this->post('/login', [
        'email' => 'nobody@example.com',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors(['email']);
    expect(Auth::check())->toBeFalse();
});

test('login succeeds with valid credentials and redirects to dashboard', function () {
    $user = User::factory()->withPassword('secret')->create([
        'email' => 'user@example.com',
    ]);

    $response = $this->post('/login', [
        'email' => 'user@example.com',
        'password' => 'secret',
    ]);

    $response->assertRedirect(route('dashboard'));
    expect(Auth::check())->toBeTrue();
    expect(Auth::id())->toBe($user->id);
});

test('login with remember flag authenticates the user', function () {
    User::factory()->withPassword('secret')->create([
        'email' => 'user@example.com',
    ]);

    $response = $this->post('/login', [
        'email' => 'user@example.com',
        'password' => 'secret',
        'remember' => true,
    ]);

    $response->assertRedirect(route('dashboard'));
    expect(Auth::check())->toBeTrue();
});

test('login redirects to intended url after successful authentication', function () {
    User::factory()->withPassword('secret')->create([
        'email' => 'user@example.com',
    ]);

    $this->withSession(['url.intended' => '/some-protected-page']);

    $response = $this->post('/login', [
        'email' => 'user@example.com',
        'password' => 'secret',
    ]);

    $response->assertRedirect('/some-protected-page');
});

// =============================================================================
// Logout — POST /logout
// =============================================================================

test('logout redirects unauthenticated users to login', function () {
    $response = $this->post('/logout');

    $response->assertRedirect('/login');
});

test('logout successfully logs out authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->post('/logout');

    $response->assertRedirect(route('login'));
    expect(Auth::check())->toBeFalse();
});

test('logout invalidates the session', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->post('/logout');

    expect(Auth::check())->toBeFalse();
});
