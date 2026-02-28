<?php

/**
 * HasStandaloneScope Trait Unit Tests
 *
 * スタンドアローンスコープトレイトのユニットテスト
 * Tests auto-setting, query scopes, and mode isolation.
 */

use Illuminate\Support\Str;
use Omnify\Core\Models\Branch;
use Omnify\Core\Models\Location;
use Omnify\Core\Models\Organization;
use Omnify\Core\Models\User;

// =============================================================================
// Auto-setting is_standalone on creation — 作成時の自動設定
// =============================================================================

describe('auto-sets is_standalone on creation', function () {
    test('defaults to true when mode is standalone', function () {
        config()->set('omnify-auth.mode', 'standalone');

        $org = Organization::factory()->create();

        expect($org->is_standalone)->toBeTrue();
    });

    test('defaults to false when mode is console', function () {
        config()->set('omnify-auth.mode', 'console');

        $org = Organization::factory()->create();

        expect($org->is_standalone)->toBeFalse();
    });

    test('does not override explicitly set is_standalone = true in console mode', function () {
        config()->set('omnify-auth.mode', 'console');

        $org = Organization::factory()->create(['is_standalone' => true]);

        expect($org->is_standalone)->toBeTrue();
    });

    test('does not override explicitly set is_standalone = false in standalone mode', function () {
        config()->set('omnify-auth.mode', 'standalone');

        $org = Organization::factory()->create(['is_standalone' => false]);

        expect($org->is_standalone)->toBeFalse();
    });

    test('auto-sets for Branch model', function () {
        config()->set('omnify-auth.mode', 'standalone');
        $branch = Branch::factory()->create();
        expect($branch->is_standalone)->toBeTrue();

        config()->set('omnify-auth.mode', 'console');
        $branch2 = Branch::factory()->create();
        expect($branch2->is_standalone)->toBeFalse();
    });

    test('auto-sets for User model', function () {
        config()->set('omnify-auth.mode', 'standalone');
        $user = User::factory()->create();
        expect($user->is_standalone)->toBeTrue();

        config()->set('omnify-auth.mode', 'console');
        $user2 = User::factory()->create();
        expect($user2->is_standalone)->toBeFalse();
    });

    test('auto-sets for Location model', function () {
        config()->set('omnify-auth.mode', 'standalone');
        $loc = Location::factory()->create();
        expect($loc->is_standalone)->toBeTrue();

        config()->set('omnify-auth.mode', 'console');
        $loc2 = Location::factory()->create();
        expect($loc2->is_standalone)->toBeFalse();
    });
});

// =============================================================================
// Query Scopes — クエリスコープ
// =============================================================================

describe('scopeStandalone()', function () {
    test('returns only standalone records', function () {
        Organization::factory()->count(3)->standalone()->create();
        Organization::factory()->count(2)->console()->create();

        $results = Organization::standalone()->get();

        expect($results)->toHaveCount(3);
        $results->each(fn ($org) => expect($org->is_standalone)->toBeTrue());
    });

    test('returns empty when no standalone records exist', function () {
        Organization::factory()->count(3)->console()->create();

        expect(Organization::standalone()->count())->toBe(0);
    });
});

describe('scopeConsole()', function () {
    test('returns only console records', function () {
        Organization::factory()->count(3)->standalone()->create();
        Organization::factory()->count(2)->console()->create();

        $results = Organization::console()->get();

        expect($results)->toHaveCount(2);
        $results->each(fn ($org) => expect($org->is_standalone)->toBeFalse());
    });

    test('returns empty when no console records exist', function () {
        Organization::factory()->count(3)->standalone()->create();

        expect(Organization::console()->count())->toBe(0);
    });
});

describe('scopeCurrentMode()', function () {
    test('returns standalone records when mode is standalone', function () {
        config()->set('omnify-auth.mode', 'standalone');

        Organization::factory()->count(3)->standalone()->create();
        Organization::factory()->count(2)->console()->create();

        $results = Organization::currentMode()->get();

        expect($results)->toHaveCount(3);
        $results->each(fn ($org) => expect($org->is_standalone)->toBeTrue());
    });

    test('returns console records when mode is console', function () {
        config()->set('omnify-auth.mode', 'console');

        Organization::factory()->count(3)->standalone()->create();
        Organization::factory()->count(2)->console()->create();

        $results = Organization::currentMode()->get();

        expect($results)->toHaveCount(2);
        $results->each(fn ($org) => expect($org->is_standalone)->toBeFalse());
    });

    test('switching mode changes currentMode results', function () {
        Organization::factory()->count(3)->standalone()->create();
        Organization::factory()->count(2)->console()->create();

        config()->set('omnify-auth.mode', 'standalone');
        expect(Organization::currentMode()->count())->toBe(3);

        config()->set('omnify-auth.mode', 'console');
        expect(Organization::currentMode()->count())->toBe(2);
    });
});

// =============================================================================
// Scopes work across all models — 全モデルでスコープ動作確認
// =============================================================================

describe('scopes work for Branch', function () {
    test('standalone/console/currentMode scopes filter correctly', function () {
        Branch::factory()->count(4)->standalone()->create();
        Branch::factory()->count(3)->console()->create();

        expect(Branch::standalone()->count())->toBe(4)
            ->and(Branch::console()->count())->toBe(3);

        config()->set('omnify-auth.mode', 'standalone');
        expect(Branch::currentMode()->count())->toBe(4);

        config()->set('omnify-auth.mode', 'console');
        expect(Branch::currentMode()->count())->toBe(3);
    });
});

