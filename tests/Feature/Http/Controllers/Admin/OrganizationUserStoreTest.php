<?php

/**
 * OrganizationUserAdminController::store() Feature Tests
 *
 * Tests the POST /admin/organizations/{organization}/users endpoint.
 *
 * Route: POST /admin/organizations/{organization}/users
 * Route name: admin.organizations.users.store
 * Route model binding: organization uses slug
 */

use Illuminate\Support\Facades\Notification;
use Omnify\Core\Models\Branch;
use Omnify\Core\Models\Organization;
use Omnify\Core\Models\Role;
use Omnify\Core\Models\User;
use Omnify\Core\Notifications\WelcomeUserNotification;

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

    $this->role = Role::factory()->create([
        'name' => 'Member',
        'slug' => 'member',
    ]);
});

// =============================================================================
// Adding Existing User — 既存ユーザーの追加
// =============================================================================

describe('store — adding existing user', function () {
    test('add existing user to org with role returns 201 and creates pivot entry', function () {
        $user = User::factory()->standalone()->create(['email' => 'existing@example.com']);

        $response = $this->postJson("/admin/organizations/{$this->org->slug}/users", [
            'email' => 'existing@example.com',
            'role_id' => $this->role->id,
        ]);

        $response->assertCreated()
            ->assertJson(['message' => 'User added to organization.']);

        // Verify pivot entry was created
        expect(
            $user->roles()
                ->wherePivot('console_organization_id', $this->org->console_organization_id)
                ->exists()
        )->toBeTrue();
    });

    test('adding user already in org with same role returns 409 duplicate error', function () {
        $user = User::factory()->standalone()->create(['email' => 'duplicate@example.com']);
        $user->assignRole($this->role, $this->org->console_organization_id);

        $response = $this->postJson("/admin/organizations/{$this->org->slug}/users", [
            'email' => 'duplicate@example.com',
            'role_id' => $this->role->id,
        ]);

        $response->assertStatus(409);
    });

    test('adding user to org with branch scope creates pivot with console_branch_id', function () {
        $branch = Branch::factory()->forOrganization($this->org->console_organization_id)->create();
        $user = User::factory()->standalone()->create(['email' => 'branch-user@example.com']);

        $response = $this->postJson("/admin/organizations/{$this->org->slug}/users", [
            'email' => 'branch-user@example.com',
            'role_id' => $this->role->id,
            'console_branch_id' => $branch->console_branch_id,
        ]);

        $response->assertCreated();

        // Verify pivot entry has console_branch_id
        $assignment = $user->roles()
            ->wherePivot('console_organization_id', $this->org->console_organization_id)
            ->wherePivot('console_branch_id', $branch->console_branch_id)
            ->first();

        expect($assignment)->not->toBeNull();
    });
});

// =============================================================================
// Creating New User — 新規ユーザーの作成
// =============================================================================

describe('store — creating new user', function () {
    test('create new user when email not in system returns 201 and user is created', function () {
        Notification::fake();

        $response = $this->postJson("/admin/organizations/{$this->org->slug}/users", [
            'email' => 'newuser@example.com',
            'name' => 'New User',
            'role_id' => $this->role->id,
        ]);

        $response->assertCreated()
            ->assertJson(['message' => 'User added to organization.']);

        expect(User::where('email', 'newuser@example.com')->exists())->toBeTrue();
    });

    test('new user has is_default_password set to true', function () {
        Notification::fake();

        $this->postJson("/admin/organizations/{$this->org->slug}/users", [
            'email' => 'defaultpw@example.com',
            'name' => 'Default PW User',
            'role_id' => $this->role->id,
        ]);

        $user = User::where('email', 'defaultpw@example.com')->first();

        expect($user)->not->toBeNull()
            ->and($user->is_default_password)->toBeTrue();
    });

    test('new user has is_standalone set to true', function () {
        Notification::fake();

        $this->postJson("/admin/organizations/{$this->org->slug}/users", [
            'email' => 'standalone@example.com',
            'name' => 'Standalone User',
            'role_id' => $this->role->id,
        ]);

        $user = User::where('email', 'standalone@example.com')->first();

        expect($user)->not->toBeNull()
            ->and($user->is_standalone)->toBeTrue();
    });

    test('welcome notification is sent to new user', function () {
        Notification::fake();

        $this->postJson("/admin/organizations/{$this->org->slug}/users", [
            'email' => 'welcome@example.com',
            'name' => 'Welcome User',
            'role_id' => $this->role->id,
        ]);

        $newUser = User::where('email', 'welcome@example.com')->first();

        Notification::assertSentTo($newUser, WelcomeUserNotification::class);
    });

    test('welcome notification is NOT sent when adding an existing user', function () {
        Notification::fake();

        $existingUser = User::factory()->standalone()->create(['email' => 'existing-no-notif@example.com']);

        $this->postJson("/admin/organizations/{$this->org->slug}/users", [
            'email' => 'existing-no-notif@example.com',
            'role_id' => $this->role->id,
        ]);

        Notification::assertNotSentTo($existingUser, WelcomeUserNotification::class);
    });

    test('new user role assignment is created with org scope', function () {
        Notification::fake();

        $this->postJson("/admin/organizations/{$this->org->slug}/users", [
            'email' => 'roleduser@example.com',
            'name' => 'Roled User',
            'role_id' => $this->role->id,
        ]);

        $newUser = User::where('email', 'roleduser@example.com')->first();

        expect(
            $newUser->roles()
                ->wherePivot('console_organization_id', $this->org->console_organization_id)
                ->exists()
        )->toBeTrue();
    });
});

