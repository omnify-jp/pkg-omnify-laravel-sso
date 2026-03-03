<?php

/**
 * OrganizationAdminController::show() Feature Tests
 *
 * 組織詳細ページのフィーチャーテスト
 * Tests the show endpoint for the Admin Organization Detail Page.
 *
 * Route: GET /admin/organizations/{organization} (route key: slug)
 *
 * NOTE: Admin route middleware is set to ['api'] in the test environment,
 * so any authenticated user can access admin routes.
 * Auth/guard tests are already covered in AdminGuardTest.php.
 */

use Inertia\Testing\AssertableInertia;
use Omnify\Core\Models\Branch;
use Omnify\Core\Models\Location;
use Omnify\Core\Models\Organization;
use Omnify\Core\Models\Role;
use Omnify\Core\Models\User;

beforeEach(function () {
    config()->set('omnify-auth.mode', 'standalone');

    // Bypass core.admin guard for these page-route tests.
    // Auth/guard behaviour is already covered by AdminGuardTest.php.
    $this->withoutMiddleware(\Omnify\Core\Http\Middleware\AdminAuthenticate::class);

    $this->adminUser = User::factory()->standalone()->withPassword('password')->create([
        'name' => 'Admin',
        'email' => 'admin@test.com',
    ]);

    $this->actingAs($this->adminUser);

    $this->org = Organization::factory()->standalone()->create([
        'name' => 'Test Organization',
        'slug' => 'test-organization',
        'is_active' => true,
    ]);
});

// =============================================================================
// Happy Path — 正常系
// =============================================================================

describe('show — happy path', function () {
    test('admin can access organization detail page', function () {
        $response = $this->get("/admin/organizations/{$this->org->slug}");

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/organizations/show')
            );
    });

    test('page returns correct Inertia props', function () {
        $response = $this->get("/admin/organizations/{$this->org->slug}");

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/organizations/show')
                ->has('organization')
                ->has('branches')
                ->has('branches.data')
                ->has('branches.meta')
                ->has('locations')
                ->has('locations.data')
                ->has('locations.meta')
                ->has('users')
                ->has('users.data')
                ->has('users.meta')
                ->has('tab')
                ->has('filters')
            );
    });

    test('organization prop contains the correct organization', function () {
        $response = $this->get("/admin/organizations/{$this->org->slug}");

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/organizations/show')
                ->where('organization.slug', $this->org->slug)
                ->where('organization.name', $this->org->name)
            );
    });

    test('default tab is general when no tab param is provided', function () {
        $response = $this->get("/admin/organizations/{$this->org->slug}");

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('tab', 'general')
            );
    });

    test('tab query param is passed through to Inertia props', function () {
        $response = $this->get("/admin/organizations/{$this->org->slug}?tab=branches");

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('tab', 'branches')
            );
    });
});

// =============================================================================
// Branches Filtering — ブランチのフィルタリング
// =============================================================================

describe('branches are filtered by organization', function () {
    test('only branches belonging to this organization are returned', function () {
        $otherOrg = Organization::factory()->standalone()->create();

        Branch::factory()->count(3)->forOrganization($this->org->console_organization_id)->create();
        Branch::factory()->count(2)->forOrganization($otherOrg->console_organization_id)->create();

        $response = $this->get("/admin/organizations/{$this->org->slug}");

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('branches.meta.total', 3)
                ->has('branches.data', 3)
            );
    });

    test('org with no branches returns empty branches data', function () {
        $response = $this->get("/admin/organizations/{$this->org->slug}");

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('branches.meta.total', 0)
                ->has('branches.data', 0)
            );
    });
});

// =============================================================================
// Locations Filtering — ロケーションのフィルタリング
// =============================================================================

describe('locations are filtered by organization', function () {
    test('only locations belonging to this organization are returned', function () {
        $otherOrg = Organization::factory()->standalone()->create();
        $branch = Branch::factory()->forOrganization($this->org->console_organization_id)->create();
        $otherBranch = Branch::factory()->forOrganization($otherOrg->console_organization_id)->create();

        Location::factory()->count(4)->forBranch($branch->console_branch_id, $this->org->console_organization_id)->create();
        Location::factory()->count(2)->forBranch($otherBranch->console_branch_id, $otherOrg->console_organization_id)->create();

        $response = $this->get("/admin/organizations/{$this->org->slug}");

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('locations.meta.total', 4)
                ->has('locations.data', 4)
            );
    });

    test('org with no locations returns empty locations data', function () {
        $response = $this->get("/admin/organizations/{$this->org->slug}");

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('locations.meta.total', 0)
                ->has('locations.data', 0)
            );
    });
});

