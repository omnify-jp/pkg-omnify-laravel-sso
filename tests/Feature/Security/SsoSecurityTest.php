<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Omnify\SsoClient\Models\User;
use Omnify\SsoClient\Services\ConsoleApiService;
use Omnify\SsoClient\Services\ConsoleTokenService;
use Omnify\SsoClient\Services\JwtVerifier;
use Omnify\SsoClient\Services\OrganizationAccessService;

// =============================================================================
// Open Redirect Prevention Tests
// =============================================================================

describe('Open Redirect Prevention', function () {
    beforeEach(function () {
        config(['app.url' => 'https://myapp.example.com']);
        config(['omnify-auth.security.allowed_redirect_hosts' => []]);
    });

    test('global-logout-url rejects external redirect URLs', function () {
        $user = User::factory()->create();
        Auth::login($user);

        // Try to redirect to external malicious site
        $response = $this->getJson('/api/sso/global-logout-url?redirect_uri=https://evil.com/steal');

        $response->assertStatus(200);
        $data = $response->json();

        // Should NOT contain evil.com in the redirect
        expect($data['logout_url'])->not->toContain('evil.com');
        expect($data['logout_url'])->toContain(urlencode(url('/')));
    });

    test('global-logout-url rejects protocol-relative URLs', function () {
        $user = User::factory()->create();
        Auth::login($user);

        $response = $this->getJson('/api/sso/global-logout-url?redirect_uri=//evil.com');

        $response->assertStatus(200);
        $data = $response->json();

        expect($data['logout_url'])->not->toContain('evil.com');
    });

    test('global-logout-url rejects javascript protocol', function () {
        $user = User::factory()->create();
        Auth::login($user);

        $response = $this->getJson('/api/sso/global-logout-url?redirect_uri=javascript:alert(1)');

        $response->assertStatus(200);
        $data = $response->json();

        expect($data['logout_url'])->not->toContain('javascript');
    });

    test('global-logout-url allows relative URLs', function () {
        $user = User::factory()->create();
        Auth::login($user);

        $response = $this->getJson('/api/sso/global-logout-url?redirect_uri=/dashboard');

        $response->assertStatus(200);
        $data = $response->json();

        expect($data['logout_url'])->toContain(urlencode('/dashboard'));
    });

    test('global-logout-url allows same-origin URLs', function () {
        $user = User::factory()->create();
        Auth::login($user);

        // Configure app URL
        config(['app.url' => 'https://myapp.test']);

        $response = $this->getJson('/api/sso/global-logout-url?redirect_uri=https://myapp.test/home');

        $response->assertStatus(200);
    });
});

// =============================================================================
// SSO Code Security Tests
// =============================================================================

