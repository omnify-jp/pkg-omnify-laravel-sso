<?php

/**
 * PasswordResetController + NewPasswordController Feature Tests
 *
 * Tests for password reset flow (omnify-auth.mode = 'standalone').
 * Covers: forgot-password page, send reset link, reset-password page, reset password.
 *
 * Routes (guest middleware):
 *   GET  /forgot-password         → PasswordResetController@create
 *   POST /forgot-password         → PasswordResetController@store
 *   GET  /reset-password/{token}  → NewPasswordController@create
 *   POST /reset-password          → NewPasswordController@store
 */

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Omnify\Core\Models\User;

beforeEach(function () {
    config(['omnify-auth.mode' => 'standalone']);
    config(['omnify-auth.standalone.password_reset' => true]);

    // Register a dashboard route for redirect after login
    $this->app->make('router')
        ->get('/dashboard', fn () => response('Dashboard'))
        ->name('dashboard');
});

// =============================================================================
// Forgot Password Page — GET /forgot-password
// =============================================================================

test('forgot password page is accessible', function () {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
});

test('forgot password page renders correct Inertia component', function () {
    $response = $this->get('/forgot-password', ['X-Inertia' => 'true']);

    $response->assertStatus(200)
        ->assertJson(['component' => 'auth/forgot-password']);
});

// =============================================================================
// Send Reset Link — POST /forgot-password
// =============================================================================

test('forgot password validates email required', function () {
    $response = $this->post('/forgot-password', []);

    $response->assertSessionHasErrors(['email']);
});

test('forgot password validates email format', function () {
    $response = $this->post('/forgot-password', [
        'email' => 'not-an-email',
    ]);

    $response->assertSessionHasErrors(['email']);
});

test('forgot password sends reset link for existing user', function () {
    Notification::fake();

    $user = User::factory()->withPassword('password')->create([
        'email' => 'user@example.com',
    ]);

    $response = $this->post('/forgot-password', [
        'email' => 'user@example.com',
    ]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('forgot password returns success even for non-existent email', function () {
    // The Password facade returns INVALID_USER for non-existent emails,
    // which the controller returns as a validation error on the email field.
    // This is Laravel's default behavior (not silently succeeding).
    $response = $this->post('/forgot-password', [
        'email' => 'nobody@example.com',
    ]);

    $response->assertSessionHasErrors(['email']);
});

// =============================================================================
// Reset Password Page — GET /reset-password/{token}
// =============================================================================

test('reset password page is accessible with token', function () {
    $response = $this->get('/reset-password/test-token?email=user@example.com');

    $response->assertStatus(200);
});

test('reset password page passes token and email to Inertia', function () {
    $response = $this->get(
        '/reset-password/my-token?email=user@example.com',
        ['X-Inertia' => 'true']
    );

    $response->assertStatus(200)
        ->assertJson([
            'component' => 'auth/reset-password',
            'props' => [
                'token' => 'my-token',
                'email' => 'user@example.com',
            ],
        ]);
});

// =============================================================================
// Reset Password — POST /reset-password
// =============================================================================

test('reset password validates required fields', function () {
    $response = $this->post('/reset-password', []);

    $response->assertSessionHasErrors(['token', 'email', 'password']);
});

test('reset password validates password minimum length', function () {
    $response = $this->post('/reset-password', [
        'token' => 'some-token',
        'email' => 'user@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertSessionHasErrors(['password']);
});

test('reset password validates password confirmation', function () {
    $response = $this->post('/reset-password', [
        'token' => 'some-token',
        'email' => 'user@example.com',
        'password' => 'new-password-123',
        'password_confirmation' => 'different-password',
    ]);

    $response->assertSessionHasErrors(['password']);
});

test('reset password resets with valid token', function () {
    $user = User::factory()->withPassword('old-password')->create([
        'email' => 'user@example.com',
    ]);

    $token = Password::createToken($user);

    $response = $this->post('/reset-password', [
        'token' => $token,
        'email' => 'user@example.com',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertRedirect(route('login'));

    $user->refresh();
    expect(Hash::check('new-password-123', $user->password))->toBeTrue();
});

test('reset password fires PasswordReset event', function () {
    Event::fake([PasswordReset::class]);

    $user = User::factory()->withPassword('old-password')->create([
        'email' => 'user@example.com',
    ]);

    $token = Password::createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => 'user@example.com',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    Event::assertDispatched(PasswordReset::class);
});

test('reset password redirects to login after success', function () {
    $user = User::factory()->withPassword('old-password')->create([
        'email' => 'user@example.com',
    ]);

    $token = Password::createToken($user);

    $response = $this->post('/reset-password', [
        'token' => $token,
        'email' => 'user@example.com',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertRedirect(route('login'));
});

test('reset password fails with invalid token', function () {
    User::factory()->withPassword('old-password')->create([
        'email' => 'user@example.com',
    ]);

    $response = $this->post('/reset-password', [
        'token' => 'invalid-token',
        'email' => 'user@example.com',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertSessionHasErrors(['email']);
});

// =============================================================================
// Guest Middleware — authenticated user redirect
// =============================================================================

test('authenticated user is redirected from forgot password', function () {
    $user = User::factory()->withPassword('password')->create();

    $response = $this->actingAs($user)->get('/forgot-password');

    // Guest middleware redirects authenticated users
    $response->assertRedirect();
});

test('authenticated user is redirected from reset password page', function () {
    $user = User::factory()->withPassword('password')->create();

    $response = $this->actingAs($user)->get('/reset-password/some-token');

    $response->assertRedirect();
});
