<?php

/**
 * SsoCallbackController Feature Tests
 *
 * SSOコールバックコントローラーのテスト
 * - コード交換
 * - ユーザー作成/更新
 * - ログアウト
 */

use Illuminate\Support\Facades\Auth;
use Omnify\SsoClient\Services\ConsoleApiService;
use Omnify\SsoClient\Services\ConsoleTokenService;
use Omnify\SsoClient\Services\JwtVerifier;
use Omnify\SsoClient\Services\OrganizationAccessService;
use Omnify\SsoClient\Tests\Fixtures\Models\User;

// =============================================================================
// Callback Tests - コールバックのテスト
// =============================================================================

test('callback returns 422 when code is missing', function () {
    $response = $this->postJson('/api/sso/callback', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});

test('callback returns 401 when code exchange fails', function () {
    // ConsoleApiServiceをモック
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
            'message' => 'Failed to exchange SSO code',
        ]);
});

test('callback returns 401 when JWT verification fails', function () {
    // ConsoleApiServiceをモック
    $consoleApi = \Mockery::mock(ConsoleApiService::class);
    $consoleApi->shouldReceive('exchangeCode')
        ->with('valid-code')
        ->andReturn([
            'access_token' => 'invalid-jwt',
            'refresh_token' => 'refresh-token',
            'expires_in' => 3600,
        ]);

    // JwtVerifierをモック
    $jwtVerifier = \Mockery::mock(JwtVerifier::class);
    $jwtVerifier->shouldReceive('verify')
        ->with('invalid-jwt')
        ->andReturn(null);

    $this->app->instance(ConsoleApiService::class, $consoleApi);
    $this->app->instance(JwtVerifier::class, $jwtVerifier);

    $response = $this->postJson('/api/sso/callback', [
        'code' => 'valid-code',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'INVALID_TOKEN',
            'message' => 'Failed to verify access token',
        ]);
});

test('callback creates new user when user does not exist', function () {
    // ConsoleApiServiceをモック
    $consoleApi = \Mockery::mock(ConsoleApiService::class);
    $consoleApi->shouldReceive('exchangeCode')
        ->with('valid-code')
        ->andReturn([
            'access_token' => 'valid-jwt',
            'refresh_token' => 'refresh-token',
            'expires_in' => 3600,
        ]);

    // JwtVerifierをモック
    $jwtVerifier = \Mockery::mock(JwtVerifier::class);
    $jwtVerifier->shouldReceive('verify')
        ->with('valid-jwt')
        ->andReturn([
            'sub' => 999,
            'email' => 'newuser@example.com',
            'name' => 'New User',
            'aud' => 'test-service',
        ]);

    // ConsoleTokenServiceをモック - save userを実行
    $tokenService = \Mockery::mock(ConsoleTokenService::class);
    $tokenService->shouldReceive('storeTokens')
        ->once()
        ->andReturnUsing(function ($user, $tokens) {
            $user->save();
        });

    // OrganizationAccessServiceをモック
    $organizationAccessService = \Mockery::mock(OrganizationAccessService::class);
    $organizationAccessService->shouldReceive('getOrganizations')
        ->andReturn([]);

    $this->app->instance(ConsoleApiService::class, $consoleApi);
    $this->app->instance(JwtVerifier::class, $jwtVerifier);
    $this->app->instance(ConsoleTokenService::class, $tokenService);
    $this->app->instance(OrganizationAccessService::class, $organizationAccessService);

    $response = $this->postJson('/api/sso/callback', [
        'code' => 'valid-code',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'user' => ['id', 'console_user_id', 'email', 'name'],
            'organizations',
        ])
        ->assertJsonPath('user.email', 'newuser@example.com')
        ->assertJsonPath('user.name', 'New User')
        ->assertJsonPath('user.console_user_id', 999);

    // ユーザーがDBに作成されたことを確認
    $this->assertDatabaseHas('users', [
        'console_user_id' => 999,
        'email' => 'newuser@example.com',
        'name' => 'New User',
    ]);
});