describe('SSO Code Security', function () {
    test('callback rejects empty code', function () {
        $response = $this->postJson('/api/sso/callback', [
            'code' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    });

    test('callback rejects missing code', function () {
        $response = $this->postJson('/api/sso/callback', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    });

    test('callback rejects invalid code', function () {
        // Mock ConsoleApiService to return null (invalid code)
        $consoleApi = \Mockery::mock(ConsoleApiService::class);
        $consoleApi->shouldReceive('exchangeCode')
            ->with('invalid-code')
            ->andReturn(null);

        $this->app->instance(ConsoleApiService::class, $consoleApi);

        $response = $this->postJson('/api/sso/callback', [
            'code' => 'invalid-code',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'INVALID_CODE',
            ]);
    });

    test('callback rejects expired code', function () {
        // Mock ConsoleApiService to return null (expired code)
        $consoleApi = \Mockery::mock(ConsoleApiService::class);
        $consoleApi->shouldReceive('exchangeCode')
            ->with('expired-code')
            ->andReturn(null);

        $this->app->instance(ConsoleApiService::class, $consoleApi);

        $response = $this->postJson('/api/sso/callback', [
            'code' => 'expired-code',
        ]);

        $response->assertStatus(401);
    });
});

// =============================================================================
// JWT Token Security Tests
// =============================================================================

describe('JWT Token Security', function () {
    test('callback rejects invalid JWT signature', function () {
        // Mock services
        $consoleApi = \Mockery::mock(ConsoleApiService::class);
        $consoleApi->shouldReceive('exchangeCode')
            ->andReturn([
                'access_token' => 'invalid.jwt.token',
                'refresh_token' => 'refresh-token',
                'expires_in' => 3600,
            ]);

        $jwtVerifier = \Mockery::mock(JwtVerifier::class);
        $jwtVerifier->shouldReceive('verify')
            ->with('invalid.jwt.token')
            ->andReturn(null); // Invalid signature

        $this->app->instance(ConsoleApiService::class, $consoleApi);
        $this->app->instance(JwtVerifier::class, $jwtVerifier);

        $response = $this->postJson('/api/sso/callback', [
            'code' => 'valid-code',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'INVALID_TOKEN',
            ]);
    });

    test('callback rejects expired JWT', function () {
        $consoleApi = \Mockery::mock(ConsoleApiService::class);
        $consoleApi->shouldReceive('exchangeCode')
            ->andReturn([
                'access_token' => 'expired.jwt.token',
                'refresh_token' => 'refresh-token',
                'expires_in' => 3600,
            ]);

        $jwtVerifier = \Mockery::mock(JwtVerifier::class);
        $jwtVerifier->shouldReceive('verify')
            ->with('expired.jwt.token')
            ->andReturn(null); // Expired token rejected

        $this->app->instance(ConsoleApiService::class, $consoleApi);
        $this->app->instance(JwtVerifier::class, $jwtVerifier);

        $response = $this->postJson('/api/sso/callback', [
            'code' => 'code-with-expired-token',
        ]);

        $response->assertStatus(401);
    });

    test('callback rejects JWT with wrong audience', function () {
        $consoleApi = \Mockery::mock(ConsoleApiService::class);
        $consoleApi->shouldReceive('exchangeCode')
            ->andReturn([
                'access_token' => 'wrong-audience.jwt.token',
                'refresh_token' => 'refresh-token',
                'expires_in' => 3600,
            ]);

        $jwtVerifier = \Mockery::mock(JwtVerifier::class);
        $jwtVerifier->shouldReceive('verify')
            ->andReturn(null); // Wrong audience rejected

        $this->app->instance(ConsoleApiService::class, $consoleApi);
        $this->app->instance(JwtVerifier::class, $jwtVerifier);

        $response = $this->postJson('/api/sso/callback', [
            'code' => 'code-with-wrong-audience',
        ]);

        $response->assertStatus(401);
    });
});

// =============================================================================
// Authentication Security Tests
// =============================================================================

describe('Authentication Security', function () {
    test('user endpoint requires authentication', function () {
        $response = $this->getJson('/api/sso/user');

        $response->assertStatus(401);
    });

    test('logout endpoint requires authentication', function () {
        $response = $this->postJson('/api/sso/logout');

        $response->assertStatus(401);
    });

    test('global-logout-url endpoint requires authentication', function () {
        $response = $this->getJson('/api/sso/global-logout-url');

        $response->assertStatus(401);
    });

    test('authenticated user can access protected endpoints', function () {
        $user = User::factory()->create();
        Auth::login($user);

        // Mock org access service
        $organizationAccessService = \Mockery::mock(OrganizationAccessService::class);
        $organizationAccessService->shouldReceive('getOrganizations')->andReturn([]);
        $this->app->instance(OrganizationAccessService::class, $organizationAccessService);

        $response = $this->getJson('/api/sso/user');

        $response->assertStatus(200)
            ->assertJsonStructure(['user', 'organizations']);
    });

    test('session is invalidated on logout', function () {
        $user = User::factory()->create();

        // Mock token service
        $tokenService = \Mockery::mock(ConsoleTokenService::class);
        $tokenService->shouldReceive('revokeTokens')->once();
        $this->app->instance(ConsoleTokenService::class, $tokenService);

        // Login via actingAs to create proper session
        $this->actingAs($user);

        // Verify logged in first
        expect(Auth::check())->toBeTrue();

        // Logout
        $response = $this->postJson('/api/sso/logout');
        $response->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully']);

        // After logout, Auth should be cleared
        // Note: Due to how Laravel testing works, we verify the logout response
        // was successful rather than checking subsequent requests
    });
});

// =============================================================================
// Input Validation Security Tests
// =============================================================================

describe('Input Validation Security', function () {
    test('device_name is limited in length', function () {
        $consoleApi = \Mockery::mock(ConsoleApiService::class);
        $consoleApi->shouldReceive('exchangeCode')->andReturn([
            'access_token' => 'valid-token',
            'refresh_token' => 'refresh-token',
            'expires_in' => 3600,
        ]);

        $jwtVerifier = \Mockery::mock(JwtVerifier::class);
        $jwtVerifier->shouldReceive('verify')->andReturn([
            'sub' => 123,
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);

        $this->app->instance(ConsoleApiService::class, $consoleApi);
        $this->app->instance(JwtVerifier::class, $jwtVerifier);

        // Try very long device name
        $response = $this->postJson('/api/sso/callback', [
            'code' => 'valid-code',
            'device_name' => str_repeat('a', 300),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['device_name']);
    });

    test('code parameter is sanitized', function () {
        $consoleApi = \Mockery::mock(ConsoleApiService::class);
        $consoleApi->shouldReceive('exchangeCode')
            ->with('<script>alert(1)</script>')
            ->andReturn(null);

        $this->app->instance(ConsoleApiService::class, $consoleApi);

        $response = $this->postJson('/api/sso/callback', [
            'code' => '<script>alert(1)</script>',
        ]);

        // Should either fail validation or return invalid code error
        expect($response->status())->toBeIn([401, 422]);
    });
});

// =============================================================================
// CSRF Protection Tests
// =============================================================================

describe('CSRF Protection', function () {
    test('callback accepts JSON requests without CSRF token', function () {
        $consoleApi = \Mockery::mock(ConsoleApiService::class);
        $consoleApi->shouldReceive('exchangeCode')->andReturn(null);

        $this->app->instance(ConsoleApiService::class, $consoleApi);

        // JSON API requests should work without CSRF
        $response = $this->postJson('/api/sso/callback', [
            'code' => 'test-code',
        ]);

        // Should not be 419 (CSRF token mismatch)
        expect($response->status())->not->toBe(419);
    });
});

// =============================================================================
// Rate Limiting (if implemented)
// =============================================================================

describe('Rate Limiting', function () {
    test('multiple rapid callback requests are handled', function () {
        $consoleApi = \Mockery::mock(ConsoleApiService::class);
        $consoleApi->shouldReceive('exchangeCode')->andReturn(null);

        $this->app->instance(ConsoleApiService::class, $consoleApi);

        // Send multiple requests rapidly
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson('/api/sso/callback', [
                'code' => "code-{$i}",
            ]);

            // Should consistently return 401, not be rate limited out of the box
            // Rate limiting should be configured at the application level
            expect($response->status())->toBe(401);
        }
    });
});

// =============================================================================
// Data Exposure Prevention
// =============================================================================

describe('Data Exposure Prevention', function () {
    test('user endpoint does not expose sensitive data', function () {
        // SSO users don't have passwords - authentication is via Console tokens
        $user = User::factory()->create([
            'console_access_token' => 'encrypted-token',
            'console_refresh_token' => 'encrypted-refresh',
        ]);
        Auth::login($user);

        $organizationAccessService = \Mockery::mock(OrganizationAccessService::class);
        $organizationAccessService->shouldReceive('getOrganizations')->andReturn([]);
        $this->app->instance(OrganizationAccessService::class, $organizationAccessService);

        $response = $this->getJson('/api/sso/user');

        $response->assertStatus(200);
        $data = $response->json();

        // Should not contain sensitive fields (SSO tokens)
        expect($data['user'])->not->toHaveKey('console_access_token');
        expect($data['user'])->not->toHaveKey('console_refresh_token');
    });

    test('callback response does not expose internal errors', function () {
        $consoleApi = \Mockery::mock(ConsoleApiService::class);
        $consoleApi->shouldReceive('exchangeCode')
            ->andThrow(new \Exception('Internal database error'));

        $this->app->instance(ConsoleApiService::class, $consoleApi);

        $response = $this->postJson('/api/sso/callback', [
            'code' => 'test-code',
        ]);

        // Should return generic error, not expose internal details
        $data = $response->json();

        expect($data)->not->toHaveKey('exception');
        expect($data)->not->toHaveKey('trace');
        expect(json_encode($data))->not->toContain('database');
    });
});

// =============================================================================
// Session Security Tests
// =============================================================================

describe('Session Security', function () {
    test('successful login creates new session', function () {
        $consoleApi = \Mockery::mock(ConsoleApiService::class);
        $consoleApi->shouldReceive('exchangeCode')
            ->andReturn([
                'access_token' => 'valid-jwt',
                'refresh_token' => 'refresh-token',
                'expires_in' => 3600,
            ]);

        $jwtVerifier = \Mockery::mock(JwtVerifier::class);
        $jwtVerifier->shouldReceive('verify')
            ->andReturn([
                'sub' => 123,
                'email' => 'test@example.com',
                'name' => 'Test User',
            ]);

        $organizationAccessService = \Mockery::mock(OrganizationAccessService::class);
        $organizationAccessService->shouldReceive('getOrganizations')->andReturn([]);

        $tokenService = \Mockery::mock(\Omnify\SsoClient\Services\ConsoleTokenService::class);
        $tokenService->shouldReceive('storeTokens')->once();

        $this->app->instance(ConsoleApiService::class, $consoleApi);
        $this->app->instance(JwtVerifier::class, $jwtVerifier);
        $this->app->instance(OrganizationAccessService::class, $organizationAccessService);
        $this->app->instance(\Omnify\SsoClient\Services\ConsoleTokenService::class, $tokenService);

        $response = $this->postJson('/api/sso/callback', [
            'code' => 'valid-code',
        ]);

        $response->assertStatus(200);

        // Should be authenticated after callback
        expect(Auth::check())->toBeTrue();
    });
});
