<?php

/**
 * OrganizationUserAdminController::destroy() Feature Tests
 *
 * Tests the DELETE /admin/organizations/{organization}/users/{user} endpoint.
 *
 * Route: DELETE /admin/organizations/{organization}/users/{user}
 * Route name: admin.organizations.users.destroy
 * Route model binding: organization uses slug, user uses id
 */

use Omnify\Core\Models\Branch;
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

    $this->role = Role::factory()->create(['name' => 'Member', 'slug' => 'member']);

    $this->user = User::factory()->standalone()->create([
        'name' => 'Member User',
        'email' => 'member@example.com',
    ]);

    $this->user->assignRole($this->role, $this->org->console_organization_id);
});

// =============================================================================
// Happy Path — 正常系
// =============================================================================

describe('destroy — happy path', function () {
    test('remove user from org deletes role_user_pivot entries for this org', function () {
        $response = $this->deleteJson("/admin/organizations/{$this->org->slug}/users/{$this->user->id}");

        $response->assertOk()
            ->assertJson(['message' => 'User removed from organization.']);

        // Pivot entries for this org should be gone
        expect(
            $this->user->roles()
                ->wherePivot('console_organization_id', $this->org->console_organization_id)
                ->exists()
        )->toBeFalse();
    });

    test('user account still exists after removal from org', function () {
        $userId = $this->user->id;

        $this->deleteJson("/admin/organizations/{$this->org->slug}/users/{$userId}");

        // The User record itself must still exist
        expect(User::find($userId))->not->toBeNull();
    });

    test("user's roles in other orgs are unaffected after removal", function () {
        $otherOrg = Organization::factory()->standalone()->create();
        $otherRole = Role::factory()->create(['name' => 'Manager', 'slug' => 'manager-other']);
        $this->user->assignRole($otherRole, $otherOrg->console_organization_id);

        $this->deleteJson("/admin/organizations/{$this->org->slug}/users/{$this->user->id}");

        // Role in the other org must still exist
        expect(
            $this->user->roles()
                ->wherePivot('console_organization_id', $otherOrg->console_organization_id)
                ->exists()
        )->toBeTrue();
    });

    test('removing user with branch-scoped roles removes all org roles including branch-scoped ones', function () {
        $branch = Branch::factory()->forOrganization($this->org->console_organization_id)->create();
        $branchRole = Role::factory()->create(['name' => 'Staff', 'slug' => 'staff']);

        // Assign an additional branch-scoped role in the same org
        $this->user->assignRole($branchRole, $this->org->console_organization_id, $branch->console_branch_id);

        $response = $this->deleteJson("/admin/organizations/{$this->org->slug}/users/{$this->user->id}");

        $response->assertOk();

        // All roles in this org (both org-wide and branch-scoped) should be removed
        expect(
            $this->user->roles()
                ->wherePivot('console_organization_id', $this->org->console_organization_id)
                ->count()
        )->toBe(0);
    });
});

// =============================================================================
// Not In Org — 未所属ユーザーへの操作
// =============================================================================

describe('destroy — user not in org', function () {
    test('removing user who is not in this org still returns 200 (idempotent detach)', function () {
        $userNotInOrg = User::factory()->standalone()->create([
            'email' => 'notinorg@example.com',
        ]);

        $response = $this->deleteJson("/admin/organizations/{$this->org->slug}/users/{$userNotInOrg->id}");

        // Detach is idempotent — no error even if there was nothing to remove
        $response->assertOk()
            ->assertJson(['message' => 'User removed from organization.']);
    });
});

// =============================================================================
// Edge Cases — エッジケース
// =============================================================================

describe('destroy — edge cases', function () {
    test('returns 404 for non-existent organization slug', function () {
        $response = $this->deleteJson("/admin/organizations/does-not-exist/users/{$this->user->id}");

        $response->assertNotFound();
    });

    test('returns 404 for non-existent user id', function () {
        $response = $this->deleteJson("/admin/organizations/{$this->org->slug}/users/non-existent-user-id");

        $response->assertNotFound();
    });

    test('removing last user leaves empty users list for org', function () {
        $this->deleteJson("/admin/organizations/{$this->org->slug}/users/{$this->user->id}");

        // Verify no users have roles in this org
        $count = User::whereHas('roles', function ($q) {
            $q->wherePivot('console_organization_id', $this->org->console_organization_id);
        })->count();

        expect($count)->toBe(0);
    });

    test('admin user removing themselves from org still removes pivot entries', function () {
        $adminRole = Role::factory()->create(['name' => 'Admin', 'slug' => 'admin-role']);
        $this->adminUser->assignRole($adminRole, $this->org->console_organization_id);

        $response = $this->deleteJson("/admin/organizations/{$this->org->slug}/users/{$this->adminUser->id}");

        $response->assertOk();

        expect(
            $this->adminUser->roles()
                ->wherePivot('console_organization_id', $this->org->console_organization_id)
                ->exists()
        )->toBeFalse();
    });
});