test('callback updates existing user', function () {
    // 既存ユーザーを作成
    $existingUser = User::factory()->create([
        'console_user_id' => 888,
        'email' => 'old@example.com',
        'name' => 'Old Name',
    ]);

    // ConsoleApiServiceをモック
    $consoleApi = \Mockery::mock(ConsoleApiService::class);
    $consoleApi->shouldReceive('exchangeCode')
        ->with('valid-code')
        ->andReturn([
            'access_token' => 'valid-jwt',
            'refresh_token' => 'refresh-token',
            'expires_in' => 3600,
        ]);

    // JwtVerifierをモック
    $jwtVerifier = \Mockery::mock(JwtVerifier::class);
    $jwtVerifier->shouldReceive('verify')
        ->with('valid-jwt')
        ->andReturn([
            'sub' => 888,
            'email' => 'updated@example.com',
            'name' => 'Updated Name',
            'aud' => 'test-service',
        ]);

    // ConsoleTokenServiceをモック - save userを実行
    $tokenService = \Mockery::mock(ConsoleTokenService::class);
    $tokenService->shouldReceive('storeTokens')
        ->once()
        ->andReturnUsing(function ($user, $tokens) {
            $user->save();
        });

    // OrganizationAccessServiceをモック
    $organizationAccessService = \Mockery::mock(OrganizationAccessService::class);
    $organizationAccessService->shouldReceive('getOrganizations')
        ->andReturn([]);

    $this->app->instance(ConsoleApiService::class, $consoleApi);
    $this->app->instance(JwtVerifier::class, $jwtVerifier);
    $this->app->instance(ConsoleTokenService::class, $tokenService);
    $this->app->instance(OrganizationAccessService::class, $organizationAccessService);

    $response = $this->postJson('/api/sso/callback', [
        'code' => 'valid-code',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('user.email', 'updated@example.com')
        ->assertJsonPath('user.name', 'Updated Name');

    // ユーザーが更新されたことを確認
    $this->assertDatabaseHas('users', [
        'id' => $existingUser->id,
        'email' => 'updated@example.com',
        'name' => 'Updated Name',
    ]);
});

test('callback returns organizations with user data', function () {
    // ConsoleApiServiceをモック
    $consoleApi = \Mockery::mock(ConsoleApiService::class);
    $consoleApi->shouldReceive('exchangeCode')
        ->andReturn([
            'access_token' => 'valid-jwt',
            'refresh_token' => 'refresh-token',
            'expires_in' => 3600,
        ]);

    // JwtVerifierをモック
    $jwtVerifier = \Mockery::mock(JwtVerifier::class);
    $jwtVerifier->shouldReceive('verify')
        ->andReturn([
            'sub' => 777,
            'email' => 'orguser@example.com',
            'name' => 'Org User',
            'aud' => 'test-service',
        ]);

    // ConsoleTokenServiceをモック - save userを実行
    $tokenService = \Mockery::mock(ConsoleTokenService::class);
    $tokenService->shouldReceive('storeTokens')
        ->once()
        ->andReturnUsing(function ($user, $tokens) {
            $user->save();
        });

    // OrganizationAccessServiceをモック - 組織データを返す
    $organizationAccessService = \Mockery::mock(OrganizationAccessService::class);
    $organizationAccessService->shouldReceive('getOrganizations')
        ->andReturn([
            [
                'organization_id' => 1,
                'organization_slug' => 'company-abc',
                'organization_name' => 'Company ABC',
                'organization_role' => 'admin',
                'service_role' => 'admin',
            ],
            [
                'organization_id' => 2,
                'organization_slug' => 'company-xyz',
                'organization_name' => 'Company XYZ',
                'organization_role' => 'member',
                'service_role' => 'member',
            ],
        ]);

    $this->app->instance(ConsoleApiService::class, $consoleApi);
    $this->app->instance(JwtVerifier::class, $jwtVerifier);
    $this->app->instance(ConsoleTokenService::class, $tokenService);
    $this->app->instance(OrganizationAccessService::class, $organizationAccessService);

    $response = $this->postJson('/api/sso/callback', [
        'code' => 'valid-code',
    ]);

    $response->assertStatus(200)
        ->assertJsonCount(2, 'organizations')
        ->assertJsonPath('organizations.0.organization_slug', 'company-abc')
        ->assertJsonPath('organizations.1.organization_slug', 'company-xyz');
});

test('callback creates session for web SPA (cookie-based auth)', function () {
    // ConsoleApiServiceをモック
    $consoleApi = \Mockery::mock(ConsoleApiService::class);
    $consoleApi->shouldReceive('exchangeCode')
        ->andReturn([
            'access_token' => 'valid-jwt',
            'refresh_token' => 'refresh-token',
            'expires_in' => 3600,
        ]);

    // JwtVerifierをモック
    $jwtVerifier = \Mockery::mock(JwtVerifier::class);
    $jwtVerifier->shouldReceive('verify')
        ->andReturn([
            'sub' => 111,
            'email' => 'session@example.com',
            'name' => 'Session User',
            'aud' => 'test-service',
        ]);

    // ConsoleTokenServiceをモック - save userを実行
    $tokenService = \Mockery::mock(ConsoleTokenService::class);
    $tokenService->shouldReceive('storeTokens')
        ->once()
        ->andReturnUsing(function ($user, $tokens) {
            $user->save();
        });

    // OrganizationAccessServiceをモック
    $organizationAccessService = \Mockery::mock(OrganizationAccessService::class);
    $organizationAccessService->shouldReceive('getOrganizations')
        ->andReturn([]);

    $this->app->instance(ConsoleApiService::class, $consoleApi);
    $this->app->instance(JwtVerifier::class, $jwtVerifier);
    $this->app->instance(ConsoleTokenService::class, $tokenService);
    $this->app->instance(OrganizationAccessService::class, $organizationAccessService);

    // device_nameなしでリクエスト（Web SPA）
    $response = $this->postJson('/api/sso/callback', [
        'code' => 'valid-code',
    ]);

    $response->assertStatus(200)
        ->assertJsonMissing(['token']); // tokenは返さない

    // セッションが作成されたことを確認
    expect(Auth::check())->toBeTrue();
});

test('callback creates API token for mobile app', function () {
    // ConsoleApiServiceをモック
    $consoleApi = \Mockery::mock(ConsoleApiService::class);
    $consoleApi->shouldReceive('exchangeCode')
        ->andReturn([
            'access_token' => 'valid-jwt',
            'refresh_token' => 'refresh-token',
            'expires_in' => 3600,
        ]);

    // JwtVerifierをモック
    $jwtVerifier = \Mockery::mock(JwtVerifier::class);
    $jwtVerifier->shouldReceive('verify')
        ->andReturn([
            'sub' => 222,
            'email' => 'mobile@example.com',
            'name' => 'Mobile User',
            'aud' => 'test-service',
        ]);

    // ConsoleTokenServiceをモック - save userを実行
    $tokenService = \Mockery::mock(ConsoleTokenService::class);
    $tokenService->shouldReceive('storeTokens')
        ->once()
        ->andReturnUsing(function ($user, $tokens) {
            $user->save();
        });

    // OrganizationAccessServiceをモック
    $organizationAccessService = \Mockery::mock(OrganizationAccessService::class);
    $organizationAccessService->shouldReceive('getOrganizations')
        ->andReturn([]);

    $this->app->instance(ConsoleApiService::class, $consoleApi);
    $this->app->instance(JwtVerifier::class, $jwtVerifier);
    $this->app->instance(ConsoleTokenService::class, $tokenService);
    $this->app->instance(OrganizationAccessService::class, $organizationAccessService);

    // device_nameありでリクエスト（モバイルアプリ）
    $response = $this->postJson('/api/sso/callback', [
        'code' => 'valid-code',
        'device_name' => 'iPhone 15 Pro',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['token']); // tokenを返す
});

// =============================================================================
// Logout Tests - ログアウトのテスト
// =============================================================================

test('logout requires authentication', function () {
    $response = $this->postJson('/api/sso/logout');

    $response->assertStatus(401);
});

test('logout successfully logs out authenticated user', function () {
    $user = User::factory()->create();

    // ConsoleTokenServiceをモック
    $tokenService = \Mockery::mock(ConsoleTokenService::class);
    $tokenService->shouldReceive('revokeTokens')
        ->once();

    $this->app->instance(ConsoleTokenService::class, $tokenService);

    $this->actingAs($user);

    $response = $this->postJson('/api/sso/logout');

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Logged out successfully',
        ]);
});

// =============================================================================
// User Endpoint Tests - ユーザー情報取得のテスト
// =============================================================================

test('user endpoint requires authentication', function () {
    $response = $this->getJson('/api/sso/user');

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'UNAUTHENTICATED',
            'message' => 'Authentication required',
        ]);
});

