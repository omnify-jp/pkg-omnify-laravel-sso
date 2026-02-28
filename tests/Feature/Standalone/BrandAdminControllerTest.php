<?php

/**
 * BrandAdminController Feature Tests (Standalone Mode)
 *
 * ブランド管理コントローラーのフィーチャーテスト（スタンドアローンモード）
 * Tests all CRUD operations for brand management in standalone mode.
 *
 * Routes:
 *   GET    /admin/brands           → index  (Inertia)
 *   GET    /admin/brands/create    → create (Inertia)
 *   POST   /admin/brands           → store  (redirect)
 *   GET    /admin/brands/{brand}/edit → edit (Inertia)
 *   PUT    /admin/brands/{brand}   → update (redirect)
 *   DELETE /admin/brands/{brand}   → destroy (redirect)
 */

use Inertia\Testing\AssertableInertia;
use Omnify\Core\Models\Brand;
use Omnify\Core\Models\Organization;
use Omnify\Core\Models\User;

beforeEach(function () {
    config()->set('omnify-auth.mode', 'standalone');

    $this->org = Organization::factory()->standalone()->create([
        'is_active' => true,
    ]);

    $this->user = User::factory()->standalone()->withPassword('password')->create([
        'name' => 'Test Admin',
        'email' => 'admin@test.com',
        'console_organization_id' => $this->org->console_organization_id,
    ]);
});

// =============================================================================
// Auth Guard — 認証ガード
// =============================================================================

describe('auth guard', function () {
    test('unauthenticated user gets redirected from index', function () {
        $response = $this->get('/admin/brands');

        $response->assertRedirect('/login');
    });

    test('unauthenticated user cannot store brand', function () {
        $response = $this->post('/admin/brands', [
            'name' => 'Test Brand',
            'slug' => 'test-brand',
            'organization_id' => $this->org->id,
        ]);

        $response->assertRedirect('/login');
    });

    test('unauthenticated user cannot update brand', function () {
        $brand = Brand::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create();

        $response = $this->put("/admin/brands/{$brand->id}", [
            'name' => 'Updated',
            'slug' => 'updated',
        ]);

        $response->assertRedirect('/login');
    });

    test('unauthenticated user cannot delete brand', function () {
        $brand = Brand::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create();

        $response = $this->delete("/admin/brands/{$brand->id}");

        $response->assertRedirect('/login');
    });
});

// =============================================================================
// Index — 一覧
// =============================================================================

describe('index', function () {
    test('returns brands index Inertia page', function () {
        $this->actingAs($this->user);

        Brand::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create();

        $response = $this->get('/admin/brands');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/brands/index')
                ->has('brands')
                ->has('organizations')
                ->has('filters')
            );
    });

    test('only shows standalone brands', function () {
        $this->actingAs($this->user);

        Brand::factory()->count(3)->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create();
        Brand::factory()->count(2)->console()->create();

        $response = $this->get('/admin/brands');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/brands/index')
                ->has('brands.data', 3)
            );
    });

    test('supports search filter by name', function () {
        $this->actingAs($this->user);

        Brand::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create(['name' => 'Alpha Brand', 'slug' => 'alpha-brand']);
        Brand::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create(['name' => 'Beta Brand', 'slug' => 'beta-brand']);

        $response = $this->get('/admin/brands?search=Alpha');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/brands/index')
                ->has('brands.data', 1)
                ->where('brands.data.0.name', 'Alpha Brand')
            );
    });

    test('supports search filter by slug', function () {
        $this->actingAs($this->user);

        Brand::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create(['name' => 'Brand A', 'slug' => 'brand-alpha']);
        Brand::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create(['name' => 'Brand B', 'slug' => 'brand-beta']);

        $response = $this->get('/admin/brands?search=brand-beta');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/brands/index')
                ->has('brands.data', 1)
                ->where('brands.data.0.slug', 'brand-beta')
            );
    });

    test('supports organization_id filter', function () {
        $this->actingAs($this->user);

        $otherOrg = Organization::factory()->standalone()->create(['is_active' => true]);

        Brand::factory()->count(2)->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create();
        Brand::factory()->standalone()
            ->forOrganization($otherOrg->console_organization_id)
            ->create();

        $response = $this->get('/admin/brands?organization_id=' . $this->org->console_organization_id);

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/brands/index')
                ->has('brands.data', 2)
            );
    });

    test('returns organizations list in props', function () {
        $this->actingAs($this->user);

        Organization::factory()->standalone()->create(['is_active' => true]);

        $response = $this->get('/admin/brands');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/brands/index')
                ->has('organizations')
                ->where('organizations', fn ($orgs) => count($orgs) >= 2)
            );
    });

    test('paginates results', function () {
        $this->actingAs($this->user);

        Brand::factory()->count(20)->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create();

        $response = $this->get('/admin/brands');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/brands/index')
                ->has('brands.data', 15)
                ->where('brands.meta.per_page', 15)
                ->where('brands.meta.total', 20)
                ->where('brands.meta.last_page', 2)
            );
    });

    test('returns pagination links', function () {
        $this->actingAs($this->user);

        Brand::factory()->count(20)->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create();

        $response = $this->get('/admin/brands');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('brands.links.first')
                ->has('brands.links.last')
            );
    });

    test('preserves filters in response', function () {
        $this->actingAs($this->user);

        $response = $this->get('/admin/brands?search=test&organization_id=' . $this->org->console_organization_id);

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.search', 'test')
                ->where('filters.organization_id', $this->org->console_organization_id)
            );
    });

    test('returns empty data when no brands exist', function () {
        $this->actingAs($this->user);

        $response = $this->get('/admin/brands');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('brands.data', 0)
                ->where('brands.meta.total', 0)
            );
    });
});

