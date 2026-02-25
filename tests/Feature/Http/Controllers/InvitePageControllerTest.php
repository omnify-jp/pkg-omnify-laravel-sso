<?php

/**
 * InvitePageController Feature Tests
 *
 * Tests for the invite member flow:
 * - GET /admin/iam/invite/create renders the invite form
 * - POST /admin/iam/invite validates input and calls ConsoleApiService
 * - Errors from ConsoleApiService are surfaced to user
 *
 * Invite routes only exist in console mode. Since Pest v4 doesn't allow
 * overriding TestCase per subdirectory, routes are manually registered in
 * beforeEach (same pattern used by StandaloneLoginControllerTest).
 */

use Omnify\SsoClient\Http\Controllers\InvitePageController;
use Omnify\SsoClient\Models\Branch;
use Omnify\SsoClient\Models\Organization;
use Omnify\SsoClient\Models\User;
use Omnify\SsoClient\Services\ConsoleApiService;
use Omnify\SsoClient\Services\ConsoleTokenService;

// Register console-mode invite routes for all tests in this file
// (TestCase defaults to standalone mode — routes aren't auto-loaded)
beforeEach(function () {
    config(['omnify-auth.mode' => 'console']);

    $prefix = config('omnify-auth.routes.access_prefix', 'admin/iam');

    $this->app->make('router')
        ->prefix($prefix)
        ->name('access.')
        ->middleware(['web'])
        ->group(function ($router) {
            $router->get('/invite/create', [InvitePageController::class, 'inviteCreate'])->name('invite.create');
            $router->post('/invite', [InvitePageController::class, 'inviteStore'])->name('invite.store');
        });
});

// =============================================================================
// GET /admin/iam/invite/create
// =============================================================================

describe('invite create page', function () {
    it('renders the invite form with branches from Console API', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $consoleBranches = [
            'organization' => ['id' => 'org-1', 'slug' => 'acme', 'name' => 'Acme Corp'],
            'branches' => [
                ['id' => 'branch-1', 'code' => 'HQ', 'name' => 'Headquarters', 'is_headquarters' => true, 'timezone' => null, 'currency' => null, 'locale' => null],
                ['id' => 'branch-2', 'code' => 'NY', 'name' => 'New York', 'is_headquarters' => false, 'timezone' => null, 'currency' => null, 'locale' => null],
            ],
        ];

        $consoleApi = \Mockery::mock(ConsoleApiService::class);
        $consoleApi->shouldReceive('getInviteBranches')
            ->once()
            ->andReturn($consoleBranches);

        $tokenService = \Mockery::mock(ConsoleTokenService::class);
        $tokenService->shouldReceive('getAccessToken')
            ->once()
            ->andReturn('fake-access-token');

        $this->app->instance(ConsoleApiService::class, $consoleApi);
        $this->app->instance(ConsoleTokenService::class, $tokenService);

        $response = $this->get('/admin/iam/invite/create?org=acme');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/iam/invite-create')
                ->has('branches', 2)
                ->where('invite_org.slug', 'acme')
                ->where('org_slug', 'acme')
            );
    });

    it('falls back to local branches when Console API fails', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $org = Organization::factory()->create([
            'slug' => 'fallback-org',
            'console_organization_id' => 'console-org-1',
        ]);

        Branch::factory()->create([
            'name' => 'Local Branch A',
            'console_organization_id' => 'console-org-1',
            'is_active' => true,
        ]);

        $tokenService = \Mockery::mock(ConsoleTokenService::class);
        $tokenService->shouldReceive('getAccessToken')
            ->once()
            ->andReturn('fake-access-token');

        $consoleApi = \Mockery::mock(ConsoleApiService::class);
        $consoleApi->shouldReceive('getInviteBranches')
            ->once()
            ->andThrow(new \RuntimeException('Console unreachable'));

        $this->app->instance(ConsoleApiService::class, $consoleApi);
        $this->app->instance(ConsoleTokenService::class, $tokenService);

        $response = $this->get('/admin/iam/invite/create?org=fallback-org');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/iam/invite-create')
                ->has('branches', 1)
            );
    });

    it('renders invite form with empty branches when no org context', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $tokenService = \Mockery::mock(ConsoleTokenService::class);
        $tokenService->shouldReceive('getAccessToken')
            ->once()
            ->andReturn(null); // No token

        $this->app->instance(ConsoleTokenService::class, $tokenService);

        $response = $this->get('/admin/iam/invite/create');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/iam/invite-create')
                ->has('branches', 0)
                ->where('invite_org', null)
            );
    });
});

// =============================================================================
// POST /admin/iam/invite
// =============================================================================

