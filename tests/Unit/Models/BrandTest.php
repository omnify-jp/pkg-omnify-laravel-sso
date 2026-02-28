<?php

/**
 * Brand Model Unit Tests
 *
 * ブランドモデルのユニットテスト
 * Tests Brand model, HasStandaloneScope trait, relationships, and soft deletes.
 */

use Illuminate\Support\Str;
use Omnify\Core\Models\Brand;
use Omnify\Core\Models\Branch;
use Omnify\Core\Models\Organization;

// =============================================================================
// Basic Model Tests — 基本モデルテスト
// =============================================================================

describe('basic model', function () {
    test('can create brand with required fields', function () {
        $consoleBrandId = (string) Str::uuid();
        $consoleOrgId = (string) Str::uuid();

        $brand = Brand::create([
            'console_brand_id' => $consoleBrandId,
            'console_organization_id' => $consoleOrgId,
            'slug' => 'test-brand',
            'name' => 'Test Brand',
        ]);

        expect($brand)->toBeInstanceOf(Brand::class)
            ->and($brand->console_brand_id)->toBe($consoleBrandId)
            ->and($brand->console_organization_id)->toBe($consoleOrgId)
            ->and($brand->slug)->toBe('test-brand')
            ->and($brand->name)->toBe('Test Brand');
    });

    test('brand id is uuid', function () {
        $brand = Brand::factory()->create();

        expect($brand->id)->toBeString()
            ->and(Str::isUuid($brand->id))->toBeTrue();
    });

    test('console_brand_id must be unique', function () {
        $consoleBrandId = (string) Str::uuid();

        Brand::factory()->create(['console_brand_id' => $consoleBrandId]);

        expect(fn () => Brand::factory()->create(['console_brand_id' => $consoleBrandId]))
            ->toThrow(\Illuminate\Database\QueryException::class);
    });

    test('slug must be unique', function () {
        Brand::factory()->create(['slug' => 'unique-slug']);

        expect(fn () => Brand::factory()->create(['slug' => 'unique-slug']))
            ->toThrow(\Illuminate\Database\QueryException::class);
    });

    test('timestamps are automatically set', function () {
        $brand = Brand::factory()->create();

        expect($brand->created_at)->not->toBeNull()
            ->and($brand->updated_at)->not->toBeNull()
            ->and($brand->created_at)->toBeInstanceOf(\Carbon\Carbon::class);
    });
});

// =============================================================================
// HasStandaloneScope — スタンドアローンスコープ
// =============================================================================

describe('HasStandaloneScope', function () {
    test('standalone scope returns only standalone brands', function () {
        Brand::factory()->count(3)->standalone()->create();
        Brand::factory()->count(2)->console()->create();

        expect(Brand::standalone()->count())->toBe(3);
    });

    test('console scope returns only console brands', function () {
        Brand::factory()->count(3)->standalone()->create();
        Brand::factory()->count(2)->console()->create();

        expect(Brand::console()->count())->toBe(2);
    });

    test('currentMode returns standalone brands when mode is standalone', function () {
        config()->set('omnify-auth.mode', 'standalone');

        Brand::factory()->count(4)->standalone()->create();
        Brand::factory()->count(3)->console()->create();

        expect(Brand::currentMode()->count())->toBe(4);
    });

    test('currentMode returns console brands when mode is console', function () {
        config()->set('omnify-auth.mode', 'console');

        Brand::factory()->count(4)->standalone()->create();
        Brand::factory()->count(3)->console()->create();

        expect(Brand::currentMode()->count())->toBe(3);
    });

    test('creating brand in standalone mode auto-sets is_standalone', function () {
        config()->set('omnify-auth.mode', 'standalone');

        $brand = Brand::factory()->create();

        expect($brand->is_standalone)->toBeTrue();
    });

    test('creating brand in console mode auto-sets is_standalone to false', function () {
        config()->set('omnify-auth.mode', 'console');

        $brand = Brand::factory()->create();

        expect($brand->is_standalone)->toBeFalse();
    });

    test('explicit is_standalone overrides auto-detection', function () {
        config()->set('omnify-auth.mode', 'standalone');

        $brand = Brand::create([
            'console_brand_id' => (string) Str::uuid(),
            'console_organization_id' => (string) Str::uuid(),
            'slug' => 'explicit-console',
            'name' => 'Explicit Console Brand',
            'is_standalone' => false,
        ]);

        expect($brand->is_standalone)->toBeFalse();
    });
});

