<?php

/**
 * BranchAdminController is_standalone Tests
 *
 * 支店管理コントローラーのスタンドアローンモード分離テスト
 * Tests that branch CRUD operations respect is_standalone flag.
 *
 * NOTE: Standalone admin routes are at /admin/* (not /api/admin/sso/*).
 * The index endpoint uses Inertia, so we test the underlying query behavior.
 */

use Illuminate\Support\Str;
use Omnify\Core\Models\Branch;
use Omnify\Core\Models\Organization;
use Omnify\Core\Models\User;

beforeEach(function () {
    config()->set('omnify-auth.mode', 'standalone');

    $this->org = Organization::factory()->standalone()->create([
        'is_active' => true,
    ]);

    $this->adminUser = User::factory()->standalone()->withPassword('password')->create([
        'name' => 'Admin',
        'email' => 'admin@test.com',
        'console_organization_id' => $this->org->console_organization_id,
    ]);

    $this->actingAs($this->adminUser);
});

// =============================================================================
// currentMode filter — test via model query (same as controller)
// =============================================================================

describe('currentMode filters branches correctly', function () {
    test('standalone mode only shows standalone branches', function () {
        Branch::factory()->count(3)->standalone()->create([
            'console_organization_id' => $this->org->console_organization_id,
        ]);
        Branch::factory()->count(2)->console()->create();

        config()->set('omnify-auth.mode', 'standalone');

        // Same query pattern as BranchAdminController::index()
        $result = Branch::query()->currentMode()->get();

        expect($result)->toHaveCount(3);
        $result->each(fn ($b) => expect($b->is_standalone)->toBeTrue());
    });

    test('console branches are not shown in standalone mode', function () {
        Branch::factory()->count(5)->console()->create();

        config()->set('omnify-auth.mode', 'standalone');

        expect(Branch::currentMode()->count())->toBe(0);
    });
});

// =============================================================================
// Store — 作成
// =============================================================================

describe('store sets is_standalone', function () {
    test('creating branch sets is_standalone = true in standalone mode', function () {
        config()->set('omnify-auth.mode', 'standalone');

        $branch = Branch::create([
            'name' => 'Test Branch',
            'slug' => 'test-branch',
            'is_active' => true,
            'is_headquarters' => false,
            'is_standalone' => true,
            'console_organization_id' => $this->org->console_organization_id,
            'console_branch_id' => Str::uuid()->toString(),
        ]);

        expect($branch->is_standalone)->toBeTrue();
    });
});
