<?php

/**
 * ConsoleApiService Unit Tests
 *
 * Console APIサービスのテスト
 */

use Illuminate\Support\Facades\Http;
use Omnify\SsoClient\Services\ConsoleApiService;

// =============================================================================
// Instantiation Tests - インスタンス化のテスト
// =============================================================================

test('ConsoleApiService can be instantiated', function () {
    $service = new ConsoleApiService(
        consoleUrl: 'https://console.example.com',
        serviceSlug: 'test-service'
    );

    expect($service)->toBeInstanceOf(ConsoleApiService::class);
});

test('ConsoleApiService returns console URL', function () {
    $service = new ConsoleApiService(
        consoleUrl: 'https://console.example.com',
        serviceSlug: 'test-service'
    );

    expect($service->getConsoleUrl())->toBe('https://console.example.com');
});

test('ConsoleApiService returns service slug', function () {
    $service = new ConsoleApiService(
        consoleUrl: 'https://console.example.com',
        serviceSlug: 'my-service'
    );

    expect($service->getServiceSlug())->toBe('my-service');
});

// =============================================================================
// Code Exchange Tests - コード交換のテスト
// =============================================================================

test('exchangeCode returns tokens on success', function () {
    Http::fake([
        '*/api/sso/token' => Http::response([
            'access_token' => 'access-token-123',
            'refresh_token' => 'refresh-token-456',
            'expires_in' => 3600,
        ], 200),
    ]);

    $service = new ConsoleApiService(
        consoleUrl: 'https://console.example.com',
        serviceSlug: 'test-service'
    );

    $result = $service->exchangeCode('valid-code');

    expect($result)->toBeArray()
        ->and($result['access_token'])->toBe('access-token-123')
        ->and($result['refresh_token'])->toBe('refresh-token-456')
        ->and($result['expires_in'])->toBe(3600);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://console.example.com/api/sso/token'
            && $request['code'] === 'valid-code'
            && $request['service_slug'] === 'test-service';
    });
});

test('exchangeCode throws exception on invalid code', function () {
    Http::fake([
        '*/api/sso/token' => Http::response([
            'error' => 'INVALID_CODE',
            'message' => 'The code is invalid or expired',
        ], 401),
    ]);

    $service = new ConsoleApiService(
        consoleUrl: 'https://console.example.com',
        serviceSlug: 'test-service',
        retry: 0 // リトライを無効化
    );

    $service->exchangeCode('invalid-code');
})->throws(\Exception::class);

// =============================================================================
// Token Refresh Tests - トークン更新のテスト
// =============================================================================

test('refreshToken returns new tokens on success', function () {
    Http::fake([
        '*/api/sso/refresh' => Http::response([
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 3600,
        ], 200),
    ]);

    $service = new ConsoleApiService(
        consoleUrl: 'https://console.example.com',
        serviceSlug: 'test-service'
    );

    $result = $service->refreshToken('old-refresh-token');

    expect($result)->toBeArray()
        ->and($result['access_token'])->toBe('new-access-token');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://console.example.com/api/sso/refresh'
            && $request['refresh_token'] === 'old-refresh-token';
    });
});

test('refreshToken throws exception on expired refresh token', function () {
    Http::fake([
        '*/api/sso/refresh' => Http::response([
            'error' => 'TOKEN_EXPIRED',
            'message' => 'Refresh token has expired',
        ], 401),
    ]);

    $service = new ConsoleApiService(
        consoleUrl: 'https://console.example.com',
        serviceSlug: 'test-service',
        retry: 0 // リトライを無効化
    );

    $service->refreshToken('expired-token');
})->throws(\Exception::class);

// =============================================================================
// Token Revoke Tests - トークン無効化のテスト
// =============================================================================

test('revokeToken returns true on success', function () {
    Http::fake([
        '*/api/sso/revoke' => Http::response(['message' => 'Token revoked'], 200),
    ]);

    $service = new ConsoleApiService(
        consoleUrl: 'https://console.example.com',
        serviceSlug: 'test-service'
    );

    $result = $service->revokeToken('some-refresh-token');

    expect($result)->toBeTrue();
});

test('revokeToken returns false on failure', function () {
    Http::fake([
        '*/api/sso/revoke' => Http::response(['error' => 'INVALID_TOKEN'], 400),
    ]);

    $service = new ConsoleApiService(
        consoleUrl: 'https://console.example.com',
        serviceSlug: 'test-service',
        retry: 0 // リトライを無効化
    );

    $result = $service->revokeToken('invalid-token');

    expect($result)->toBeFalse();
});

// =============================================================================
// Access Check Tests - アクセスチェックのテスト
// =============================================================================

test('getAccess returns organization access info', function () {
    Http::fake([
        '*/api/sso/access*' => Http::response([
            'organization_id' => 1,
            'organization_slug' => 'my-company',
            'organization_role' => 'admin',
            'service_role' => 'admin',
            'service_role_level' => 100,
        ], 200),
    ]);

    $service = new ConsoleApiService(
        consoleUrl: 'https://console.example.com',
        serviceSlug: 'test-service'
    );

    $result = $service->getAccess('access-token', 'my-company');

    expect($result)->toBeArray()
        ->and($result['organization_slug'])->toBe('my-company')
        ->and($result['service_role'])->toBe('admin');
});