describe('invite store', function () {
    it('validates required fields', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post('/admin/iam/invite', []);

        $response->assertSessionHasErrors(['org_slug', 'branch_id', 'emails_raw', 'role']);
    });

    it('validates role must be owner/admin/member', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post('/admin/iam/invite', [
            'org_slug' => 'acme',
            'branch_id' => 'branch-1',
            'emails_raw' => 'test@example.com',
            'role' => 'superadmin',
        ]);

        $response->assertSessionHasErrors(['role']);
    });

    it('returns error when no valid emails provided', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $tokenService = \Mockery::mock(ConsoleTokenService::class);
        $tokenService->shouldReceive('getAccessToken')->andReturn('fake-token');
        $this->app->instance(ConsoleTokenService::class, $tokenService);

        $response = $this->post('/admin/iam/invite', [
            'org_slug' => 'acme',
            'branch_id' => 'branch-1',
            'emails_raw' => 'not-an-email, also-invalid',
            'role' => 'member',
        ]);

        $response->assertSessionHasErrors(['emails_raw']);
    });

    it('sends invitations and redirects on success', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $tokenService = \Mockery::mock(ConsoleTokenService::class);
        $tokenService->shouldReceive('getAccessToken')
            ->once()
            ->andReturn('fake-access-token');

        $consoleApi = \Mockery::mock(ConsoleApiService::class);
        $consoleApi->shouldReceive('inviteMembers')
            ->once()
            ->with('fake-access-token', 'acme', 'branch-1', ['alice@example.com', 'bob@example.com'], 'member')
            ->andReturn(['sent' => 2, 'skipped' => 0, 'errors' => []]);

        $this->app->instance(ConsoleApiService::class, $consoleApi);
        $this->app->instance(ConsoleTokenService::class, $tokenService);

        $response = $this->post('/admin/iam/invite', [
            'org_slug' => 'acme',
            'branch_id' => 'branch-1',
            'emails_raw' => "alice@example.com\nbob@example.com",
            'role' => 'member',
        ]);

        $response->assertRedirect()
            ->assertSessionHas('success');
    });

    it('skips duplicate emails in the textarea input', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $tokenService = \Mockery::mock(ConsoleTokenService::class);
        $tokenService->shouldReceive('getAccessToken')->andReturn('fake-access-token');

        $consoleApi = \Mockery::mock(ConsoleApiService::class);
        $consoleApi->shouldReceive('inviteMembers')
            ->once()
            ->andReturn(['sent' => 1, 'skipped' => 1, 'errors' => []]);

        $this->app->instance(ConsoleApiService::class, $consoleApi);
        $this->app->instance(ConsoleTokenService::class, $tokenService);

        $response = $this->post('/admin/iam/invite', [
            'org_slug' => 'acme',
            'branch_id' => 'branch-1',
            'emails_raw' => "alice@example.com\nalice@example.com",
            'role' => 'admin',
        ]);

        // Should succeed (Console handles skip logic)
        $response->assertRedirect()
            ->assertSessionHas('success');
    });

    it('returns error when session expired (no access token)', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $tokenService = \Mockery::mock(ConsoleTokenService::class);
        $tokenService->shouldReceive('getAccessToken')
            ->once()
            ->andReturn(null);

        $this->app->instance(ConsoleTokenService::class, $tokenService);

        $response = $this->post('/admin/iam/invite', [
            'org_slug' => 'acme',
            'branch_id' => 'branch-1',
            'emails_raw' => 'alice@example.com',
            'role' => 'member',
        ]);

        $response->assertSessionHasErrors(['session']);
    });

    it('surfaces Console API errors back to the user', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $tokenService = \Mockery::mock(ConsoleTokenService::class);
        $tokenService->shouldReceive('getAccessToken')->andReturn('fake-access-token');

        $consoleApi = \Mockery::mock(ConsoleApiService::class);
        $consoleApi->shouldReceive('inviteMembers')
            ->once()
            ->andThrow(new \RuntimeException('ORGANIZATION_NOT_FOUND'));

        $this->app->instance(ConsoleApiService::class, $consoleApi);
        $this->app->instance(ConsoleTokenService::class, $tokenService);

        $response = $this->post('/admin/iam/invite', [
            'org_slug' => 'acme',
            'branch_id' => 'branch-1',
            'emails_raw' => 'alice@example.com',
            'role' => 'member',
        ]);

        $response->assertSessionHasErrors(['invite']);
    });

    it('parses comma and semicolon separated emails', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $tokenService = \Mockery::mock(ConsoleTokenService::class);
        $tokenService->shouldReceive('getAccessToken')->andReturn('fake-access-token');

        $consoleApi = \Mockery::mock(ConsoleApiService::class);
        $consoleApi->shouldReceive('inviteMembers')
            ->once()
            ->with('fake-access-token', 'acme', 'b1', ['a@x.com', 'b@x.com', 'c@x.com'], 'owner')
            ->andReturn(['sent' => 3, 'skipped' => 0, 'errors' => []]);

        $this->app->instance(ConsoleApiService::class, $consoleApi);
        $this->app->instance(ConsoleTokenService::class, $tokenService);

        $response = $this->post('/admin/iam/invite', [
            'org_slug' => 'acme',
            'branch_id' => 'b1',
            'emails_raw' => 'a@x.com, b@x.com; c@x.com',
            'role' => 'owner',
        ]);

        $response->assertRedirect()
            ->assertSessionHas('success');
    });
});