// =============================================================================
// Users Filtering — ユーザーのフィルタリング
// =============================================================================

describe('users are filtered by organization', function () {
    test('only users with roles scoped to this organization are returned', function () {
        $otherOrg = Organization::factory()->standalone()->create();

        $role = Role::factory()->create(['name' => 'Member', 'slug' => 'member']);

        $userInOrg = User::factory()->standalone()->create();
        $userInOrg->assignRole($role, $this->org->console_organization_id);

        $userInOtherOrg = User::factory()->standalone()->create();
        $userInOtherOrg->assignRole($role, $otherOrg->console_organization_id);

        $userWithNoRole = User::factory()->standalone()->create();

        $response = $this->get("/admin/organizations/{$this->org->slug}");

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('users.meta.total', 1)
                ->has('users.data', 1)
            );
    });

    test('org with no users returns empty users data', function () {
        $response = $this->get("/admin/organizations/{$this->org->slug}");

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('users.meta.total', 0)
                ->has('users.data', 0)
            );
    });

    test('user data includes role_name from role_user_pivot', function () {
        $role = Role::factory()->create(['name' => 'Manager', 'slug' => 'manager']);
        $user = User::factory()->standalone()->create(['name' => 'Jane Doe', 'email' => 'jane@test.com']);
        $user->assignRole($role, $this->org->console_organization_id);

        $response = $this->get("/admin/organizations/{$this->org->slug}");

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.name', 'Jane Doe')
                ->where('users.data.0.email', 'jane@test.com')
                ->where('users.data.0.role_name', 'Manager')
            );
    });
});

// =============================================================================
// Edge Cases — エッジケース
// =============================================================================

describe('edge cases', function () {
    test('non-existent org slug returns 404', function () {
        $response = $this->get('/admin/organizations/does-not-exist');

        $response->assertNotFound();
    });

    test('soft-deleted organization returns 404', function () {
        $org = Organization::factory()->standalone()->create(['slug' => 'to-be-deleted']);
        $org->delete();

        $response = $this->get('/admin/organizations/to-be-deleted');

        $response->assertNotFound();
    });

    test('org with no branches, locations, or users returns empty data arrays', function () {
        $response = $this->get("/admin/organizations/{$this->org->slug}");

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('branches.data', 0)
                ->has('locations.data', 0)
                ->has('users.data', 0)
            );
    });
});

// =============================================================================
// Pagination — ページネーション
// =============================================================================

describe('pagination', function () {
    test('branches pagination params work correctly', function () {
        Branch::factory()->count(20)->forOrganization($this->org->console_organization_id)->create();

        $response = $this->get("/admin/organizations/{$this->org->slug}?branches_page=2");

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('branches.meta.current_page', 2)
                ->where('branches.meta.per_page', 15)
                ->where('branches.meta.total', 20)
                ->where('branches.meta.last_page', 2)
            );
    });

    test('branches pagination meta has correct shape', function () {
        $response = $this->get("/admin/organizations/{$this->org->slug}");

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('branches.meta.current_page')
                ->has('branches.meta.last_page')
                ->has('branches.meta.per_page')
                ->has('branches.meta.total')
            );
    });
});

// =============================================================================
// Location Branch Filter — ロケーションのブランチフィルタ
// =============================================================================

describe('location branch_id filter', function () {
    test('locations can be filtered by branch_id', function () {
        $branchA = Branch::factory()->forOrganization($this->org->console_organization_id)->create();
        $branchB = Branch::factory()->forOrganization($this->org->console_organization_id)->create();

        Location::factory()->count(3)->forBranch($branchA->console_branch_id, $this->org->console_organization_id)->create();
        Location::factory()->count(2)->forBranch($branchB->console_branch_id, $this->org->console_organization_id)->create();

        $response = $this->get("/admin/organizations/{$this->org->slug}?branch_id={$branchA->console_branch_id}");

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('locations.meta.total', 3)
                ->has('locations.data', 3)
            );
    });

    test('branch_id filter is passed through in filters prop', function () {
        $branch = Branch::factory()->forOrganization($this->org->console_organization_id)->create();

        $response = $this->get("/admin/organizations/{$this->org->slug}?branch_id={$branch->console_branch_id}");

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.branch_id', $branch->console_branch_id)
            );
    });
});
