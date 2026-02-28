<?php

/**
 * OrganizationAdminController (Standalone) Feature Tests
 *
 * 組織管理コントローラーのスタンドアローンモード完全テスト
 * Tests: index (Inertia), store (JSON 201), update (JSON), destroy (redirect).
 *
 * Routes: /admin/organizations (standalone admin, middleware: web + auth)
 */

use Inertia\Testing\AssertableInertia;
use Omnify\Core\Models\Organization;
use Omnify\Core\Models\User;

beforeEach(function () {
    config()->set('omnify-auth.mode', 'standalone');
});

// =============================================================================
// Auth guard — 認証ガード
// =============================================================================

describe('auth guard', function () {
    test('unauthenticated user gets redirected from index', function () {
        $response = $this->get('/admin/organizations');

        $response->assertRedirect('/login');
    });

    test('unauthenticated user cannot store organization', function () {
        $response = $this->postJson('/admin/organizations', [
            'name' => 'Test Org',
            'slug' => 'test-org',
        ]);

        $response->assertStatus(401);
    });

    test('unauthenticated user cannot update organization', function () {
        $org = Organization::factory()->standalone()->create();

        $response = $this->putJson("/admin/organizations/{$org->id}", [
            'name' => 'Updated',
            'slug' => 'updated',
        ]);

        $response->assertStatus(401);
    });

    test('unauthenticated user cannot delete organization', function () {
        $org = Organization::factory()->standalone()->create();

        $response = $this->delete("/admin/organizations/{$org->id}");

        $response->assertRedirect('/login');
    });
});

// =============================================================================
// Index — 一覧表示
// =============================================================================

