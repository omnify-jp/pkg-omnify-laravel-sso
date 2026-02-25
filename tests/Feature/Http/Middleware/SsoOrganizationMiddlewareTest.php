<?php

/**
 * SSO Organization Access Middleware Tests
 *
 * 組織アクセスミドルウェアのテスト
 */

use Illuminate\Support\Facades\Route;
use Omnify\SsoClient\Services\OrganizationAccessService;
use Omnify\SsoClient\Tests\Fixtures\Models\User;

beforeEach(function () {
    // テスト用のルートを定義
    Route::middleware(['sso.auth', 'sso.organization'])->get('/test-org-access', function () {
        return response()->json([
            'message' => 'organization access granted',
            'organization_id' => request()->attributes->get('organizationId'),
            'organization_role' => request()->attributes->get('organizationRole'),
            'service_role' => request()->attributes->get('serviceRole'),
        ]);
    });
});

test('sso.organization middleware rejects request without X-Organization-Id header', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/test-org-access');

    $response->assertStatus(400)
        ->assertJson([
            'error' => 'MISSING_ORGANIZATION',
            'message' => 'X-Organization-Id header is required',
        ]);
});

test('sso.organization middleware rejects unauthorized organization access', function () {
    $user = User::factory()->create();

    // OrganizationAccessServiceをモック
    $organizationAccessService = \Mockery::mock(OrganizationAccessService::class);
    $organizationAccessService->shouldReceive('checkAccess')
        ->with(\Mockery::any(), 'unauthorized-org')
        ->andReturn(null);

    $this->app->instance(OrganizationAccessService::class, $organizationAccessService);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Organization-Id' => 'unauthorized-org'])
        ->getJson('/test-org-access');

    $response->assertStatus(403)
        ->assertJson([
            'error' => 'ACCESS_DENIED',
            'message' => 'No access to this organization',
        ]);
});

test('sso.organization middleware allows authorized organization access', function () {
    $user = User::factory()->create();

    // OrganizationAccessServiceをモック
    $organizationAccessService = \Mockery::mock(OrganizationAccessService::class);
    $organizationAccessService->shouldReceive('checkAccess')
        ->with(\Mockery::any(), 'my-company')
        ->andReturn([
            'organization_id' => 1,
            'organization_slug' => 'my-company',
            'organization_role' => 'admin',
            'service_role' => 'admin',
            'service_role_level' => 100,
        ]);

    $this->app->instance(OrganizationAccessService::class, $organizationAccessService);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Organization-Id' => 'my-company'])
        ->getJson('/test-org-access');

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'organization access granted',
            'organization_id' => 1,
            'organization_role' => 'admin',
            'service_role' => 'admin',
        ]);
});

test('sso.organization middleware sets organization info on request attributes', function () {
    $user = User::factory()->create();

    // OrganizationAccessServiceをモック
    $organizationAccessService = \Mockery::mock(OrganizationAccessService::class);
    $organizationAccessService->shouldReceive('checkAccess')
        ->andReturn([
            'organization_id' => 123,
            'organization_slug' => 'test-org',
            'organization_role' => 'member',
            'service_role' => 'manager',
            'service_role_level' => 50,
        ]);

    $this->app->instance(OrganizationAccessService::class, $organizationAccessService);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Organization-Id' => 'test-org'])
        ->getJson('/test-org-access');

    $response->assertStatus(200)
        ->assertJsonPath('organization_id', 123)
        ->assertJsonPath('organization_role', 'member')
        ->assertJsonPath('service_role', 'manager');
});
