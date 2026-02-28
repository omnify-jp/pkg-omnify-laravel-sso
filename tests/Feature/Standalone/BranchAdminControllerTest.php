<?php

/**
 * BranchAdminController Feature Tests (Standalone Mode)
 *
 * 支店管理コントローラーのフィーチャーテスト（スタンドアローンモード）
 * Tests all CRUD operations for branch management in standalone mode.
 *
 * Routes:
 *   GET    /admin/branches           → index  (Inertia)
 *   GET    /admin/branches/create    → create (Inertia)
 *   POST   /admin/branches           → store  (redirect)
 *   GET    /admin/branches/{branch}/edit → edit (Inertia)
 *   PUT    /admin/branches/{branch}  → update (redirect)
 *   DELETE /admin/branches/{branch}  → destroy (redirect)
 */

use Inertia\Testing\AssertableInertia;
use Omnify\Core\Models\Branch;
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
        $response = $this->get('/admin/branches');

        $response->assertRedirect('/login');
    });

    test('unauthenticated user cannot store branch', function () {
        $response = $this->post('/admin/branches', [
            'name' => 'Test Branch',
            'slug' => 'test-branch',
            'organization_id' => $this->org->id,
        ]);

        $response->assertRedirect('/login');
    });
});

// =============================================================================
// Index — 一覧
// =============================================================================

describe('index', function () {
    test('returns branches index Inertia page', function () {
        $this->actingAs($this->user);

        Branch::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create();

        $response = $this->get('/admin/branches');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/branches/index')
                ->has('branches')
                ->has('organizations')
                ->has('filters')
            );
    });

    test('only shows standalone branches', function () {
        $this->actingAs($this->user);

        Branch::factory()->count(3)->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create();
        Branch::factory()->count(2)->console()->create();

        $response = $this->get('/admin/branches');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/branches/index')
                ->has('branches.data', 3)
            );
    });

    test('supports search filter', function () {
        $this->actingAs($this->user);

        Branch::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create(['name' => 'Tokyo Branch', 'slug' => 'tokyo-branch']);
        Branch::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create(['name' => 'Osaka Branch', 'slug' => 'osaka-branch']);

        $response = $this->get('/admin/branches?search=Tokyo');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/branches/index')
                ->has('branches.data', 1)
                ->where('branches.data.0.name', 'Tokyo Branch')
            );
    });

    test('supports organization filter', function () {
        $this->actingAs($this->user);

        $otherOrg = Organization::factory()->standalone()->create(['is_active' => true]);

        Branch::factory()->count(2)->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create();
        Branch::factory()->standalone()
            ->forOrganization($otherOrg->console_organization_id)
            ->create();

        $response = $this->get('/admin/branches?organization_id=' . $this->org->console_organization_id);

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/branches/index')
                ->has('branches.data', 2)
            );
    });

    test('returns organizations list in props', function () {
        $this->actingAs($this->user);

        Organization::factory()->standalone()->create(['is_active' => true]);

        $response = $this->get('/admin/branches');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/branches/index')
                ->has('organizations')
                ->where('organizations', fn ($orgs) => count($orgs) >= 2)
            );
    });

    test('paginates results', function () {
        $this->actingAs($this->user);

        Branch::factory()->count(20)->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create();

        $response = $this->get('/admin/branches');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/branches/index')
                ->has('branches.data', 15)
                ->where('branches.meta.per_page', 15)
                ->where('branches.meta.total', 20)
                ->where('branches.meta.last_page', 2)
            );
    });
});

// =============================================================================
// Create — 新規作成ページ
// =============================================================================

describe('create', function () {
    test('returns create page with organizations list', function () {
        $this->actingAs($this->user);

        $response = $this->get('/admin/branches/create');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/branches/create')
                ->has('organizations')
            );
    });
});

// =============================================================================
// Store — 作成
// =============================================================================