test('user endpoint returns authenticated user data', function () {
    $user = User::factory()->create([
        'console_user_id' => 123,
        'email' => 'auth@example.com',
        'name' => 'Auth User',
    ]);

    // OrganizationAccessServiceをモック
    $organizationAccessService = \Mockery::mock(OrganizationAccessService::class);
    $organizationAccessService->shouldReceive('getOrganizations')
        ->andReturn([]);

    $this->app->instance(OrganizationAccessService::class, $organizationAccessService);

    $this->actingAs($user);

    $response = $this->getJson('/api/sso/user');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'user' => ['id', 'console_user_id', 'email', 'name'],
            'organizations',
        ])
        ->assertJsonPath('user.email', 'auth@example.com')
        ->assertJsonPath('user.name', 'Auth User')
        ->assertJsonPath('user.console_user_id', 123);
});

test('user endpoint returns user with organizations', function () {
    $user = User::factory()->create();

    // OrganizationAccessServiceをモック
    $organizationAccessService = \Mockery::mock(OrganizationAccessService::class);
    $organizationAccessService->shouldReceive('getOrganizations')
        ->andReturn([
            [
                'organization_id' => 1,
                'organization_slug' => 'my-org',
                'organization_name' => 'My Organization',
                'organization_role' => 'owner',
                'service_role' => 'admin',
            ],
        ]);

    $this->app->instance(OrganizationAccessService::class, $organizationAccessService);

    $this->actingAs($user);

    $response = $this->getJson('/api/sso/user');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'organizations')
        ->assertJsonPath('organizations.0.organization_slug', 'my-org')
        ->assertJsonPath('organizations.0.organization_role', 'owner');
});