describe('index', function () {
    beforeEach(function () {
        $this->user = User::factory()->standalone()->withPassword('test')->create();
    });

    test('returns organizations index Inertia page', function () {
        $response = $this->actingAs($this->user)->get('/admin/organizations');

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page->component('admin/organizations/index')
        );
    });

    test('returns paginated organizations with 15 per page', function () {
        Organization::factory()->count(20)->standalone()->create(['is_active' => true]);

        $response = $this->actingAs($this->user)->get('/admin/organizations');

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('admin/organizations/index')
                ->has('organizations.data', 15)
                ->where('organizations.meta.per_page', 15)
                ->where('organizations.meta.total', 20)
                ->where('organizations.meta.last_page', 2)
        );
    });

    test('only shows standalone organizations, not console', function () {
        Organization::factory()->count(3)->standalone()->create(['is_active' => true]);
        Organization::factory()->count(2)->console()->create(['is_active' => true]);

        $response = $this->actingAs($this->user)->get('/admin/organizations');

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('organizations.data', 3)
                ->where('organizations.meta.total', 3)
        );
    });

    test('supports search filter by name', function () {
        Organization::factory()->standalone()->create(['name' => 'Alpha Corp', 'is_active' => true]);
        Organization::factory()->standalone()->create(['name' => 'Beta Inc', 'is_active' => true]);
        Organization::factory()->standalone()->create(['name' => 'Gamma LLC', 'is_active' => true]);

        $response = $this->actingAs($this->user)->get('/admin/organizations?q=Alpha');

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('organizations.data', 1)
                ->where('organizations.data.0.name', 'Alpha Corp')
        );
    });

    test('supports search filter by slug', function () {
        Organization::factory()->standalone()->create([
            'name' => 'Organization A',
            'slug' => 'alpha-corp',
            'is_active' => true,
        ]);
        Organization::factory()->standalone()->create([
            'name' => 'Organization B',
            'slug' => 'beta-inc',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->get('/admin/organizations?q=beta-inc');

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('organizations.data', 1)
                ->where('organizations.data.0.slug', 'beta-inc')
        );
    });

    test('supports is_active filter', function () {
        Organization::factory()->count(3)->standalone()->create(['is_active' => true]);
        Organization::factory()->count(2)->standalone()->create(['is_active' => false]);

        $response = $this->actingAs($this->user)
            ->get('/admin/organizations?filter[is_active]=1');

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('organizations.data', 3)
                ->where('organizations.meta.total', 3)
        );
    });

    test('supports created_at date range filter', function () {
        Organization::factory()->standalone()->create([
            'name' => 'Old Org',
            'is_active' => true,
            'created_at' => '2024-01-01 00:00:00',
        ]);
        Organization::factory()->standalone()->create([
            'name' => 'Recent Org',
            'is_active' => true,
            'created_at' => '2025-06-15 00:00:00',
        ]);
        Organization::factory()->standalone()->create([
            'name' => 'Newest Org',
            'is_active' => true,
            'created_at' => '2025-12-01 00:00:00',
        ]);

        $response = $this->actingAs($this->user)
            ->get('/admin/organizations?filter[created_at_from]=2025-06-01&filter[created_at_to]=2025-06-30');

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('organizations.data', 1)
                ->where('organizations.data.0.name', 'Recent Org')
        );
    });

    test('supports sorting by name ascending', function () {
        Organization::factory()->standalone()->create(['name' => 'Charlie', 'is_active' => true]);
        Organization::factory()->standalone()->create(['name' => 'Alpha', 'is_active' => true]);
        Organization::factory()->standalone()->create(['name' => 'Bravo', 'is_active' => true]);

        $response = $this->actingAs($this->user)
            ->get('/admin/organizations?sort=name');

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('organizations.data.0.name', 'Alpha')
                ->where('organizations.data.1.name', 'Bravo')
                ->where('organizations.data.2.name', 'Charlie')
        );
    });

    test('supports sorting by name descending', function () {
        Organization::factory()->standalone()->create(['name' => 'Charlie', 'is_active' => true]);
        Organization::factory()->standalone()->create(['name' => 'Alpha', 'is_active' => true]);
        Organization::factory()->standalone()->create(['name' => 'Bravo', 'is_active' => true]);

        $response = $this->actingAs($this->user)
            ->get('/admin/organizations?sort=-name');

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('organizations.data.0.name', 'Charlie')
                ->where('organizations.data.1.name', 'Bravo')
                ->where('organizations.data.2.name', 'Alpha')
        );
    });

    test('supports sorting by created_at', function () {
        Organization::factory()->standalone()->create([
            'name' => 'First',
            'is_active' => true,
            'created_at' => '2025-01-01 00:00:00',
        ]);
        Organization::factory()->standalone()->create([
            'name' => 'Second',
            'is_active' => true,
            'created_at' => '2025-06-01 00:00:00',
        ]);
        Organization::factory()->standalone()->create([
            'name' => 'Third',
            'is_active' => true,
            'created_at' => '2025-12-01 00:00:00',
        ]);

        $response = $this->actingAs($this->user)
            ->get('/admin/organizations?sort=created_at');

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('organizations.data.0.name', 'First')
                ->where('organizations.data.1.name', 'Second')
                ->where('organizations.data.2.name', 'Third')
        );
    });

    test('passes filters back in response', function () {
        $response = $this->actingAs($this->user)
            ->get('/admin/organizations?q=test&sort=-name&filter[is_active]=1');

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('filters.q', 'test')
                ->where('filters.sort', '-name')
                ->where('filters.filter.is_active', '1')
        );
    });

    test('returns empty data when no organizations exist', function () {
        $response = $this->actingAs($this->user)->get('/admin/organizations');

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('organizations.data', 0)
                ->where('organizations.meta.total', 0)
        );
    });
});

// =============================================================================
// Store — 作成
// =============================================================================