test('getAccess returns null when access denied', function () {
    Http::fake([
        '*/api/sso/access*' => Http::response([
            'error' => 'ACCESS_DENIED',
            'message' => 'No access to this organization',
        ], 403),
    ]);

    $service = new ConsoleApiService(
        consoleUrl: 'https://console.example.com',
        serviceSlug: 'test-service',
        retry: 0 // リトライを無効化
    );

    $result = $service->getAccess('access-token', 'forbidden-org');

    expect($result)->toBeNull();
});

// =============================================================================
// Organizations Tests - 組織一覧のテスト
// =============================================================================

test('getOrganizations returns list of organizations', function () {
    Http::fake([
        '*/api/sso/organizations' => Http::response([
            [
                'organization_id' => 1,
                'organization_slug' => 'company-a',
                'organization_name' => 'Company A',
                'organization_role' => 'admin',
                'service_role' => 'admin',
            ],
            [
                'organization_id' => 2,
                'organization_slug' => 'company-b',
                'organization_name' => 'Company B',
                'organization_role' => 'member',
                'service_role' => 'member',
            ],
        ], 200),
    ]);

    $service = new ConsoleApiService(
        consoleUrl: 'https://console.example.com',
        serviceSlug: 'test-service'
    );

    $result = $service->getOrganizations('access-token');

    expect($result)->toBeArray()
        ->and($result)->toHaveCount(2)
        ->and($result[0]['organization_slug'])->toBe('company-a')
        ->and($result[1]['organization_slug'])->toBe('company-b');
});

test('getOrganizations throws exception when unauthenticated', function () {
    Http::fake([
        '*/api/sso/organizations' => Http::response([
            'error' => 'UNAUTHENTICATED',
            'message' => 'Invalid access token',
        ], 401),
    ]);

    $service = new ConsoleApiService(
        consoleUrl: 'https://console.example.com',
        serviceSlug: 'test-service',
        retry: 0 // リトライを無効化
    );

    $service->getOrganizations('invalid-token');
})->throws(\Exception::class);

// =============================================================================
// Teams Tests - チーム一覧のテスト
// =============================================================================

test('getUserTeams returns list of user teams', function () {
    Http::fake([
        '*/api/sso/teams*' => Http::response([
            'teams' => [
                [
                    'id' => 1,
                    'name' => 'Engineering',
                    'path' => '/engineering',
                    'parent_id' => null,
                    'is_leader' => true,
                ],
                [
                    'id' => 2,
                    'name' => 'Frontend',
                    'path' => '/engineering/frontend',
                    'parent_id' => 1,
                    'is_leader' => false,
                ],
            ],
        ], 200),
    ]);

    $service = new ConsoleApiService(
        consoleUrl: 'https://console.example.com',
        serviceSlug: 'test-service'
    );

    $result = $service->getUserTeams('access-token', 'my-company');

    expect($result)->toBeArray()
        ->and($result)->toHaveCount(2)
        ->and($result[0]['name'])->toBe('Engineering')
        ->and($result[0]['is_leader'])->toBeTrue();
});

test('getUserTeams returns empty array when access denied', function () {
    Http::fake([
        '*/api/sso/teams*' => Http::response([
            'error' => 'ACCESS_DENIED',
        ], 403),
    ]);

    $service = new ConsoleApiService(
        consoleUrl: 'https://console.example.com',
        serviceSlug: 'test-service',
        retry: 0 // リトライを無効化
    );

    $result = $service->getUserTeams('access-token', 'forbidden-org');

    expect($result)->toBe([]);
});

// =============================================================================
// JWKS Tests - JWKSのテスト
// =============================================================================

test('getJwks returns JWKS data', function () {
    Http::fake([
        '*/.well-known/jwks.json' => Http::response([
            'keys' => [
                [
                    'kty' => 'RSA',
                    'kid' => 'key-1',
                    'n' => 'modulus-data',
                    'e' => 'AQAB',
                ],
            ],
        ], 200),
    ]);

    $service = new ConsoleApiService(
        consoleUrl: 'https://console.example.com',
        serviceSlug: 'test-service'
    );

    $result = $service->getJwks();

    expect($result)->toBeArray()
        ->and($result['keys'])->toHaveCount(1)
        ->and($result['keys'][0]['kid'])->toBe('key-1');
});

test('getJwks throws exception on server error', function () {
    Http::fake([
        '*/.well-known/jwks.json' => Http::response('Server Error', 500),
    ]);

    $service = new ConsoleApiService(
        consoleUrl: 'https://console.example.com',
        serviceSlug: 'test-service',
        retry: 0 // リトライを無効化
    );

    $service->getJwks();
})->throws(\Exception::class);

// =============================================================================
// Error Handling Tests - エラーハンドリングのテスト
// =============================================================================

test('handles 500 server errors', function () {
    Http::fake([
        '*/api/sso/token' => Http::response([
            'error' => 'SERVER_ERROR',
            'message' => 'Internal server error',
        ], 500),
    ]);

    $service = new ConsoleApiService(
        consoleUrl: 'https://console.example.com',
        serviceSlug: 'test-service',
        retry: 0 // リトライを無効化
    );

    $service->exchangeCode('some-code');
})->throws(\Exception::class);

test('handles network timeout', function () {
    Http::fake([
        '*/api/sso/token' => fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection timed out'),
    ]);

    $service = new ConsoleApiService(
        consoleUrl: 'https://console.example.com',
        serviceSlug: 'test-service',
        timeout: 1,
        retry: 0
    );

    $service->exchangeCode('some-code');
})->throws(\Illuminate\Http\Client\ConnectionException::class);