// =============================================================================
// Relationships — リレーションシップ
// =============================================================================

describe('relationships', function () {
    test('brand belongs to organization', function () {
        $org = Organization::factory()->create();
        $brand = Brand::factory()
            ->forOrganization($org->console_organization_id)
            ->create();

        expect($brand->organization)->not->toBeNull()
            ->and($brand->organization->id)->toBe($org->id);
    });

    test('brand has many branches', function () {
        $brand = Brand::factory()->standalone()->create();

        Branch::factory()->count(3)->standalone()->create([
            'console_brand_id' => $brand->console_brand_id,
            'console_organization_id' => $brand->console_organization_id,
        ]);

        expect($brand->branches)->toHaveCount(3);
    });

    test('brand with no branches returns empty collection', function () {
        $brand = Brand::factory()->create();

        expect($brand->branches)->toHaveCount(0);
    });
});

// =============================================================================
// Soft Deletes — ソフトデリート
// =============================================================================

describe('soft deletes', function () {
    test('brand uses soft deletes', function () {
        $brand = Brand::factory()->create();
        $brandId = $brand->id;

        $brand->delete();

        expect(Brand::find($brandId))->toBeNull()
            ->and(Brand::withTrashed()->find($brandId))->not->toBeNull();
    });

    test('soft deleted brand can be restored', function () {
        $brand = Brand::factory()->create();
        $brandId = $brand->id;

        $brand->delete();
        Brand::withTrashed()->find($brandId)->restore();

        expect(Brand::find($brandId))->not->toBeNull();
    });

    test('soft deleted brands respect scope filters', function () {
        $brand = Brand::factory()->standalone()->create();
        $brand->delete();

        expect(Brand::standalone()->count())->toBe(0)
            ->and(Brand::withTrashed()->standalone()->count())->toBe(1);
    });
});

// =============================================================================
// Query Tests — クエリテスト
// =============================================================================

describe('queries', function () {
    test('can find brand by console_brand_id', function () {
        $consoleBrandId = (string) Str::uuid();

        Brand::factory()->create([
            'console_brand_id' => $consoleBrandId,
            'name' => 'Findable Brand',
        ]);

        $found = Brand::where('console_brand_id', $consoleBrandId)->first();

        expect($found)->not->toBeNull()
            ->and($found->name)->toBe('Findable Brand');
    });

    test('can filter brands by organization', function () {
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();

        Brand::factory()->count(3)->forOrganization($org1->console_organization_id)->create();
        Brand::factory()->count(2)->forOrganization($org2->console_organization_id)->create();

        $org1Brands = Brand::where('console_organization_id', $org1->console_organization_id)->get();

        expect($org1Brands)->toHaveCount(3);
    });

    test('can find brand by slug', function () {
        Brand::factory()->create(['slug' => 'my-brand', 'name' => 'My Brand']);

        $found = Brand::where('slug', 'my-brand')->first();

        expect($found)->not->toBeNull()
            ->and($found->name)->toBe('My Brand');
    });
});

// =============================================================================
// Factory Tests — ファクトリーテスト
// =============================================================================

describe('factory', function () {
    test('factory creates valid brand', function () {
        $brand = Brand::factory()->create();

        expect($brand)->toBeInstanceOf(Brand::class)
            ->and($brand->id)->toBeString()
            ->and(Str::isUuid($brand->id))->toBeTrue()
            ->and(Str::isUuid($brand->console_brand_id))->toBeTrue()
            ->and(Str::isUuid($brand->console_organization_id))->toBeTrue()
            ->and($brand->slug)->toBeString()
            ->and($brand->name)->toBeString();
    });

    test('factory creates multiple unique brands', function () {
        $brands = Brand::factory()->count(5)->create();

        expect($brands)->toHaveCount(5);

        $slugs = $brands->pluck('slug')->toArray();
        expect(array_unique($slugs))->toHaveCount(5);

        $consoleIds = $brands->pluck('console_brand_id')->toArray();
        expect(array_unique($consoleIds))->toHaveCount(5);
    });
});