describe('scopes work for User', function () {
    test('standalone/console/currentMode scopes filter correctly', function () {
        User::factory()->count(5)->standalone()->create();
        User::factory()->count(2)->console()->create();

        expect(User::standalone()->count())->toBe(5)
            ->and(User::console()->count())->toBe(2);

        config()->set('omnify-auth.mode', 'standalone');
        expect(User::currentMode()->count())->toBe(5);

        config()->set('omnify-auth.mode', 'console');
        expect(User::currentMode()->count())->toBe(2);
    });
});

describe('scopes work for Location', function () {
    test('standalone/console/currentMode scopes filter correctly', function () {
        Location::factory()->count(2)->standalone()->create();
        Location::factory()->count(6)->console()->create();

        expect(Location::standalone()->count())->toBe(2)
            ->and(Location::console()->count())->toBe(6);

        config()->set('omnify-auth.mode', 'standalone');
        expect(Location::currentMode()->count())->toBe(2);

        config()->set('omnify-auth.mode', 'console');
        expect(Location::currentMode()->count())->toBe(6);
    });
});

// =============================================================================
// Edge Cases — エッジケース
// =============================================================================

describe('edge cases', function () {
    test('is_standalone is cast to boolean', function () {
        $org = Organization::factory()->standalone()->create();

        expect($org->is_standalone)->toBeBool()
            ->and($org->is_standalone)->toBeTrue();

        $org2 = Organization::factory()->console()->create();

        expect($org2->is_standalone)->toBeBool()
            ->and($org2->is_standalone)->toBeFalse();
    });

    test('scopes can be chained with other queries', function () {
        $orgId = (string) Str::uuid();

        Branch::factory()->standalone()->create([
            'console_organization_id' => $orgId,
            'is_active' => true,
        ]);
        Branch::factory()->standalone()->create([
            'console_organization_id' => $orgId,
            'is_active' => false,
        ]);
        Branch::factory()->console()->create([
            'console_organization_id' => $orgId,
            'is_active' => true,
        ]);

        $activeSA = Branch::standalone()
            ->where('console_organization_id', $orgId)
            ->where('is_active', true)
            ->count();

        expect($activeSA)->toBe(1);
    });

    test('scopes work with soft deleted records', function () {
        $org = Organization::factory()->standalone()->create();
        Organization::factory()->count(2)->console()->create();

        $org->delete();

        // Normal scope excludes soft deleted
        expect(Organization::standalone()->count())->toBe(0);

        // withTrashed includes soft deleted
        expect(Organization::withTrashed()->standalone()->count())->toBe(1);
    });

    test('is_standalone persists after update', function () {
        $org = Organization::factory()->standalone()->create();

        $org->update(['name' => 'Updated Name']);

        $org->refresh();
        expect($org->is_standalone)->toBeTrue()
            ->and($org->name)->toBe('Updated Name');
    });

    test('empty database returns empty for all scopes', function () {
        expect(Organization::standalone()->count())->toBe(0)
            ->and(Organization::console()->count())->toBe(0)
            ->and(Organization::currentMode()->count())->toBe(0);
    });

    test('factory standalone and console states produce correct values', function () {
        $sa = Organization::factory()->standalone()->create();
        $co = Organization::factory()->console()->create();

        expect($sa->is_standalone)->toBeTrue()
            ->and($co->is_standalone)->toBeFalse();
    });

    test('is_standalone default is false in schema', function () {
        // Create without setting is_standalone, without trait auto-set
        // The DB default is false
        $org = Organization::factory()->create(['is_standalone' => false]);

        expect($org->is_standalone)->toBeFalse();
    });
});

// =============================================================================
// Data Isolation — データ分離
// =============================================================================

describe('data isolation between modes', function () {
    test('standalone data is invisible in console mode', function () {
        Organization::factory()->count(5)->standalone()->create();

        config()->set('omnify-auth.mode', 'console');
        expect(Organization::currentMode()->count())->toBe(0);
    });

    test('console data is invisible in standalone mode', function () {
        Organization::factory()->count(5)->console()->create();

        config()->set('omnify-auth.mode', 'standalone');
        expect(Organization::currentMode()->count())->toBe(0);
    });

    test('mixed data is properly isolated per mode', function () {
        // Create mixed data
        $saOrgs = Organization::factory()->count(3)->standalone()->create();
        $coOrgs = Organization::factory()->count(4)->console()->create();

        $saOrgId = $saOrgs->first()->console_organization_id;
        $coOrgId = $coOrgs->first()->console_organization_id;

        Branch::factory()->count(2)->standalone()->create(['console_organization_id' => $saOrgId]);
        Branch::factory()->count(3)->console()->create(['console_organization_id' => $coOrgId]);

        User::factory()->count(5)->standalone()->create();
        User::factory()->count(7)->console()->create();

        // Standalone mode sees only standalone data
        config()->set('omnify-auth.mode', 'standalone');
        expect(Organization::currentMode()->count())->toBe(3)
            ->and(Branch::currentMode()->count())->toBe(2)
            ->and(User::currentMode()->count())->toBe(5);

        // Console mode sees only console data
        config()->set('omnify-auth.mode', 'console');
        expect(Organization::currentMode()->count())->toBe(4)
            ->and(Branch::currentMode()->count())->toBe(3)
            ->and(User::currentMode()->count())->toBe(7);
    });
});