describe('store', function () {
    beforeEach(function () {
        $this->user = User::factory()->standalone()->withPassword('test')->create();
    });

    test('creates organization via JSON request', function () {
        $response = $this->actingAs($this->user)->postJson('/admin/organizations', [
            'name' => 'New Organization',
            'slug' => 'new-organization',
            'is_active' => true,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('organizations', [
            'name' => 'New Organization',
            'slug' => 'new-organization',
            'is_active' => true,
        ]);
    });

    test('sets is_standalone to true', function () {
        $this->actingAs($this->user)->postJson('/admin/organizations', [
            'name' => 'Standalone Org',
            'slug' => 'standalone-org',
            'is_active' => true,
        ]);

        $org = Organization::where('slug', 'standalone-org')->first();

        expect($org)->not->toBeNull()
            ->and($org->is_standalone)->toBeTrue();
    });

    test('generates console_organization_id UUID', function () {
        $this->actingAs($this->user)->postJson('/admin/organizations', [
            'name' => 'UUID Org',
            'slug' => 'uuid-org',
            'is_active' => true,
        ]);

        $org = Organization::where('slug', 'uuid-org')->first();

        expect($org)->not->toBeNull()
            ->and($org->console_organization_id)->not->toBeNull()
            ->and($org->console_organization_id)->toMatch(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i'
            );
    });

    test('validates name is required', function () {
        $response = $this->actingAs($this->user)->postJson('/admin/organizations', [
            'slug' => 'test-org',
            'is_active' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    });

    test('validates slug is required', function () {
        $response = $this->actingAs($this->user)->postJson('/admin/organizations', [
            'name' => 'Test Org',
            'is_active' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    });

    test('validates slug uniqueness', function () {
        Organization::factory()->standalone()->create(['slug' => 'taken-slug']);

        $response = $this->actingAs($this->user)->postJson('/admin/organizations', [
            'name' => 'Another Org',
            'slug' => 'taken-slug',
            'is_active' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    });

    test('validates slug max length', function () {
        $response = $this->actingAs($this->user)->postJson('/admin/organizations', [
            'name' => 'Test Org',
            'slug' => str_repeat('a', 256),
            'is_active' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    });

    test('returns 422 with validation errors for empty payload', function () {
        $response = $this->actingAs($this->user)->postJson('/admin/organizations', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'slug']);
    });

    test('returns 201 with message on success', function () {
        $response = $this->actingAs($this->user)->postJson('/admin/organizations', [
            'name' => 'Success Org',
            'slug' => 'success-org',
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJson(['message' => 'Organization created.']);
    });
});

// =============================================================================
// Update — 更新
// =============================================================================

describe('update', function () {
    beforeEach(function () {
        $this->user = User::factory()->standalone()->withPassword('test')->create();
        $this->org = Organization::factory()->standalone()->create([
            'name' => 'Original Name',
            'slug' => 'original-slug',
            'is_active' => true,
        ]);
    });

    test('updates organization name', function () {
        $response = $this->actingAs($this->user)
            ->putJson("/admin/organizations/{$this->org->id}", [
                'name' => 'Updated Name',
                'slug' => 'original-slug',
                'is_active' => true,
            ]);

        $response->assertOk();

        $this->org->refresh();
        expect($this->org->name)->toBe('Updated Name');
    });

    test('updates organization slug', function () {
        $response = $this->actingAs($this->user)
            ->putJson("/admin/organizations/{$this->org->id}", [
                'name' => 'Original Name',
                'slug' => 'updated-slug',
                'is_active' => true,
            ]);

        $response->assertOk();

        $this->org->refresh();
        expect($this->org->slug)->toBe('updated-slug');
    });

    test('allows same slug when updating self', function () {
        $response = $this->actingAs($this->user)
            ->putJson("/admin/organizations/{$this->org->id}", [
                'name' => 'Updated Name',
                'slug' => 'original-slug',
                'is_active' => true,
            ]);

        $response->assertOk();
    });

    test('validates slug uniqueness excluding self', function () {
        Organization::factory()->standalone()->create(['slug' => 'other-slug']);

        $response = $this->actingAs($this->user)
            ->putJson("/admin/organizations/{$this->org->id}", [
                'name' => 'Updated Name',
                'slug' => 'other-slug',
                'is_active' => true,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    });

    test('validates name required', function () {
        $response = $this->actingAs($this->user)
            ->putJson("/admin/organizations/{$this->org->id}", [
                'slug' => 'original-slug',
                'is_active' => true,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    });

    test('returns success message', function () {
        $response = $this->actingAs($this->user)
            ->putJson("/admin/organizations/{$this->org->id}", [
                'name' => 'Updated Name',
                'slug' => 'original-slug',
                'is_active' => true,
            ]);

        $response->assertOk()
            ->assertJson(['message' => 'Organization updated.']);
    });
});

// =============================================================================
// Destroy — 削除
// =============================================================================

describe('destroy', function () {
    beforeEach(function () {
        $this->user = User::factory()->standalone()->withPassword('test')->create();
    });

    test('deletes organization', function () {
        $org = Organization::factory()->standalone()->create();

        $this->actingAs($this->user)->delete("/admin/organizations/{$org->id}");

        $this->assertSoftDeleted('organizations', ['id' => $org->id]);
    });

    test('returns redirect after delete', function () {
        $org = Organization::factory()->standalone()->create();

        $response = $this->actingAs($this->user)
            ->delete("/admin/organizations/{$org->id}");

        $response->assertRedirect(route('admin.organizations.index'));
    });

    test('returns 404 for non-existent organization', function () {
        $response = $this->actingAs($this->user)
            ->deleteJson('/admin/organizations/99999');

        $response->assertStatus(404);
    });
});
