<?php

/**
 * OrganizationAdminController is_standalone Tests
 *
 * 組織管理コントローラーのスタンドアローンモード分離テスト
 * Tests that organization CRUD operations respect is_standalone flag.
 *
 * NOTE: Standalone admin routes are at /admin/* (not /api/admin/sso/*).
 * The store endpoint returns JSON when expectsJson() is true.
 * The index endpoint uses Inertia, so we test the underlying query behavior.
 */

use Omnify\Core\Models\Organization;
use Omnify\Core\Models\User;

beforeEach(function () {
    config()->set('omnify-auth.mode', 'standalone');

    $this->adminUser = User::factory()->standalone()->withPassword('password')->create([
        'name' => 'Admin',
        'email' => 'admin@test.com',
    ]);

    $this->actingAs($this->adminUser);
});

// =============================================================================
// Store — 作成
// =============================================================================

describe('store sets is_standalone', function () {
    test('creating organization via controller sets is_standalone = true', function () {
        $response = $this->postJson('/admin/organizations', [
            'name' => 'Test Org',
            'slug' => 'test-org',
            'is_active' => true,
        ]);

        $response->assertStatus(201);

        $org = Organization::where('slug', 'test-org')->first();

        expect($org)->not->toBeNull()
            ->and($org->is_standalone)->toBeTrue();
    });
});

// =============================================================================
// Index — 一覧 (currentMode filter) — test via model query
// =============================================================================

describe('currentMode filters organizations correctly', function () {
    test('standalone mode only shows standalone organizations', function () {
        Organization::factory()->count(3)->standalone()->create(['is_active' => true]);
        Organization::factory()->count(2)->console()->create(['is_active' => true]);

        config()->set('omnify-auth.mode', 'standalone');

        // Same query as OrganizationAdminController::index()
        $result = Organization::query()->currentMode()->get();

        expect($result)->toHaveCount(3);
        $result->each(fn ($org) => expect($org->is_standalone)->toBeTrue());
    });

    test('console orgs are hidden in standalone mode', function () {
        // Only console orgs
        Organization::factory()->count(5)->console()->create(['is_active' => true]);

        config()->set('omnify-auth.mode', 'standalone');

        $result = Organization::query()->currentMode()->get();

        expect($result)->toHaveCount(0);
    });
});