describe('store', function () {
    test('creates branch with valid data', function () {
        $this->actingAs($this->user);

        $response = $this->post('/admin/branches', [
            'name' => 'New Branch',
            'slug' => 'new-branch',
            'organization_id' => $this->org->id,
            'is_active' => true,
            'is_headquarters' => false,
        ]);

        $response->assertRedirect(route('admin.branches.index'));

        $this->assertDatabaseHas('branches', [
            'name' => 'New Branch',
            'slug' => 'new-branch',
        ]);
    });

    test('sets is_standalone to true', function () {
        $this->actingAs($this->user);

        $this->post('/admin/branches', [
            'name' => 'Standalone Branch',
            'slug' => 'standalone-branch',
            'organization_id' => $this->org->id,
        ]);

        $branch = Branch::where('slug', 'standalone-branch')->first();

        expect($branch)->not->toBeNull()
            ->and($branch->is_standalone)->toBeTrue();
    });

    test('links branch to organization via console_organization_id', function () {
        $this->actingAs($this->user);

        $this->post('/admin/branches', [
            'name' => 'Linked Branch',
            'slug' => 'linked-branch',
            'organization_id' => $this->org->id,
        ]);

        $branch = Branch::where('slug', 'linked-branch')->first();

        expect($branch)->not->toBeNull()
            ->and($branch->console_organization_id)->toBe($this->org->console_organization_id);
    });

    test('validates name is required', function () {
        $this->actingAs($this->user);

        $response = $this->post('/admin/branches', [
            'slug' => 'test-branch',
            'organization_id' => $this->org->id,
        ]);

        $response->assertSessionHasErrors(['name']);
    });

    test('validates slug is required', function () {
        $this->actingAs($this->user);

        $response = $this->post('/admin/branches', [
            'name' => 'Test Branch',
            'organization_id' => $this->org->id,
        ]);

        $response->assertSessionHasErrors(['slug']);
    });

    test('validates slug uniqueness', function () {
        $this->actingAs($this->user);

        Branch::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create(['slug' => 'existing-slug']);

        $response = $this->post('/admin/branches', [
            'name' => 'New Branch',
            'slug' => 'existing-slug',
            'organization_id' => $this->org->id,
        ]);

        $response->assertSessionHasErrors(['slug']);
    });

    test('validates organization_id is required', function () {
        $this->actingAs($this->user);

        $response = $this->post('/admin/branches', [
            'name' => 'Test Branch',
            'slug' => 'test-branch',
        ]);

        $response->assertSessionHasErrors(['organization_id']);
    });

    test('validates organization_id exists', function () {
        $this->actingAs($this->user);

        $response = $this->post('/admin/branches', [
            'name' => 'Test Branch',
            'slug' => 'test-branch',
            'organization_id' => 99999,
        ]);

        $response->assertSessionHasErrors(['organization_id']);
    });

    test('creates headquarters branch', function () {
        $this->actingAs($this->user);

        $this->post('/admin/branches', [
            'name' => 'HQ Branch',
            'slug' => 'hq-branch',
            'organization_id' => $this->org->id,
            'is_headquarters' => true,
        ]);

        $branch = Branch::where('slug', 'hq-branch')->first();

        expect($branch)->not->toBeNull()
            ->and($branch->is_headquarters)->toBeTrue();
    });

    test('redirects to index after store', function () {
        $this->actingAs($this->user);

        $response = $this->post('/admin/branches', [
            'name' => 'Redirect Branch',
            'slug' => 'redirect-branch',
            'organization_id' => $this->org->id,
        ]);

        $response->assertRedirect(route('admin.branches.index'));
        $response->assertSessionHas('success');
    });
});

// =============================================================================
// Edit — 編集ページ
// =============================================================================

describe('edit', function () {
    test('returns edit page with branch data', function () {
        $this->actingAs($this->user);

        $branch = Branch::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create(['name' => 'Editable Branch']);

        $response = $this->get("/admin/branches/{$branch->id}/edit");

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/branches/edit')
                ->has('branch')
                ->where('branch.name', 'Editable Branch')
            );
    });

    test('returns edit page with organizations list', function () {
        $this->actingAs($this->user);

        $branch = Branch::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create();

        $response = $this->get("/admin/branches/{$branch->id}/edit");

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/branches/edit')
                ->has('organizations')
            );
    });

    test('returns 404 for non-existent branch', function () {
        $this->actingAs($this->user);

        $response = $this->get('/admin/branches/99999/edit');

        $response->assertNotFound();
    });
});

