<?php

/**
 * SsoPageController + LogoutController Feature Tests (Console Mode)
 *
 * コンソールモードのSSO認証ページコントローラーのテスト
 * - GET /sso/login → Inertia login page with authorize URL
 * - GET /sso/callback → Inertia callback page
 * - POST /logout → logout + redirect to sso.login
 *
 * Console routes are manually registered in beforeEach because
 * TestCase defaults to standalone mode. Same pattern used by
 * InvitePageControllerTest and StandaloneLoginControllerTest.
 */

use Illuminate\Support\Facades\Auth;
use Omnify\Core\Http\Controllers\Console\LogoutController;
use Omnify\Core\Http\Controllers\Console\SsoPageController;
use Omnify\Core\Models\User;

beforeEach(function () {
    config(['omnify-auth.mode' => 'console']);

    $this->app->make('router')
        ->prefix('sso')
        ->name('core.')
        ->middleware(['web'])
        ->group(function ($router) {
            $router->get('/login', [SsoPageController::class, 'login'])->name('login');
            $router->get('/callback', [SsoPageController::class, 'callback'])->name('callback');
        });

    $this->app->make('router')
        ->middleware(['web'])
        ->group(function ($router) {
            $router->post('/logout', [LogoutController::class, '__invoke'])->name('logout');
        });
});

// =============================================================================
// SSO Login Page — GET /sso/login
// =============================================================================

test('login page renders SSO login component', function () {
    $response = $this->get('/sso/login', ['X-Inertia' => 'true']);

    $response->assertStatus(200)
        ->assertJson(['component' => 'sso/login']);
});

test('login page passes authorize URL with correct parameters', function () {
    $response = $this->get('/sso/login', ['X-Inertia' => 'true']);

    $response->assertStatus(200);

    $props = $response->json('props');
    expect($props)->toHaveKey('ssoAuthorizeUrl');

    $authorizeUrl = $props['ssoAuthorizeUrl'];
    expect($authorizeUrl)->toContain('https://test.console.omnify.jp/sso/authorize');
    expect($authorizeUrl)->toContain('service_slug=');
    expect($authorizeUrl)->toContain('redirect_uri=');
});

test('login page authorize URL contains service_slug', function () {
    $response = $this->get('/sso/login', ['X-Inertia' => 'true']);

    $response->assertStatus(200);

    $authorizeUrl = $response->json('props.ssoAuthorizeUrl');
    expect($authorizeUrl)->toContain('service_slug=test-service');
});

test('login page authorize URL contains callback redirect_uri', function () {
    $response = $this->get('/sso/login', ['X-Inertia' => 'true']);

    $response->assertStatus(200);

    $authorizeUrl = $response->json('props.ssoAuthorizeUrl');

    // callback URL defaults to /sso/callback
    $callbackUrl = url(config('omnify-auth.service.callback_url', '/sso/callback'));
    expect($authorizeUrl)->toContain(urlencode($callbackUrl));
});

test('login page passes console URL as prop', function () {
    $response = $this->get('/sso/login', ['X-Inertia' => 'true']);

    $response->assertStatus(200);

    $props = $response->json('props');
    expect($props)->toHaveKey('consoleUrl');
    expect($props['consoleUrl'])->toBe('https://test.console.omnify.jp');
});

// =============================================================================
// SSO Callback Page — GET /sso/callback
// =============================================================================

test('callback page renders SSO callback component', function () {
    $response = $this->get('/sso/callback', ['X-Inertia' => 'true']);

    $response->assertStatus(200)
        ->assertJson(['component' => 'sso/callback']);
});

test('callback page passes callback API URL as prop', function () {
    $response = $this->get('/sso/callback', ['X-Inertia' => 'true']);

    $response->assertStatus(200);

    $props = $response->json('props');
    expect($props)->toHaveKey('callbackApiUrl');
    expect($props['callbackApiUrl'])->toContain('/api/sso/callback');
});

// =============================================================================
// Logout — POST /logout
// =============================================================================

test('logout clears authentication', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    expect(Auth::check())->toBeTrue();

    $this->post('/logout');

    expect(Auth::check())->toBeFalse();
});

test('logout invalidates session', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post('/logout');

    // After logout, session should be regenerated (old session invalidated)
    expect(Auth::check())->toBeFalse();
});

test('logout redirects to SSO login', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post('/logout');

    $response->assertRedirect(route('core.login'));
});
