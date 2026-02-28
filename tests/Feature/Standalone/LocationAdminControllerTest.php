<?php

/**
 * LocationAdminController Feature Tests (Standalone Mode)
 *
 * 拠点管理コントローラーのフィーチャーテスト（スタンドアローンモード）
 * Tests all CRUD operations for location management in standalone mode.
 *
 * Routes:
 *   GET    /admin/locations           → index  (Inertia)
 *   GET    /admin/locations/create    → create (Inertia)
 *   POST   /admin/locations           → store  (redirect)
 *   GET    /admin/locations/{location}/edit → edit (Inertia)
 *   PUT    /admin/locations/{location}  → update (redirect)
 *   DELETE /admin/locations/{location}  → destroy (redirect)
 */

use Inertia\Testing\AssertableInertia;
use Omnify\Core\Models\Branch;
use Omnify\Core\Models\Location;
use Omnify\Core\Models\Organization;
use Omnify\Core\Models\User;

beforeEach(function () {
    config()->set('omnify-auth.mode', 'standalone');

    $this->org = Organization::factory()->standalone()->create([
        'is_active' => true,
    ]);

    $this->branch = Branch::factory()->standalone()->create([
        'console_organization_id' => $this->org->console_organization_id,
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
        $response = $this->get('/admin/locations');

        $response->assertRedirect('/login');
    });

    test('unauthenticated user cannot store location', function () {
        $response = $this->post('/admin/locations', [
            'name' => 'Test Location',
            'code' => 'TST001',
            'branch_id' => $this->branch->id,
            'type' => 'office',
        ]);

        $response->assertRedirect('/login');
    });

    test('unauthenticated user cannot update location', function () {
        $location = Location::factory()->standalone()
            ->forBranch($this->branch->console_branch_id, $this->org->console_organization_id)
            ->create();

        $response = $this->put("/admin/locations/{$location->id}", [
            'name' => 'Updated',
            'code' => 'UPD001',
            'type' => 'office',
        ]);

        $response->assertRedirect('/login');
    });

    test('unauthenticated user cannot delete location', function () {
        $location = Location::factory()->standalone()
            ->forBranch($this->branch->console_branch_id, $this->org->console_organization_id)
            ->create();

        $response = $this->delete("/admin/locations/{$location->id}");

        $response->assertRedirect('/login');
    });
});

// =============================================================================
// Index — 一覧
// =============================================================================

