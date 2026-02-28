<?php

/**
 * Factory Relationship Tests
 *
 * ファクトリーリレーション整合性テスト
 * Tests that factories create real parent records (no orphan UUIDs).
 */

use Illuminate\Support\Str;
use Omnify\Core\Models\Brand;
use Omnify\Core\Models\Branch;
use Omnify\Core\Models\Location;
use Omnify\Core\Models\Organization;

// =============================================================================
// BranchFactory — 支店ファクトリー
// =============================================================================

describe('BranchFactory creates real parent records', function () {
    test('branch factory creates a real Organization parent', function () {
        $branch = Branch::factory()->create();

        $org = Organization::where(
            'console_organization_id',
            $branch->console_organization_id,
        )->first();

        expect($org)->not->toBeNull()
            ->and($org)->toBeInstanceOf(Organization::class);
    });

    test('branch organization relationship resolves', function () {
        $branch = Branch::factory()->create();

        expect($branch->organization)->not->toBeNull()
            ->and($branch->organization)->toBeInstanceOf(Organization::class)
            ->and($branch->organization->console_organization_id)
            ->toBe($branch->console_organization_id);
    });

    test('multiple branches from same factory each get an organization', function () {
        $branches = Branch::factory()->count(3)->create();

        $branches->each(function ($branch) {
            expect(
                Organization::where('console_organization_id', $branch->console_organization_id)->exists()
            )->toBeTrue();
        });
    });

    test('forOrganization state overrides default organization', function () {
        $customOrgId = (string) Str::uuid();

        $branch = Branch::factory()->forOrganization($customOrgId)->create();

        expect($branch->console_organization_id)->toBe($customOrgId);
    });
});

// =============================================================================
// LocationFactory — 拠点ファクトリー
// =============================================================================

describe('LocationFactory creates real parent records', function () {
    test('location factory creates a real Branch parent', function () {
        $location = Location::factory()->create();

        $branch = Branch::where(
            'console_branch_id',
            $location->console_branch_id,
        )->first();

        expect($branch)->not->toBeNull()
            ->and($branch)->toBeInstanceOf(Branch::class);
    });

    test('location factory creates a real Organization (via Branch)', function () {
        $location = Location::factory()->create();

        $org = Organization::where(
            'console_organization_id',
            $location->console_organization_id,
        )->first();

        expect($org)->not->toBeNull()
            ->and($org)->toBeInstanceOf(Organization::class);
    });

    test('location branch and organization share the same console_organization_id', function () {
        $location = Location::factory()->create();

        $branch = Branch::where('console_branch_id', $location->console_branch_id)->first();

        expect($branch->console_organization_id)->toBe($location->console_organization_id);
    });

    test('location branch relationship resolves', function () {
        $location = Location::factory()->create();

        expect($location->branch)->not->toBeNull()
            ->and($location->branch)->toBeInstanceOf(Branch::class);
    });

    test('location organization relationship resolves', function () {
        $location = Location::factory()->create();

        expect($location->organization)->not->toBeNull()
            ->and($location->organization)->toBeInstanceOf(Organization::class);
    });

    test('forBranch state overrides default branch and organization', function () {
        $branch = Branch::factory()->create();

        $location = Location::factory()
            ->forBranch($branch->console_branch_id, $branch->console_organization_id)
            ->create();

        expect($location->console_branch_id)->toBe($branch->console_branch_id)
            ->and($location->console_organization_id)->toBe($branch->console_organization_id);
    });
});

// =============================================================================
// BrandFactory — ブランドファクトリー
// =============================================================================

describe('BrandFactory creates real parent records', function () {
    test('brand factory creates a real Organization parent', function () {
        $brand = Brand::factory()->create();

        $org = Organization::where(
            'console_organization_id',
            $brand->console_organization_id,
        )->first();

        expect($org)->not->toBeNull()
            ->and($org)->toBeInstanceOf(Organization::class);
    });

    test('brand organization relationship resolves', function () {
        $brand = Brand::factory()->create();

        expect($brand->organization)->not->toBeNull()
            ->and($brand->organization)->toBeInstanceOf(Organization::class)
            ->and($brand->organization->console_organization_id)
            ->toBe($brand->console_organization_id);
    });

    test('forOrganization state overrides default organization', function () {
        $customOrgId = (string) Str::uuid();

        $brand = Brand::factory()->forOrganization($customOrgId)->create();

        expect($brand->console_organization_id)->toBe($customOrgId);
    });

    test('standalone state sets is_standalone to true', function () {
        $brand = Brand::factory()->standalone()->create();

        expect($brand->is_standalone)->toBeTrue();
    });

    test('console state sets is_standalone to false', function () {
        $brand = Brand::factory()->console()->create();

        expect($brand->is_standalone)->toBeFalse();
    });

    test('brand factory creates valid UUID for console_brand_id', function () {
        $brand = Brand::factory()->create();

        expect(Str::isUuid($brand->console_brand_id))->toBeTrue();
    });
});