// =============================================================================
// Create — 新規作成ページ
// =============================================================================

describe('create', function () {
    test('returns create page with organizations list', function () {
        $this->actingAs($this->user);

        $response = $this->get('/admin/brands/create');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/brands/create')
                ->has('organizations')
            );
    });
});

// =============================================================================
// Store — 作成
// =============================================================================

describe('store', function () {
    test('creates brand with valid data', function () {
        $this->actingAs($this->user);

        $response = $this->post('/admin/brands', [
            'name' => 'New Brand',
            'slug' => 'new-brand',
            'organization_id' => $this->org->id,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.brands.index'));

        $this->assertDatabaseHas('brands', [
            'name' => 'New Brand',
            'slug' => 'new-brand',
        ]);
    });

    test('sets is_standalone to true', function () {
        $this->actingAs($this->user);

        $this->post('/admin/brands', [
            'name' => 'Standalone Brand',
            'slug' => 'standalone-brand',
            'organization_id' => $this->org->id,
        ]);

        $brand = Brand::where('slug', 'standalone-brand')->first();

        expect($brand)->not->toBeNull()
            ->and($brand->is_standalone)->toBeTrue();
    });

    test('generates console_brand_id UUID', function () {
        $this->actingAs($this->user);

        $this->post('/admin/brands', [
            'name' => 'UUID Brand',
            'slug' => 'uuid-brand',
            'organization_id' => $this->org->id,
        ]);

        $brand = Brand::where('slug', 'uuid-brand')->first();

        expect($brand)->not->toBeNull()
            ->and($brand->console_brand_id)->not->toBeNull()
            ->and($brand->console_brand_id)->toMatch(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i'
            );
    });

    test('links brand to organization via console_organization_id', function () {
        $this->actingAs($this->user);

        $this->post('/admin/brands', [
            'name' => 'Linked Brand',
            'slug' => 'linked-brand',
            'organization_id' => $this->org->id,
        ]);

        $brand = Brand::where('slug', 'linked-brand')->first();

        expect($brand)->not->toBeNull()
            ->and($brand->console_organization_id)->toBe($this->org->console_organization_id);
    });

    test('validates name is required', function () {
        $this->actingAs($this->user);

        $response = $this->post('/admin/brands', [
            'slug' => 'test-brand',
            'organization_id' => $this->org->id,
        ]);

        $response->assertSessionHasErrors(['name']);
    });

    test('validates slug is required', function () {
        $this->actingAs($this->user);

        $response = $this->post('/admin/brands', [
            'name' => 'Test Brand',
            'organization_id' => $this->org->id,
        ]);

        $response->assertSessionHasErrors(['slug']);
    });

    test('validates slug uniqueness', function () {
        $this->actingAs($this->user);

        Brand::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create(['slug' => 'existing-slug']);

        $response = $this->post('/admin/brands', [
            'name' => 'New Brand',
            'slug' => 'existing-slug',
            'organization_id' => $this->org->id,
        ]);

        $response->assertSessionHasErrors(['slug']);
    });

    test('validates slug max length', function () {
        $this->actingAs($this->user);

        $response = $this->post('/admin/brands', [
            'name' => 'Test Brand',
            'slug' => str_repeat('a', 51),
            'organization_id' => $this->org->id,
        ]);

        $response->assertSessionHasErrors(['slug']);
    });

    test('validates name max length', function () {
        $this->actingAs($this->user);

        $response = $this->post('/admin/brands', [
            'name' => str_repeat('a', 101),
            'slug' => 'test-brand',
            'organization_id' => $this->org->id,
        ]);

        $response->assertSessionHasErrors(['name']);
    });

    test('validates organization_id is required', function () {
        $this->actingAs($this->user);

        $response = $this->post('/admin/brands', [
            'name' => 'Test Brand',
            'slug' => 'test-brand',
        ]);

        $response->assertSessionHasErrors(['organization_id']);
    });

    test('validates organization_id exists', function () {
        $this->actingAs($this->user);

        $response = $this->post('/admin/brands', [
            'name' => 'Test Brand',
            'slug' => 'test-brand',
            'organization_id' => 99999,
        ]);

        $response->assertSessionHasErrors(['organization_id']);
    });

    test('stores optional fields', function () {
        $this->actingAs($this->user);

        $this->post('/admin/brands', [
            'name' => 'Full Brand',
            'slug' => 'full-brand',
            'organization_id' => $this->org->id,
            'description' => 'A test description',
            'logo_url' => 'https://example.com/logo.png',
            'cover_image_url' => 'https://example.com/cover.png',
            'website' => 'https://example.com',
            'is_active' => true,
        ]);

        $brand = Brand::where('slug', 'full-brand')->first();

        expect($brand)->not->toBeNull()
            ->and($brand->description)->toBe('A test description')
            ->and($brand->logo_url)->toBe('https://example.com/logo.png')
            ->and($brand->cover_image_url)->toBe('https://example.com/cover.png')
            ->and($brand->website)->toBe('https://example.com')
            ->and($brand->is_active)->toBeTrue();
    });

    test('redirects to index after store', function () {
        $this->actingAs($this->user);

        $response = $this->post('/admin/brands', [
            'name' => 'Redirect Brand',
            'slug' => 'redirect-brand',
            'organization_id' => $this->org->id,
        ]);

        $response->assertRedirect(route('admin.brands.index'));
        $response->assertSessionHas('success');
    });
});

// =============================================================================
// Edit — 編集ページ
// =============================================================================

describe('edit', function () {
    test('returns edit page with brand data', function () {
        $this->actingAs($this->user);

        $brand = Brand::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create(['name' => 'Editable Brand']);

        $response = $this->get("/admin/brands/{$brand->id}/edit");

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/brands/edit')
                ->has('brand')
                ->where('brand.name', 'Editable Brand')
            );
    });

    test('returns edit page with organizations list', function () {
        $this->actingAs($this->user);

        $brand = Brand::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create();

        $response = $this->get("/admin/brands/{$brand->id}/edit");

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/brands/edit')
                ->has('organizations')
            );
    });

    test('returns 404 for non-existent brand', function () {
        $this->actingAs($this->user);

        $response = $this->get('/admin/brands/99999/edit');

        $response->assertNotFound();
    });
});

