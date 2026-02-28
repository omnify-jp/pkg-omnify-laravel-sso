<?php

/**
 * Mode Isolation Integration Tests
 *
 * モード分離の統合テスト
 * Tests comprehensive data isolation between standalone and console modes.
 * Covers cross-model queries, mode switching, and edge cases.
 */

use Illuminate\Support\Str;
use Omnify\Core\Models\Brand;
use Omnify\Core\Models\Branch;
use Omnify\Core\Models\Location;
use Omnify\Core\Models\Organization;
use Omnify\Core\Models\User;

// =============================================================================
// Cross-model isolation — 全モデル横断分離テスト
// =============================================================================

describe('cross-model data isolation', function () {
    beforeEach(function () {
        // Create standalone data
        $this->saOrg = Organization::factory()->standalone()->create([
            'name' => 'SA Org',
            'is_active' => true,
        ]);
        $this->saBranch = Branch::factory()->standalone()->create([
            'console_organization_id' => $this->saOrg->console_organization_id,
        ]);
        $this->saUser = User::factory()->standalone()->create([
            'console_organization_id' => $this->saOrg->console_organization_id,
        ]);
        $this->saLocation = Location::factory()->standalone()->create([
            'console_organization_id' => $this->saOrg->console_organization_id,
            'console_branch_id' => $this->saBranch->console_branch_id,
        ]);
        $this->saBrand = Brand::factory()->standalone()->create([
            'console_organization_id' => $this->saOrg->console_organization_id,
        ]);

        // Create console data
        $this->coOrg = Organization::factory()->console()->create([
            'name' => 'CO Org',
            'is_active' => true,
        ]);
        $this->coBranch = Branch::factory()->console()->create([
            'console_organization_id' => $this->coOrg->console_organization_id,
        ]);
        $this->coUser = User::factory()->console()->create([
            'console_organization_id' => $this->coOrg->console_organization_id,
        ]);
        $this->coLocation = Location::factory()->console()->create([
            'console_organization_id' => $this->coOrg->console_organization_id,
            'console_branch_id' => $this->coBranch->console_branch_id,
        ]);
        $this->coBrand = Brand::factory()->console()->create([
            'console_organization_id' => $this->coOrg->console_organization_id,
        ]);
    });

    test('standalone mode sees only standalone data across all models', function () {
        config()->set('omnify-auth.mode', 'standalone');

        expect(Organization::currentMode()->count())->toBe(1)
            ->and(Organization::currentMode()->first()->name)->toBe('SA Org')
            ->and(Branch::currentMode()->count())->toBe(1)
            ->and(User::currentMode()->count())->toBe(1)
            ->and(Location::currentMode()->count())->toBe(1)
            ->and(Brand::currentMode()->count())->toBe(1);
    });

    test('console mode sees only console data across all models', function () {
        config()->set('omnify-auth.mode', 'console');

        expect(Organization::currentMode()->count())->toBe(1)
            ->and(Organization::currentMode()->first()->name)->toBe('CO Org')
            ->and(Branch::currentMode()->count())->toBe(1)
            ->and(User::currentMode()->count())->toBe(1)
            ->and(Location::currentMode()->count())->toBe(1)
            ->and(Brand::currentMode()->count())->toBe(1);
    });

    test('unscoped queries see all data regardless of mode', function () {
        expect(Organization::count())->toBe(2)
            ->and(Branch::count())->toBe(2)
            ->and(User::count())->toBe(2)
            ->and(Location::count())->toBe(2)
            ->and(Brand::count())->toBe(2);
    });
});

// =============================================================================
// Mode switching — モード切替テスト
// =============================================================================

describe('rapid mode switching', function () {
    test('switching mode changes visible data immediately', function () {
        Organization::factory()->count(3)->standalone()->create();
        Organization::factory()->count(5)->console()->create();

        config()->set('omnify-auth.mode', 'standalone');
        expect(Organization::currentMode()->count())->toBe(3);

        config()->set('omnify-auth.mode', 'console');
        expect(Organization::currentMode()->count())->toBe(5);

        config()->set('omnify-auth.mode', 'standalone');
        expect(Organization::currentMode()->count())->toBe(3);
    });
});

// =============================================================================
// Scope chaining — スコープ連鎖
// =============================================================================

describe('scope chaining', function () {
    test('currentMode chains with where clauses', function () {
        Organization::factory()->standalone()->create([
            'is_active' => true,
            'name' => 'Active SA',
        ]);
        Organization::factory()->standalone()->create([
            'is_active' => false,
            'name' => 'Inactive SA',
        ]);
        Organization::factory()->console()->create([
            'is_active' => true,
            'name' => 'Active CO',
        ]);

        config()->set('omnify-auth.mode', 'standalone');

        $activeStandalone = Organization::currentMode()
            ->where('is_active', true)
            ->get();

        expect($activeStandalone)->toHaveCount(1)
            ->and($activeStandalone->first()->name)->toBe('Active SA');
    });

    test('currentMode chains with orderBy and pagination', function () {
        Organization::factory()->count(20)->standalone()->create(['is_active' => true]);
        Organization::factory()->count(10)->console()->create(['is_active' => true]);

        config()->set('omnify-auth.mode', 'standalone');

        $paginated = Organization::currentMode()
            ->orderBy('name')
            ->paginate(5);

        expect($paginated->total())->toBe(20)
            ->and($paginated->items())->toHaveCount(5);
    });

    test('standalone scope chains with organization filter on branches', function () {
        $orgId = (string) Str::uuid();

        Branch::factory()->count(3)->standalone()->create([
            'console_organization_id' => $orgId,
        ]);
        Branch::factory()->count(2)->console()->create([
            'console_organization_id' => $orgId,
        ]);
        Branch::factory()->count(4)->standalone()->create();

        $result = Branch::standalone()
            ->where('console_organization_id', $orgId)
            ->count();

        expect($result)->toBe(3);
    });
});