describe('index', function () {
    test('returns locations index Inertia page', function () {
        $this->actingAs($this->user);

        Location::factory()->standalone()
            ->forBranch($this->branch->console_branch_id, $this->org->console_organization_id)
            ->create();

        $response = $this->get('/admin/locations');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/locations/index')
                ->has('locations')
                ->has('branches')
                ->has('organizations')
                ->has('filters')
            );
    });

    test('only shows standalone locations', function () {
        $this->actingAs($this->user);

        Location::factory()->count(3)->standalone()
            ->forBranch($this->branch->console_branch_id, $this->org->console_organization_id)
            ->create();
        Location::factory()->count(2)->console()->create();

        $response = $this->get('/admin/locations');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/locations/index')
                ->has('locations.data', 3)
            );
    });

    test('supports search filter by name', function () {
        $this->actingAs($this->user);

        Location::factory()->standalone()
            ->forBranch($this->branch->console_branch_id, $this->org->console_organization_id)
            ->create(['name' => 'Tokyo Office', 'code' => 'TKO001']);
        Location::factory()->standalone()
            ->forBranch($this->branch->console_branch_id, $this->org->console_organization_id)
            ->create(['name' => 'Osaka Office', 'code' => 'OSK001']);

        $response = $this->get('/admin/locations?search=Tokyo');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/locations/index')
                ->has('locations.data', 1)
                ->where('locations.data.0.name', 'Tokyo Office')
            );
    });

    test('supports search filter by code', function () {
        $this->actingAs($this->user);

        Location::factory()->standalone()
            ->forBranch($this->branch->console_branch_id, $this->org->console_organization_id)
            ->create(['name' => 'Office A', 'code' => 'ALPHA01']);
        Location::factory()->standalone()
            ->forBranch($this->branch->console_branch_id, $this->org->console_organization_id)
            ->create(['name' => 'Office B', 'code' => 'BETA001']);

        $response = $this->get('/admin/locations?search=ALPHA');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/locations/index')
                ->has('locations.data', 1)
                ->where('locations.data.0.code', 'ALPHA01')
            );
    });

    test('supports branch_id filter', function () {
        $this->actingAs($this->user);

        $otherBranch = Branch::factory()->standalone()->create([
            'console_organization_id' => $this->org->console_organization_id,
            'is_active' => true,
        ]);

        Location::factory()->count(2)->standalone()
            ->forBranch($this->branch->console_branch_id, $this->org->console_organization_id)
            ->create();
        Location::factory()->standalone()
            ->forBranch($otherBranch->console_branch_id, $this->org->console_organization_id)
            ->create();

        $response = $this->get('/admin/locations?branch_id=' . $this->branch->console_branch_id);

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/locations/index')
                ->has('locations.data', 2)
            );
    });

    test('supports organization_id filter', function () {
        $this->actingAs($this->user);

        $otherOrg = Organization::factory()->standalone()->create(['is_active' => true]);
        $otherBranch = Branch::factory()->standalone()->create([
            'console_organization_id' => $otherOrg->console_organization_id,
            'is_active' => true,
        ]);

        Location::factory()->count(2)->standalone()
            ->forBranch($this->branch->console_branch_id, $this->org->console_organization_id)
            ->create();
        Location::factory()->standalone()
            ->forBranch($otherBranch->console_branch_id, $otherOrg->console_organization_id)
            ->create();

        $response = $this->get('/admin/locations?organization_id=' . $this->org->console_organization_id);

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/locations/index')
                ->has('locations.data', 2)
            );
    });

    test('supports type filter', function () {
        $this->actingAs($this->user);

        Location::factory()->count(2)->standalone()
            ->forBranch($this->branch->console_branch_id, $this->org->console_organization_id)
            ->create(['type' => 'office']);
        Location::factory()->standalone()
            ->forBranch($this->branch->console_branch_id, $this->org->console_organization_id)
            ->create(['type' => 'warehouse']);

        $response = $this->get('/admin/locations?type=office');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/locations/index')
                ->has('locations.data', 2)
            );
    });

    test('returns branches list in props', function () {
        $this->actingAs($this->user);

        $response = $this->get('/admin/locations');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/locations/index')
                ->has('branches')
            );
    });

    test('returns organizations list in props', function () {
        $this->actingAs($this->user);

        $response = $this->get('/admin/locations');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/locations/index')
                ->has('organizations')
            );
    });

    test('paginates results', function () {
        $this->actingAs($this->user);

        Location::factory()->count(20)->standalone()
            ->forBranch($this->branch->console_branch_id, $this->org->console_organization_id)
            ->create();

        $response = $this->get('/admin/locations');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/locations/index')
                ->has('locations.data', 15)
                ->where('locations.meta.per_page', 15)
                ->where('locations.meta.total', 20)
                ->where('locations.meta.last_page', 2)
            );
    });

    test('preserves filters in response', function () {
        $this->actingAs($this->user);

        $response = $this->get('/admin/locations?search=test&type=office');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.search', 'test')
                ->where('filters.type', 'office')
            );
    });

    test('returns empty data when no locations exist', function () {
        $this->actingAs($this->user);

        $response = $this->get('/admin/locations');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('locations.data', 0)
                ->where('locations.meta.total', 0)
            );
    });
});

// =============================================================================
// Create — 新規作成ページ
// =============================================================================

describe('create', function () {
    test('returns create page with branches and organizations', function () {
        $this->actingAs($this->user);

        $response = $this->get('/admin/locations/create');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/locations/create')
                ->has('branches')
                ->has('organizations')
            );
    });
});

// =============================================================================
// Store — 作成
// =============================================================================