// =============================================================================
// Update — 更新
// =============================================================================

describe('update', function () {
    test('updates brand name', function () {
        $this->actingAs($this->user);

        $brand = Brand::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create(['name' => 'Old Name', 'slug' => 'old-slug']);

        $response = $this->put("/admin/brands/{$brand->id}", [
            'name' => 'New Name',
            'slug' => 'old-slug',
        ]);

        $response->assertRedirect(route('admin.brands.index'));

        $brand->refresh();
        expect($brand->name)->toBe('New Name');
    });

    test('updates brand slug', function () {
        $this->actingAs($this->user);

        $brand = Brand::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create(['name' => 'Brand', 'slug' => 'old-slug']);

        $response = $this->put("/admin/brands/{$brand->id}", [
            'name' => 'Brand',
            'slug' => 'new-slug',
        ]);

        $response->assertRedirect(route('admin.brands.index'));

        $brand->refresh();
        expect($brand->slug)->toBe('new-slug');
    });

    test('allows same slug when updating self', function () {
        $this->actingAs($this->user);

        $brand = Brand::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create(['name' => 'Brand', 'slug' => 'my-slug']);

        $response = $this->put("/admin/brands/{$brand->id}", [
            'name' => 'Updated Brand',
            'slug' => 'my-slug',
        ]);

        $response->assertRedirect(route('admin.brands.index'));
        $response->assertSessionHasNoErrors();

        $brand->refresh();
        expect($brand->name)->toBe('Updated Brand');
        expect($brand->slug)->toBe('my-slug');
    });

    test('validates slug uniqueness excluding self', function () {
        $this->actingAs($this->user);

        Brand::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create(['slug' => 'taken-slug']);

        $brand = Brand::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create(['name' => 'My Brand', 'slug' => 'my-slug']);

        $response = $this->put("/admin/brands/{$brand->id}", [
            'name' => 'My Brand',
            'slug' => 'taken-slug',
        ]);

        $response->assertSessionHasErrors(['slug']);
    });

    test('can change organization', function () {
        $this->actingAs($this->user);

        $newOrg = Organization::factory()->standalone()->create(['is_active' => true]);

        $brand = Brand::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create(['name' => 'Brand', 'slug' => 'brand-slug']);

        $response = $this->put("/admin/brands/{$brand->id}", [
            'name' => 'Brand',
            'slug' => 'brand-slug',
            'organization_id' => $newOrg->id,
        ]);

        $response->assertRedirect(route('admin.brands.index'));

        $brand->refresh();
        expect($brand->console_organization_id)->toBe($newOrg->console_organization_id);
    });

    test('updates optional fields', function () {
        $this->actingAs($this->user);

        $brand = Brand::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create(['name' => 'Brand', 'slug' => 'brand-slug']);

        $this->put("/admin/brands/{$brand->id}", [
            'name' => 'Brand',
            'slug' => 'brand-slug',
            'description' => 'Updated description',
            'website' => 'https://updated.com',
            'is_active' => false,
        ]);

        $brand->refresh();
        expect($brand->description)->toBe('Updated description')
            ->and($brand->website)->toBe('https://updated.com')
            ->and($brand->is_active)->toBeFalse();
    });

    test('redirects after update', function () {
        $this->actingAs($this->user);

        $brand = Brand::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create(['name' => 'Brand', 'slug' => 'brand-slug']);

        $response = $this->put("/admin/brands/{$brand->id}", [
            'name' => 'Updated Brand',
            'slug' => 'brand-slug',
        ]);

        $response->assertRedirect(route('admin.brands.index'));
        $response->assertSessionHas('success');
    });
});

// =============================================================================
// Destroy — 削除
// =============================================================================

describe('destroy', function () {
    test('deletes brand', function () {
        $this->actingAs($this->user);

        $brand = Brand::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create();

        $brandId = $brand->id;

        $response = $this->delete("/admin/brands/{$brandId}");

        $response->assertRedirect(route('admin.brands.index'));

        expect(Brand::find($brandId))->toBeNull();
    });

    test('soft deletes brand (recoverable)', function () {
        $this->actingAs($this->user);

        $brand = Brand::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create();

        $brandId = $brand->id;

        $this->delete("/admin/brands/{$brandId}");

        expect(Brand::withTrashed()->find($brandId))->not->toBeNull();
    });

    test('redirects after delete', function () {
        $this->actingAs($this->user);

        $brand = Brand::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create();

        $response = $this->delete("/admin/brands/{$brand->id}");

        $response->assertRedirect(route('admin.brands.index'));
        $response->assertSessionHas('success');
    });

    test('returns 404 for non-existent brand', function () {
        $this->actingAs($this->user);

        $response = $this->delete('/admin/brands/99999');

        $response->assertNotFound();
    });
});
