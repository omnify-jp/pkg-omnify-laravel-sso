<?php

/**
 * Location Model Relationship Tests
 *
 * 拠点モデルのリレーションシップテスト
 * Tests Location model relationships to Branch and Organization.
 */

use Omnify\Core\Models\Branch;
use Omnify\Core\Models\Location;
use Omnify\Core\Models\Organization;

// =============================================================================
// Branch relationship — 支店リレーション
// =============================================================================

describe('location → branch relationship', function () {
    test('location belongs to branch', function () {
        $branch = Branch::factory()->create();
        $location = Location::factory()
            ->forBranch($branch->console_branch_id, $branch->console_organization_id)
            ->create();

        expect($location->branch)->not->toBeNull()
            ->and($location->branch)->toBeInstanceOf(Branch::class)
            ->and($location->branch->id)->toBe($branch->id);
    });

    test('location branch is accessible via console_branch_id', function () {
        $location = Location::factory()->create();

        $branch = Branch::where('console_branch_id', $location->console_branch_id)->first();

        expect($branch)->not->toBeNull()
            ->and($location->branch->console_branch_id)->toBe($branch->console_branch_id);
    });
});

// =============================================================================
// Organization relationship — 組織リレーション
// =============================================================================

describe('location → organization relationship', function () {
    test('location belongs to organization', function () {
        $org = Organization::factory()->create();
        $branch = Branch::factory()->forOrganization($org->console_organization_id)->create();
        $location = Location::factory()
            ->forBranch($branch->console_branch_id, $branch->console_organization_id)
            ->create();

        expect($location->organization)->not->toBeNull()
            ->and($location->organization)->toBeInstanceOf(Organization::class)
            ->and($location->organization->id)->toBe($org->id);
    });

    test('location organization matches branch organization', function () {
        $location = Location::factory()->create();

        expect($location->organization->console_organization_id)
            ->toBe($location->branch->console_organization_id);
    });
});

// =============================================================================
// Branch → locations relationship — 支店→拠点リレーション
// =============================================================================

describe('branch → locations relationship', function () {
    test('branch has many locations', function () {
        $branch = Branch::factory()->create();

        Location::factory()->count(4)
            ->forBranch($branch->console_branch_id, $branch->console_organization_id)
            ->create();

        expect($branch->locations)->toHaveCount(4);
    });

    test('branch with no locations returns empty collection', function () {
        $branch = Branch::factory()->create();

        expect($branch->locations)->toHaveCount(0);
    });
});

// =============================================================================
// Branch → brand relationship — 支店→ブランドリレーション
// =============================================================================

describe('branch → brand relationship', function () {
    test('branch can belong to a brand', function () {
        $brand = \Omnify\Core\Models\Brand::factory()->create();
        $branch = Branch::factory()->create([
            'console_brand_id' => $brand->console_brand_id,
            'console_organization_id' => $brand->console_organization_id,
        ]);

        expect($branch->brand)->not->toBeNull()
            ->and($branch->brand)->toBeInstanceOf(\Omnify\Core\Models\Brand::class)
            ->and($branch->brand->id)->toBe($brand->id);
    });

    test('branch without brand returns null', function () {
        $branch = Branch::factory()->create(['console_brand_id' => null]);

        expect($branch->brand)->toBeNull();
    });
});
