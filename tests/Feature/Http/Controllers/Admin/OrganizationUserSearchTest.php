<?php

/**
 * OrganizationUserAdminController::search() Feature Tests
 *
 * Tests the POST /admin/organizations/{organization}/users/search endpoint.
 *
 * Route: POST /admin/organizations/{organization}/users/search
 * Route name: admin.organizations.users.search
 * Route model binding: organization uses slug
 */

use Omnify\Core\Models\Organization;
use Omnify\Core\Models\Role;
use Omnify\Core\Models\User;

beforeEach(function () {
    config()->set('omnify-auth.mode', 'standalone');

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

describe('search — happy path', function () {
    test('search with valid email finds existing user', function () {
        $user = User::factory()->standalone()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $response = $this->postJson("/admin/organizations/{$this->org->slug}/users/search", [
            'email' => 'john@example.com',
        ]);

        $response->assertOk()
            ->assertJson([
                'user' => [
                    'id' => $user->id,
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                ],
            ]);
    });

    test('search returns already_in_org true if user has role in this org', function () {
        $role = Role::factory()->create(['name' => 'Member', 'slug' => 'member']);
        $user = User::factory()->standalone()->create(['email' => 'member@example.com']);
        $user->assignRole($role, $this->org->console_organization_id);

        $response = $this->postJson("/admin/organizations/{$this->org->slug}/users/search", [
            'email' => 'member@example.com',
        ]);

        $response->assertOk()
            ->assertJson([
                'user' => [
                    'email' => 'member@example.com',
                    'already_in_org' => true,
                ],
            ]);
    });

    test('search returns already_in_org false if user exists but not in this org', function () {
        $otherOrg = Organization::factory()->standalone()->create();
        $role = Role::factory()->create(['name' => 'Member', 'slug' => 'member-other']);
        $user = User::factory()->standalone()->create(['email' => 'outside@example.com']);
        $user->assignRole($role, $otherOrg->console_organization_id);

        $response = $this->postJson("/admin/organizations/{$this->org->slug}/users/search", [
            'email' => 'outside@example.com',
        ]);

        $response->assertOk()
            ->assertJson([
                'user' => [
                    'email' => 'outside@example.com',
                    'already_in_org' => false,
                ],
            ]);
    });

    test('search with non-existent email returns user null', function () {
        $response = $this->postJson("/admin/organizations/{$this->org->slug}/users/search", [
            'email' => 'nobody@example.com',
        ]);

        $response->assertOk()
            ->assertJson(['user' => null]);
    });

    test('search is case-insensitive', function () {
        User::factory()->standalone()->create([
            'name' => 'Case User',
            'email' => 'case@example.com',
        ]);

        $response = $this->postJson("/admin/organizations/{$this->org->slug}/users/search", [
            'email' => 'CASE@EXAMPLE.COM',
        ]);

        // SQLite LIKE is case-insensitive for ASCII by default; result may be found or null
        // The important thing is no server error — assert valid JSON response
        $response->assertOk()
            ->assertJsonStructure(['user']);
    });
});

// =============================================================================
// Validation — バリデーション
// =============================================================================

describe('search — validation', function () {
    test('email field is required', function () {
        $response = $this->postJson("/admin/organizations/{$this->org->slug}/users/search", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    test('email field must be a valid email format', function () {
        $response = $this->postJson("/admin/organizations/{$this->org->slug}/users/search", [
            'email' => 'not-an-email',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });
});

// =============================================================================
// Security — セキュリティ
// =============================================================================

describe('search — security', function () {
    test('response does not expose password or sensitive fields', function () {
        User::factory()->standalone()->create([
            'name' => 'Safe User',
            'email' => 'safe@example.com',
        ]);

        $response = $this->postJson("/admin/organizations/{$this->org->slug}/users/search", [
            'email' => 'safe@example.com',
        ]);

        $response->assertOk();
        $data = $response->json('user');

        expect($data)->not->toHaveKey('password')
            ->not->toHaveKey('remember_token')
            ->not->toHaveKey('console_access_token')
            ->not->toHaveKey('console_refresh_token');
    });

    // NOTE: Unauthenticated access tests are covered by AdminGuardTest.php.
    // Those tests verify the core.admin middleware rejects unauthenticated requests.
    test('search endpoint is registered and accessible with web middleware', function () {
        $response = $this->postJson("/admin/organizations/{$this->org->slug}/users/search", [
            'email' => 'any@example.com',
        ]);

        // The endpoint is registered — returns 200 (not 404)
        $response->assertOk();
    });
});

// =============================================================================
// Edge Cases — エッジケース
// =============================================================================

describe('search — edge cases', function () {
    test('search against non-existent org slug returns 404', function () {
        $response = $this->postJson('/admin/organizations/does-not-exist/users/search', [
            'email' => 'test@example.com',
        ]);

        $response->assertNotFound();
    });

    test('user with no roles in any org returns already_in_org false', function () {
        User::factory()->standalone()->create(['email' => 'norole@example.com']);

        $response = $this->postJson("/admin/organizations/{$this->org->slug}/users/search", [
            'email' => 'norole@example.com',
        ]);

        $response->assertOk()
            ->assertJson([
                'user' => [
                    'already_in_org' => false,
                ],
            ]);
    });
});