// =============================================================================
// Edge cases — エッジケース
// =============================================================================

describe('edge cases', function () {
    test('creating model without explicit is_standalone uses trait default', function () {
        config()->set('omnify-auth.mode', 'standalone');
        $org1 = Organization::factory()->create();
        expect($org1->is_standalone)->toBeTrue();

        config()->set('omnify-auth.mode', 'console');
        $org2 = Organization::factory()->create();
        expect($org2->is_standalone)->toBeFalse();
    });

    test('soft deleted records respect scope filters', function () {
        $org = Organization::factory()->standalone()->create();
        $org->delete();

        expect(Organization::standalone()->count())->toBe(0)
            ->and(Organization::withTrashed()->standalone()->count())->toBe(1);
    });

    test('updateOrCreate respects is_standalone', function () {
        $consoleOrgId = (string) Str::uuid();

        // Create via sync (console mode)
        Branch::withTrashed()->updateOrCreate(
            ['console_branch_id' => 'sync-branch-001'],
            [
                'console_organization_id' => $consoleOrgId,
                'slug' => 'synced',
                'name' => 'Synced Branch',
                'is_standalone' => false,
            ]
        );

        expect(Branch::console()->count())->toBe(1)
            ->and(Branch::standalone()->count())->toBe(0);

        // Update same branch via sync — should stay console
        Branch::withTrashed()->updateOrCreate(
            ['console_branch_id' => 'sync-branch-001'],
            [
                'name' => 'Updated Synced Branch',
                'is_standalone' => false,
            ]
        );

        $branch = Branch::where('console_branch_id', 'sync-branch-001')->first();
        expect($branch->name)->toBe('Updated Synced Branch')
            ->and($branch->is_standalone)->toBeFalse();
    });

    test('mass assignment with explicit is_standalone overrides trait', function () {
        config()->set('omnify-auth.mode', 'standalone');

        // Explicitly set to false even in standalone mode
        $org = Organization::create([
            'console_organization_id' => (string) Str::uuid(),
            'name' => 'Console Org in SA Mode',
            'slug' => 'console-in-sa',
            'is_standalone' => false,
        ]);

        expect($org->is_standalone)->toBeFalse();
    });

    test('factory states produce correct values', function () {
        $saOrg = Organization::factory()->standalone()->create();
        $coOrg = Organization::factory()->console()->create();
        $saBranch = Branch::factory()->standalone()->create();
        $coBranch = Branch::factory()->console()->create();
        $saUser = User::factory()->standalone()->create();
        $coUser = User::factory()->console()->create();
        $saLoc = Location::factory()->standalone()->create();
        $coLoc = Location::factory()->console()->create();
        $saBrand = Brand::factory()->standalone()->create();
        $coBrand = Brand::factory()->console()->create();

        expect($saOrg->is_standalone)->toBeTrue()
            ->and($coOrg->is_standalone)->toBeFalse()
            ->and($saBranch->is_standalone)->toBeTrue()
            ->and($coBranch->is_standalone)->toBeFalse()
            ->and($saUser->is_standalone)->toBeTrue()
            ->and($coUser->is_standalone)->toBeFalse()
            ->and($saLoc->is_standalone)->toBeTrue()
            ->and($coLoc->is_standalone)->toBeFalse()
            ->and($saBrand->is_standalone)->toBeTrue()
            ->and($coBrand->is_standalone)->toBeFalse();
    });

    test('count queries with scopes return correct numbers', function () {
        $saOrg = Organization::factory()->standalone()->create();
        $coOrg = Organization::factory()->console()->create();
        Branch::factory()->count(12)->standalone()
            ->forOrganization($saOrg->console_organization_id)->create();
        Branch::factory()->count(8)->console()
            ->forOrganization($coOrg->console_organization_id)->create();

        expect(Organization::standalone()->count())->toBe(1)
            ->and(Organization::console()->count())->toBe(1)
            ->and(Branch::standalone()->count())->toBe(12)
            ->and(Branch::console()->count())->toBe(8)
            ->and(Organization::count())->toBe(2)
            ->and(Branch::count())->toBe(20);
    });
});

// =============================================================================
// Mutual exclusion — 相互排他テスト
// =============================================================================

describe('standalone and console scopes are mutually exclusive', function () {
    test('same record cannot be both standalone and console', function () {
        $org = Organization::factory()->standalone()->create();

        expect($org->is_standalone)->toBeTrue();

        // Update to console
        $org->update(['is_standalone' => false]);
        $org->refresh();

        expect($org->is_standalone)->toBeFalse()
            ->and(Organization::standalone()->where('id', $org->id)->exists())->toBeFalse()
            ->and(Organization::console()->where('id', $org->id)->exists())->toBeTrue();
    });

    test('intersection of standalone() and console() scopes is always empty', function () {
        Organization::factory()->count(5)->standalone()->create();
        Organization::factory()->count(5)->console()->create();

        $standaloneIds = Organization::standalone()->pluck('id');
        $consoleIds = Organization::console()->pluck('id');

        $intersection = $standaloneIds->intersect($consoleIds);

        expect($intersection)->toHaveCount(0);
    });

    test('union of standalone() and console() equals all records', function () {
        Organization::factory()->count(3)->standalone()->create();
        Organization::factory()->count(4)->console()->create();

        $standaloneCount = Organization::standalone()->count();
        $consoleCount = Organization::console()->count();
        $totalCount = Organization::count();

        expect($standaloneCount + $consoleCount)->toBe($totalCount);
    });
});