describe('store', function () {
    test('creates location with valid data', function () {
        $this->actingAs($this->user);

        $response = $this->post('/admin/locations', [
            'name' => 'New Location',
            'code' => 'NWL001',
            'branch_id' => $this->branch->id,
            'type' => 'office',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.locations.index'));

        $this->assertDatabaseHas('locations', [
            'name' => 'New Location',
            'code' => 'NWL001',
        ]);
    });

    test('sets is_standalone to true', function () {
        $this->actingAs($this->user);

        $this->post('/admin/locations', [
            'name' => 'Standalone Location',
            'code' => 'STD001',
            'branch_id' => $this->branch->id,
            'type' => 'office',
        ]);

        $location = Location::where('code', 'STD001')->first();

        expect($location)->not->toBeNull()
            ->and($location->is_standalone)->toBeTrue();
    });

    test('generates console_location_id UUID', function () {
        $this->actingAs($this->user);

        $this->post('/admin/locations', [
            'name' => 'UUID Location',
            'code' => 'UUID01',
            'branch_id' => $this->branch->id,
            'type' => 'office',
        ]);

        $location = Location::where('code', 'UUID01')->first();

        expect($location)->not->toBeNull()
            ->and($location->console_location_id)->not->toBeNull()
            ->and($location->console_location_id)->toMatch(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i'
            );
    });

    test('links location to branch via console_branch_id', function () {
        $this->actingAs($this->user);

        $this->post('/admin/locations', [
            'name' => 'Linked Location',
            'code' => 'LNK001',
            'branch_id' => $this->branch->id,
            'type' => 'office',
        ]);

        $location = Location::where('code', 'LNK001')->first();

        expect($location)->not->toBeNull()
            ->and($location->console_branch_id)->toBe($this->branch->console_branch_id)
            ->and($location->console_organization_id)->toBe($this->branch->console_organization_id);
    });

    test('validates name is required', function () {
        $this->actingAs($this->user);

        $response = $this->post('/admin/locations', [
            'code' => 'TST001',
            'branch_id' => $this->branch->id,
            'type' => 'office',
        ]);

        $response->assertSessionHasErrors(['name']);
    });

    test('validates code is required', function () {
        $this->actingAs($this->user);

        $response = $this->post('/admin/locations', [
            'name' => 'Test Location',
            'branch_id' => $this->branch->id,
            'type' => 'office',
        ]);

        $response->assertSessionHasErrors(['code']);
    });

    test('validates branch_id is required', function () {
        $this->actingAs($this->user);

        $response = $this->post('/admin/locations', [
            'name' => 'Test Location',
            'code' => 'TST001',
            'type' => 'office',
        ]);

        $response->assertSessionHasErrors(['branch_id']);
    });

    test('validates branch_id exists', function () {
        $this->actingAs($this->user);

        $response = $this->post('/admin/locations', [
            'name' => 'Test Location',
            'code' => 'TST001',
            'branch_id' => 99999,
            'type' => 'office',
        ]);

        $response->assertSessionHasErrors(['branch_id']);
    });

    test('validates type is required', function () {
        $this->actingAs($this->user);

        $response = $this->post('/admin/locations', [
            'name' => 'Test Location',
            'code' => 'TST001',
            'branch_id' => $this->branch->id,
        ]);

        $response->assertSessionHasErrors(['type']);
    });

    test('validates type enum', function () {
        $this->actingAs($this->user);

        $response = $this->post('/admin/locations', [
            'name' => 'Test Location',
            'code' => 'TST001',
            'branch_id' => $this->branch->id,
            'type' => 'invalid-type',
        ]);

        $response->assertSessionHasErrors(['type']);
    });

    test('accepts all valid type values', function () {
        $this->actingAs($this->user);
        $validTypes = ['office', 'warehouse', 'factory', 'store', 'clinic', 'restaurant', 'other'];

        foreach ($validTypes as $i => $type) {
            $this->post('/admin/locations', [
                'name' => "Location {$i}",
                'code' => "TYPE{$i}",
                'branch_id' => $this->branch->id,
                'type' => $type,
            ]);

            $this->assertDatabaseHas('locations', [
                'code' => "TYPE{$i}",
                'type' => $type,
            ]);
        }
    });

    test('validates name max length', function () {
        $this->actingAs($this->user);

        $response = $this->post('/admin/locations', [
            'name' => str_repeat('a', 151),
            'code' => 'TST001',
            'branch_id' => $this->branch->id,
            'type' => 'office',
        ]);

        $response->assertSessionHasErrors(['name']);
    });

    test('validates code max length', function () {
        $this->actingAs($this->user);

        $response = $this->post('/admin/locations', [
            'name' => 'Test Location',
            'code' => str_repeat('A', 31),
            'branch_id' => $this->branch->id,
            'type' => 'office',
        ]);

        $response->assertSessionHasErrors(['code']);
    });

    test('stores all optional fields', function () {
        $this->actingAs($this->user);

        $this->post('/admin/locations', [
            'name' => 'Full Location',
            'code' => 'FULL01',
            'branch_id' => $this->branch->id,
            'type' => 'office',
            'is_active' => true,
            'address' => '1-2-3 Shibuya',
            'city' => 'Tokyo',
            'state_province' => 'Tokyo',
            'postal_code' => '150-0002',
            'country_code' => 'JP',
            'latitude' => 35.6580,
            'longitude' => 139.7016,
            'phone' => '03-1234-5678',
            'email' => 'office@example.com',
            'timezone' => 'Asia/Tokyo',
            'capacity' => 100,
            'sort_order' => 5,
            'description' => 'Main office in Shibuya',
        ]);

        $location = Location::where('code', 'FULL01')->first();

        expect($location)->not->toBeNull()
            ->and($location->address)->toBe('1-2-3 Shibuya')
            ->and($location->city)->toBe('Tokyo')
            ->and($location->state_province)->toBe('Tokyo')
            ->and($location->postal_code)->toBe('150-0002')
            ->and($location->country_code)->toBe('JP')
            ->and((float) $location->latitude)->toBe(35.658)
            ->and((float) $location->longitude)->toBe(139.7016)
            ->and($location->phone)->toBe('03-1234-5678')
            ->and($location->email)->toBe('office@example.com')
            ->and($location->timezone)->toBe('Asia/Tokyo')
            ->and($location->capacity)->toBe(100)
            ->and($location->sort_order)->toBe(5)
            ->and($location->description)->toBe('Main office in Shibuya');
    });

    test('validates latitude range', function () {
        $this->actingAs($this->user);

        $response = $this->post('/admin/locations', [
            'name' => 'Test',
            'code' => 'TST001',
            'branch_id' => $this->branch->id,
            'type' => 'office',
            'latitude' => 91,
        ]);

        $response->assertSessionHasErrors(['latitude']);
    });

    test('validates longitude range', function () {
        $this->actingAs($this->user);

        $response = $this->post('/admin/locations', [
            'name' => 'Test',
            'code' => 'TST001',
            'branch_id' => $this->branch->id,
            'type' => 'office',
            'longitude' => 181,
        ]);

        $response->assertSessionHasErrors(['longitude']);
    });

    test('validates email format', function () {
        $this->actingAs($this->user);

        $response = $this->post('/admin/locations', [
            'name' => 'Test',
            'code' => 'TST001',
            'branch_id' => $this->branch->id,
            'type' => 'office',
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors(['email']);
    });

    test('redirects to index after store', function () {
        $this->actingAs($this->user);

        $response = $this->post('/admin/locations', [
            'name' => 'Redirect Location',
            'code' => 'RDR001',
            'branch_id' => $this->branch->id,
            'type' => 'office',
        ]);

        $response->assertRedirect(route('admin.locations.index'));
        $response->assertSessionHas('success');
    });
});

// =============================================================================
// Edit — 編集ページ
// =============================================================================

describe('edit', function () {
    test('returns edit page with location data', function () {
        $this->actingAs($this->user);

        $location = Location::factory()->standalone()
            ->forBranch($this->branch->console_branch_id, $this->org->console_organization_id)
            ->create(['name' => 'Editable Location']);

        $response = $this->get("/admin/locations/{$location->id}/edit");

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/locations/edit')
                ->has('location')
                ->where('location.name', 'Editable Location')
            );
    });

    test('returns edit page with branches and organizations', function () {
        $this->actingAs($this->user);

        $location = Location::factory()->standalone()
            ->forBranch($this->branch->console_branch_id, $this->org->console_organization_id)
            ->create();

        $response = $this->get("/admin/locations/{$location->id}/edit");

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/locations/edit')
                ->has('branches')
                ->has('organizations')
            );
    });

    test('returns 404 for non-existent location', function () {
        $this->actingAs($this->user);

        $response = $this->get('/admin/locations/99999/edit');

        $response->assertNotFound();
    });
});

// =============================================================================
// Update — 更新
// =============================================================================

describe('update', function () {
    test('updates location name', function () {
        $this->actingAs($this->user);

        $location = Location::factory()->standalone()
            ->forBranch($this->branch->console_branch_id, $this->org->console_organization_id)
            ->create(['name' => 'Old Name', 'code' => 'OLD001', 'type' => 'office']);

        $response = $this->put("/admin/locations/{$location->id}", [
            'name' => 'New Name',
            'code' => 'OLD001',
            'type' => 'office',
        ]);

        $response->assertRedirect(route('admin.locations.index'));

        $location->refresh();
        expect($location->name)->toBe('New Name');
    });

    test('updates location code', function () {
        $this->actingAs($this->user);

        $location = Location::factory()->standalone()
            ->forBranch($this->branch->console_branch_id, $this->org->console_organization_id)
            ->create(['name' => 'Location', 'code' => 'OLD001', 'type' => 'office']);

        $this->put("/admin/locations/{$location->id}", [
            'name' => 'Location',
            'code' => 'NEW001',
            'type' => 'office',
        ]);

        $location->refresh();
        expect($location->code)->toBe('NEW001');
    });

    test('updates location type', function () {
        $this->actingAs($this->user);

        $location = Location::factory()->standalone()
            ->forBranch($this->branch->console_branch_id, $this->org->console_organization_id)
            ->create(['name' => 'Location', 'code' => 'TST001', 'type' => 'office']);

        $this->put("/admin/locations/{$location->id}", [
            'name' => 'Location',
            'code' => 'TST001',
            'type' => 'warehouse',
        ]);

        $location->refresh();
        expect($location->type)->toBe('warehouse');
    });

    test('can change branch', function () {
        $this->actingAs($this->user);

        $newBranch = Branch::factory()->standalone()->create([
            'console_organization_id' => $this->org->console_organization_id,
            'is_active' => true,
        ]);

        $location = Location::factory()->standalone()
            ->forBranch($this->branch->console_branch_id, $this->org->console_organization_id)
            ->create(['name' => 'Location', 'code' => 'TST001', 'type' => 'office']);

        $response = $this->put("/admin/locations/{$location->id}", [
            'name' => 'Location',
            'code' => 'TST001',
            'type' => 'office',
            'branch_id' => $newBranch->id,
        ]);

        $response->assertRedirect(route('admin.locations.index'));

        $location->refresh();
        expect($location->console_branch_id)->toBe($newBranch->console_branch_id)
            ->and($location->console_organization_id)->toBe($newBranch->console_organization_id);
    });

    test('updates optional fields', function () {
        $this->actingAs($this->user);

        $location = Location::factory()->standalone()
            ->forBranch($this->branch->console_branch_id, $this->org->console_organization_id)
            ->create(['name' => 'Location', 'code' => 'TST001', 'type' => 'office']);

        $this->put("/admin/locations/{$location->id}", [
            'name' => 'Location',
            'code' => 'TST001',
            'type' => 'office',
            'city' => 'Osaka',
            'phone' => '06-1234-5678',
            'capacity' => 50,
        ]);

        $location->refresh();
        expect($location->city)->toBe('Osaka')
            ->and($location->phone)->toBe('06-1234-5678')
            ->and($location->capacity)->toBe(50);
    });

    test('redirects after update', function () {
        $this->actingAs($this->user);

        $location = Location::factory()->standalone()
            ->forBranch($this->branch->console_branch_id, $this->org->console_organization_id)
            ->create(['name' => 'Location', 'code' => 'TST001', 'type' => 'office']);

        $response = $this->put("/admin/locations/{$location->id}", [
            'name' => 'Updated Location',
            'code' => 'TST001',
            'type' => 'office',
        ]);

        $response->assertRedirect(route('admin.locations.index'));
        $response->assertSessionHas('success');
    });
});

// =============================================================================
// Destroy — 削除
// =============================================================================

describe('destroy', function () {
    test('deletes location', function () {
        $this->actingAs($this->user);

        $location = Location::factory()->standalone()
            ->forBranch($this->branch->console_branch_id, $this->org->console_organization_id)
            ->create();

        $locationId = $location->id;

        $response = $this->delete("/admin/locations/{$locationId}");

        $response->assertRedirect(route('admin.locations.index'));

        expect(Location::find($locationId))->toBeNull();
    });

    test('soft deletes location (recoverable)', function () {
        $this->actingAs($this->user);

        $location = Location::factory()->standalone()
            ->forBranch($this->branch->console_branch_id, $this->org->console_organization_id)
            ->create();

        $locationId = $location->id;

        $this->delete("/admin/locations/{$locationId}");

        expect(Location::withTrashed()->find($locationId))->not->toBeNull();
    });

    test('redirects after delete', function () {
        $this->actingAs($this->user);

        $location = Location::factory()->standalone()
            ->forBranch($this->branch->console_branch_id, $this->org->console_organization_id)
            ->create();

        $response = $this->delete("/admin/locations/{$location->id}");

        $response->assertRedirect(route('admin.locations.index'));
        $response->assertSessionHas('success');
    });

    test('returns 404 for non-existent location', function () {
        $this->actingAs($this->user);

        $response = $this->delete('/admin/locations/99999');

        $response->assertNotFound();
    });
});