// =============================================================================
// Validation — バリデーション
// =============================================================================

describe('store — validation', function () {
    test('email is required', function () {
        $response = $this->postJson("/admin/organizations/{$this->org->slug}/users", [
            'role_id' => $this->role->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    test('role_id is required', function () {
        $response = $this->postJson("/admin/organizations/{$this->org->slug}/users", [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['role_id']);
    });

    test('name is required when creating a new user', function () {
        // Email does not exist in system
        $response = $this->postJson("/admin/organizations/{$this->org->slug}/users", [
            'email' => 'brandnew@example.com',
            'role_id' => $this->role->id,
            // name intentionally omitted
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    test('name is not required when adding an existing user', function () {
        Notification::fake();

        User::factory()->standalone()->create(['email' => 'existing-no-name@example.com']);

        $response = $this->postJson("/admin/organizations/{$this->org->slug}/users", [
            'email' => 'existing-no-name@example.com',
            'role_id' => $this->role->id,
            // name intentionally omitted — user already exists
        ]);

        $response->assertCreated();
    });

    test('role_id must exist in the roles table', function () {
        $response = $this->postJson("/admin/organizations/{$this->org->slug}/users", [
            'email' => 'test@example.com',
            'name' => 'Test User',
            'role_id' => 'non-existent-role-id',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['role_id']);
    });

    test('console_branch_id must belong to this organization', function () {
        $otherOrg = Organization::factory()->standalone()->create();
        $otherBranch = Branch::factory()->forOrganization($otherOrg->console_organization_id)->create();

        $response = $this->postJson("/admin/organizations/{$this->org->slug}/users", [
            'email' => 'test@example.com',
            'name' => 'Test User',
            'role_id' => $this->role->id,
            'console_branch_id' => $otherBranch->console_branch_id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['console_branch_id']);
    });

    test('email must be valid format', function () {
        $response = $this->postJson("/admin/organizations/{$this->org->slug}/users", [
            'email' => 'not-valid',
            'role_id' => $this->role->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });
});

// =============================================================================
// Edge Cases — エッジケース
// =============================================================================

describe('store — edge cases', function () {
    test('returns 404 for non-existent org slug', function () {
        $response = $this->postJson('/admin/organizations/does-not-exist/users', [
            'email' => 'test@example.com',
            'role_id' => $this->role->id,
        ]);

        $response->assertNotFound();
    });

    test('console_branch_id can be null for org-wide assignment', function () {
        Notification::fake();

        User::factory()->standalone()->create(['email' => 'orgwide@example.com']);

        $response = $this->postJson("/admin/organizations/{$this->org->slug}/users", [
            'email' => 'orgwide@example.com',
            'role_id' => $this->role->id,
            'console_branch_id' => null,
        ]);

        $response->assertCreated();

        $user = User::where('email', 'orgwide@example.com')->first();
        $assignment = $user->roles()
            ->wherePivot('console_organization_id', $this->org->console_organization_id)
            ->first();

        expect($assignment?->pivot?->console_branch_id)->toBeNull();
    });
});
