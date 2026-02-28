<?php

/**
 * SsoCallbackController is_standalone Tests
 *
 * SSO コールバック時に is_standalone = false が設定されることをテスト
 * Tests that SSO callback creates users with is_standalone = false.
 *
 * NOTE: /api/sso/callback route is always loaded (both modes), so default
 * TestCase (standalone) is fine here. We mock all DI services.
 */

use Omnify\Core\Models\User;
use Omnify\Core\Services\ConsoleApiService;
use Omnify\Core\Services\ConsoleTokenService;
use Omnify\Core\Services\JwtVerifier;
use Omnify\Core\Services\OrganizationAccessService;
use Omnify\Core\Support\SsoLogger;

// =============================================================================
// Helper to mock full SSO callback flow
// =============================================================================

function mockSsoServices(array $claims = [], array $tokens = []): void
{
    $defaultClaims = [
        'sub' => 'console-user-uuid-001',
        'email' => 'sso-user@example.com',
        'name' => 'SSO User',
    ];

    $defaultTokens = [
        'access_token' => 'jwt-access-token',
        'refresh_token' => 'jwt-refresh-token',
        'expires_in' => 3600,
    ];

    $claims = array_merge($defaultClaims, $claims);
    $tokens = array_merge($defaultTokens, $tokens);

    $consoleApi = \Mockery::mock(ConsoleApiService::class);
    $consoleApi->shouldReceive('exchangeCode')
        ->with('valid-code')
        ->andReturn($tokens);

    $jwtVerifier = \Mockery::mock(JwtVerifier::class);
    $jwtVerifier->shouldReceive('verify')
        ->with($tokens['access_token'])
        ->andReturn($claims);

    // storeTokens must actually save the user — the controller relies on this
    $tokenService = \Mockery::mock(ConsoleTokenService::class);
    $tokenService->shouldReceive('storeTokens')
        ->once()
        ->andReturnUsing(function ($user, $tkns) {
            $user->save();
        });

    $orgAccessService = \Mockery::mock(OrganizationAccessService::class);
    $orgAccessService->shouldReceive('getOrganizations')
        ->andReturn([]);

    $logger = \Mockery::mock(SsoLogger::class);
    $logger->shouldReceive('codeExchange')->andReturnNull();
    $logger->shouldReceive('jwtVerification')->andReturnNull();
    $logger->shouldReceive('authAttempt')->andReturnNull();

    app()->instance(ConsoleApiService::class, $consoleApi);
    app()->instance(JwtVerifier::class, $jwtVerifier);
    app()->instance(ConsoleTokenService::class, $tokenService);
    app()->instance(OrganizationAccessService::class, $orgAccessService);
    app()->instance(SsoLogger::class, $logger);
}

// =============================================================================
// SSO Callback creates user with is_standalone = false
// =============================================================================

describe('SSO callback sets is_standalone', function () {
    test('new user created via SSO has is_standalone = false', function () {
        mockSsoServices();

        $response = $this->postJson('/api/sso/callback', [
            'code' => 'valid-code',
        ]);

        $response->assertOk();

        $user = User::where('console_user_id', 'console-user-uuid-001')->first();

        expect($user)->not->toBeNull()
            ->and($user->is_standalone)->toBeFalse()
            ->and($user->email)->toBe('sso-user@example.com');
    });

    test('existing user retains is_standalone after SSO login', function () {
        // Pre-create user with is_standalone = false
        $existingUser = User::factory()->console()->create([
            'console_user_id' => 'console-user-uuid-001',
            'email' => 'old@example.com',
            'name' => 'Old Name',
        ]);

        mockSsoServices();

        $response = $this->postJson('/api/sso/callback', [
            'code' => 'valid-code',
        ]);

        $response->assertOk();

        $existingUser->refresh();
        expect($existingUser->is_standalone)->toBeFalse()
            ->and($existingUser->email)->toBe('sso-user@example.com')
            ->and($existingUser->name)->toBe('SSO User');
    });

    test('SSO user is not visible in standalone mode queries', function () {
        mockSsoServices();

        $this->postJson('/api/sso/callback', ['code' => 'valid-code'])->assertOk();

        // SSO user should not appear in standalone scope
        expect(User::standalone()->count())->toBe(0)
            ->and(User::console()->count())->toBe(1);

        config()->set('omnify-auth.mode', 'standalone');
        expect(User::currentMode()->count())->toBe(0);

        config()->set('omnify-auth.mode', 'console');
        expect(User::currentMode()->count())->toBe(1);
    });
});