// =============================================================================
// Update — 更新
// =============================================================================

describe('update', function () {
    test('updates branch name', function () {
        $this->actingAs($this->user);

        $branch = Branch::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create(['name' => 'Old Name', 'slug' => 'old-slug']);

        $response = $this->put("/admin/branches/{$branch->id}", [
            'name' => 'New Name',
            'slug' => 'old-slug',
        ]);

        $response->assertRedirect(route('admin.branches.index'));

        $branch->refresh();
        expect($branch->name)->toBe('New Name');
    });

    test('updates branch slug', function () {
        $this->actingAs($this->user);

        $branch = Branch::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create(['name' => 'Branch', 'slug' => 'old-slug']);

        $response = $this->put("/admin/branches/{$branch->id}", [
            'name' => 'Branch',
            'slug' => 'new-slug',
        ]);

        $response->assertRedirect(route('admin.branches.index'));

        $branch->refresh();
        expect($branch->slug)->toBe('new-slug');
    });

    test('allows same slug when updating self', function () {
        $this->actingAs($this->user);

        $branch = Branch::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create(['name' => 'Branch', 'slug' => 'my-slug']);

        $response = $this->put("/admin/branches/{$branch->id}", [
            'name' => 'Updated Branch',
            'slug' => 'my-slug',
        ]);

        $response->assertRedirect(route('admin.branches.index'));
        $response->assertSessionHasNoErrors();

        $branch->refresh();
        expect($branch->name)->toBe('Updated Branch');
        expect($branch->slug)->toBe('my-slug');
    });

    test('can change organization', function () {
        $this->actingAs($this->user);

        $newOrg = Organization::factory()->standalone()->create(['is_active' => true]);

        $branch = Branch::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create(['name' => 'Branch', 'slug' => 'branch-slug']);

        $response = $this->put("/admin/branches/{$branch->id}", [
            'name' => 'Branch',
            'slug' => 'branch-slug',
            'organization_id' => $newOrg->id,
        ]);

        $response->assertRedirect(route('admin.branches.index'));

        $branch->refresh();
        expect($branch->console_organization_id)->toBe($newOrg->console_organization_id);
    });

    test('validates slug uniqueness excluding self', function () {
        $this->actingAs($this->user);

        Branch::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create(['slug' => 'taken-slug']);

        $branch = Branch::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create(['name' => 'My Branch', 'slug' => 'my-slug']);

        $response = $this->put("/admin/branches/{$branch->id}", [
            'name' => 'My Branch',
            'slug' => 'taken-slug',
        ]);

        $response->assertSessionHasErrors(['slug']);
    });

    test('redirects after update', function () {
        $this->actingAs($this->user);

        $branch = Branch::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create(['name' => 'Branch', 'slug' => 'branch-slug']);

        $response = $this->put("/admin/branches/{$branch->id}", [
            'name' => 'Updated Branch',
            'slug' => 'branch-slug',
        ]);

        $response->assertRedirect(route('admin.branches.index'));
        $response->assertSessionHas('success');
    });
});

// =============================================================================
// Destroy — 削除
// =============================================================================

describe('destroy', function () {
    test('deletes branch', function () {
        $this->actingAs($this->user);

        $branch = Branch::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create();

        $branchId = $branch->id;

        $response = $this->delete("/admin/branches/{$branchId}");

        $response->assertRedirect(route('admin.branches.index'));

        expect(Branch::find($branchId))->toBeNull();
    });

    test('redirects after delete', function () {
        $this->actingAs($this->user);

        $branch = Branch::factory()->standalone()
            ->forOrganization($this->org->console_organization_id)
            ->create();

        $response = $this->delete("/admin/branches/{$branch->id}");

        $response->assertRedirect(route('admin.branches.index'));
        $response->assertSessionHas('success');
    });

    test('returns 404 for non-existent branch', function () {
        $this->actingAs($this->user);

        $response = $this->delete('/admin/branches/99999');

        $response->assertNotFound();
    });
});
