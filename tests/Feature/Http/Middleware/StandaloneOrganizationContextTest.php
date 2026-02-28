<?php

/**
 * StandaloneOrganizationContext Middleware Tests
 *
 * スタンドアローン組織コンテキストミドルウェアのテスト
 * Tests that middleware resolves org context respecting is_standalone / currentMode.
 */

use Illuminate\Support\Facades\Route;
use Omnify\Core\Http\Middleware\StandaloneOrganizationContext;
use Omnify\Core\Models\Organization;
use Omnify\Core\Models\User;

beforeEach(function () {
    // Register a test route with the middleware
    Route::middleware(['web', StandaloneOrganizationContext::class])
        ->get('/_test/org-context', function (\Illuminate\Http\Request $request) {
            return response()->json([
                'organizationId' => $request->attributes->get('organizationId'),
            ]);
        });
});

// =============================================================================
// Standalone Mode — スタンドアローンモード
// =============================================================================

describe('standalone mode', function () {
    beforeEach(function () {
        config()->set('omnify-auth.mode', 'standalone');
    });

    test('resolves standalone org when user has console_organization_id', function () {
        $org = Organization::factory()->standalone()->create([
            'is_active' => true,
        ]);

        $user = User::factory()->standalone()->create([
            'console_organization_id' => $org->console_organization_id,
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/_test/org-context');

        $response->assertOk();
        expect($response->json('organizationId'))->toBe($org->id);
    });

    test('does not resolve console org in standalone mode', function () {
        $consoleOrg = Organization::factory()->console()->create([
            'is_active' => true,
        ]);

        $user = User::factory()->standalone()->create([
            'console_organization_id' => $consoleOrg->console_organization_id,
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/_test/org-context');

        $response->assertOk();
        // Should NOT resolve the console org
        expect($response->json('organizationId'))->not->toBe($consoleOrg->id);
    });

    test('falls back to first active standalone org', function () {
        // Create console org first (should be skipped)
        Organization::factory()->console()->create(['is_active' => true]);

        // Create standalone org (should be found as fallback)
        $saOrg = Organization::factory()->standalone()->create(['is_active' => true]);

        $user = User::factory()->standalone()->create([
            'console_organization_id' => null,
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/_test/org-context');

        $response->assertOk();
        expect($response->json('organizationId'))->toBe($saOrg->id);
    });

    test('returns null when no standalone org exists', function () {
        // Only console orgs exist
        Organization::factory()->count(3)->console()->create(['is_active' => true]);

        $user = User::factory()->standalone()->create([
            'console_organization_id' => null,
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/_test/org-context');

        $response->assertOk();
        expect($response->json('organizationId'))->toBeNull();
    });
});

// =============================================================================
// Console Mode — コンソールモード
// =============================================================================

describe('console mode', function () {
    beforeEach(function () {
        config()->set('omnify-auth.mode', 'console');
    });

    test('resolves console org when user has console_organization_id', function () {
        $org = Organization::factory()->console()->create([
            'is_active' => true,
        ]);

        $user = User::factory()->console()->create([
            'console_organization_id' => $org->console_organization_id,
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/_test/org-context');

        $response->assertOk();
        expect($response->json('organizationId'))->toBe($org->id);
    });

    test('does not resolve standalone org in console mode', function () {
        $saOrg = Organization::factory()->standalone()->create([
            'is_active' => true,
        ]);

        $user = User::factory()->console()->create([
            'console_organization_id' => $saOrg->console_organization_id,
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/_test/org-context');

        $response->assertOk();
        expect($response->json('organizationId'))->not->toBe($saOrg->id);
    });

    test('falls back to first active console org', function () {
        Organization::factory()->standalone()->create(['is_active' => true]);
        $coOrg = Organization::factory()->console()->create(['is_active' => true]);

        $user = User::factory()->console()->create([
            'console_organization_id' => null,
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/_test/org-context');

        $response->assertOk();
        expect($response->json('organizationId'))->toBe($coOrg->id);
    });
});

// =============================================================================
// Edge Cases — エッジケース
// =============================================================================

describe('edge cases', function () {
    test('unauthenticated request skips org resolution', function () {
        Organization::factory()->standalone()->create(['is_active' => true]);

        $response = $this->getJson('/_test/org-context');

        $response->assertOk();
        expect($response->json('organizationId'))->toBeNull();
    });

    test('inactive org is not resolved even if matching', function () {
        config()->set('omnify-auth.mode', 'standalone');

        $org = Organization::factory()->standalone()->create([
            'is_active' => false,
        ]);

        $user = User::factory()->standalone()->create([
            'console_organization_id' => $org->console_organization_id,
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/_test/org-context');

        $response->assertOk();
        expect($response->json('organizationId'))->not->toBe($org->id);
    });
});