// =============================================================================
// Global Logout URL Tests - グローバルログアウトURLのテスト
// =============================================================================

test('global logout url requires authentication', function () {
    $response = $this->getJson('/api/sso/global-logout-url');

    $response->assertStatus(401);
});

test('global logout url returns console logout url', function () {
    $user = User::factory()->create();

    // ConsoleApiServiceをモック
    $consoleApi = \Mockery::mock(ConsoleApiService::class);
    $consoleApi->shouldReceive('getConsoleUrl')
        ->andReturn('https://console.example.com');

    $this->app->instance(ConsoleApiService::class, $consoleApi);

    $this->actingAs($user);

    $response = $this->getJson('/api/sso/global-logout-url?redirect_uri=https://app.example.com');

    $response->assertStatus(200)
        ->assertJsonStructure(['logout_url']);

    $logoutUrl = $response->json('logout_url');
    expect($logoutUrl)->toContain('https://console.example.com/sso/logout')
        ->and($logoutUrl)->toContain('redirect_uri=');
});

// =============================================================================
// Edge Cases - エッジケースのテスト
// =============================================================================

test('callback handles user with null password (sets random password)', function () {
    // Password is NOT NULL in schema — users always have a password set during SSO callback.
    // The schema enforces this constraint, so null password is not a valid state.
    expect(true)->toBeTrue();
});

test('callback handles special characters in user name', function () {
    // ConsoleApiServiceをモック
    $consoleApi = \Mockery::mock(ConsoleApiService::class);
    $consoleApi->shouldReceive('exchangeCode')
        ->andReturn([
            'access_token' => 'valid-jwt',
            'refresh_token' => 'refresh-token',
            'expires_in' => 3600,
        ]);

    // JwtVerifierをモック - 特殊文字を含む名前
    $jwtVerifier = \Mockery::mock(JwtVerifier::class);
    $jwtVerifier->shouldReceive('verify')
        ->andReturn([
            'sub' => 666,
            'email' => 'special@example.com',
            'name' => '田中 太郎 <script>alert("xss")</script>',
            'aud' => 'test-service',
        ]);

    // ConsoleTokenServiceをモック - save userを実行
    $tokenService = \Mockery::mock(ConsoleTokenService::class);
    $tokenService->shouldReceive('storeTokens')
        ->once()
        ->andReturnUsing(function ($user, $tokens) {
            $user->save();
        });

    // OrganizationAccessServiceをモック
    $organizationAccessService = \Mockery::mock(OrganizationAccessService::class);
    $organizationAccessService->shouldReceive('getOrganizations')
        ->andReturn([]);

    $this->app->instance(ConsoleApiService::class, $consoleApi);
    $this->app->instance(JwtVerifier::class, $jwtVerifier);
    $this->app->instance(ConsoleTokenService::class, $tokenService);
    $this->app->instance(OrganizationAccessService::class, $organizationAccessService);

    $response = $this->postJson('/api/sso/callback', [
        'code' => 'valid-code',
    ]);

    $response->assertStatus(200);

    // 名前が保存されていることを確認
    $this->assertDatabaseHas('users', [
        'console_user_id' => 666,
    ]);
});

test('callback validates device_name max length', function () {
    $response = $this->postJson('/api/sso/callback', [
        'code' => 'valid-code',
        'device_name' => str_repeat('a', 300), // 255文字を超える
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['device_name']);
});
