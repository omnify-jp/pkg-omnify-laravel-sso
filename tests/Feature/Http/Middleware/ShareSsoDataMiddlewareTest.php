<?php

/**
 * ShareSsoData Middleware Tests
 *
 * Tests that the middleware shares the correct SSO data with Inertia
 * using the package's own models via Testbench.
 */

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Omnify\SsoClient\Database\Factories\BranchFactory;
use Omnify\SsoClient\Database\Factories\OrganizationFactory;
use Omnify\SsoClient\Database\Factories\RoleFactory;
use Omnify\SsoClient\Http\Middleware\ShareSsoData;
use Omnify\SsoClient\Models\Organization;
use Omnify\SsoClient\Models\User;

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Run the middleware and return the resolved SSO payload.
 */
function pkgRunMiddleware(?callable $userResolver = null): array
{
    $middleware = new ShareSsoData;
    $request = Request::create('/test', 'GET');

    if ($userResolver) {
        $request->setUserResolver($userResolver);
    }

    $middleware->handle($request, fn ($r) => new Response('OK'));

    return (Inertia::getShared()['sso'])();
}

// ── Tests ─────────────────────────────────────────────────────────────────────

describe('ShareSsoData Middleware (package)', function () {
    afterEach(fn () => Session::flush());

    // ── Structure ─────────────────────────────────────────────────────────────

    test('returns the top-level sso keys', function () {
        $sso = pkgRunMiddleware();

        expect($sso)->toHaveKeys(['auth', 'organization', 'locale']);
    });

    test('passes request to next middleware and returns its response', function () {
        $middleware = new ShareSsoData;
        $request = Request::create('/test', 'GET');
        $called = false;

        $response = $middleware->handle($request, function ($r) use (&$called) {
            $called = true;

            return new Response('body', 201);
        });

        expect($called)->toBeTrue()
            ->and($response->getStatusCode())->toBe(201);
    });

    // ── Auth – unauthenticated ─────────────────────────────────────────────────

    test('auth has null user and empty arrays when unauthenticated', function () {
        $sso = pkgRunMiddleware();

        expect($sso['auth']['user'])->toBeNull()
            ->and($sso['auth']['permissions'])->toBe([])
            ->and($sso['auth']['roles'])->toBe([]);
    });

    // ── Auth – authenticated ───────────────────────────────────────────────────

    test('auth user is populated when authenticated', function () {
        $user = $this->createUser();

        $sso = pkgRunMiddleware(fn () => $user);

        expect($sso['auth']['user'])->not->toBeNull();
    });

    test('auth roles are empty when user has no assignments', function () {
        $user = $this->createUser();

        $sso = pkgRunMiddleware(fn () => $user);

        expect($sso['auth']['roles'])->toBe([]);
    });

    test('auth roles contain assigned roles with id, name, slug', function () {
        $user = $this->createUser();
        $role = RoleFactory::new()->create(['console_organization_id' => null]);
        $user->roles()->attach($role->id, [
            'console_organization_id' => null,
            'console_branch_id' => null,
        ]);

        $sso = pkgRunMiddleware(fn () => $user);

        $roleIds = array_column($sso['auth']['roles'], 'id');
        expect($sso['auth']['roles'])->not->toBeEmpty()
            ->and($roleIds)->toContain($role->id)
            ->and($sso['auth']['roles'][0])->toHaveKeys(['id', 'name', 'slug'])
            ->and(array_key_exists('description', $sso['auth']['roles'][0]))->toBeFalse();
    });

    test('all assigned roles are returned when user has multiple roles', function () {
        $user = $this->createUser();
        $role1 = RoleFactory::new()->create(['console_organization_id' => null]);
        $role2 = RoleFactory::new()->create(['console_organization_id' => null]);
        $user->roles()->attach($role1->id, ['console_organization_id' => null, 'console_branch_id' => null]);
        $user->roles()->attach($role2->id, ['console_organization_id' => null, 'console_branch_id' => null]);

        $sso = pkgRunMiddleware(fn () => $user);

        expect($sso['auth']['roles'])->toHaveCount(2);
    });

    test('permissions are empty when user model does not have getAllPermissions', function () {
        $user = $this->createUser();

        $sso = pkgRunMiddleware(fn () => $user);

        expect($sso['auth']['permissions'])->toBe([]);
    });

    test('formats permissions as slug objects when user has getAllPermissions', function () {
        $user = new class extends User
        {
            protected $table = 'users';

            public function getForeignKey(): string
            {
                return 'user_id';
            }

            public function getAllPermissions(?string $organizationId = null, ?string $branchId = null): array
            {
                return ['manage-users', 'view-reports'];
            }
        };
        $user->id = (string) Str::uuid();
        $user->name = 'Test';
        $user->email = 'perm-test@example.com';

        $sso = pkgRunMiddleware(fn () => $user);

        expect($sso['auth']['permissions'])
            ->toContain(['slug' => 'manage-users'])
            ->toContain(['slug' => 'view-reports']);
    });

    test('passes session org and branch ids to getAllPermissions', function () {
        $user = new class extends User
        {
            protected $table = 'users';

            public array $capturedArgs = [];

            public function getForeignKey(): string
            {
                return 'user_id';
            }

            public function getAllPermissions(?string $organizationId = null, ?string $branchId = null): array
            {
                $this->capturedArgs = [$organizationId, $branchId];

                return [];
            }
        };
        $user->id = (string) Str::uuid();
        $user->name = 'Test';
        $user->email = 'args-test@example.com';

        $orgId = (string) Str::uuid();
        $branchId = (string) Str::uuid();
        Session::put('current_organization_id', $orgId);
        Session::put('current_branch_id', $branchId);

        pkgRunMiddleware(fn () => $user);

        expect($user->capturedArgs)->toBe([$orgId, $branchId]);
    });

    // ── Organization – no session ─────────────────────────────────────────────

    test('organization is null when no session org id', function () {
        $sso = pkgRunMiddleware();

        expect($sso['organization']['current'])->toBeNull()
            ->and($sso['organization']['branch'])->toBeNull()
            ->and($sso['organization']['organizations'])->toBe([]);
    });

    test('branch is not loaded when branch session is set but org session is missing', function () {
        $org = OrganizationFactory::new()->create();
        $branch = BranchFactory::new()->create([
            'console_organization_id' => $org->console_organization_id,
        ]);
        Session::put('current_branch_id', $branch->console_branch_id);

        $sso = pkgRunMiddleware();

        expect($sso['organization']['current'])->toBeNull()
            ->and($sso['organization']['branch'])->toBeNull();
    });

    // ── Organization – session set ────────────────────────────────────────────

    test('loads organization with required fields when session has current_organization_id', function () {
        $org = OrganizationFactory::new()->create();
        Session::put('current_organization_id', $org->console_organization_id);

        $sso = pkgRunMiddleware();

        expect($sso['organization']['current'])->not->toBeNull()
            ->and($sso['organization']['current']['id'])->toBe($org->id)
            ->and($sso['organization']['current'])->toHaveKeys([
                'id', 'name', 'slug', 'is_active', 'console_organization_id',
            ]);
    });

    test('organization is null when session id does not match any org in db', function () {
        Session::put('current_organization_id', (string) Str::uuid());

        $sso = pkgRunMiddleware();

        expect($sso['organization']['current'])->toBeNull();
    });

    test('organization loads even without authenticated user', function () {
        $org = OrganizationFactory::new()->create();
        Session::put('current_organization_id', $org->console_organization_id);

        $sso = pkgRunMiddleware(); // no user resolver

        expect($sso['auth']['user'])->toBeNull()
            ->and($sso['organization']['current']['id'])->toBe($org->id);
    });

    // ── Branch ────────────────────────────────────────────────────────────────

    test('branch is null when only org session is set', function () {
        $org = OrganizationFactory::new()->create();
        Session::put('current_organization_id', $org->console_organization_id);

        $sso = pkgRunMiddleware();

        expect($sso['organization']['branch'])->toBeNull();
    });

    test('loads branch with required fields when both sessions are set', function () {
        $org = OrganizationFactory::new()->create();
        $branch = BranchFactory::new()->create([
            'console_organization_id' => $org->console_organization_id,
        ]);
        Session::put('current_organization_id', $org->console_organization_id);
        Session::put('current_branch_id', $branch->console_branch_id);

        $sso = pkgRunMiddleware();

        expect($sso['organization']['branch'])->not->toBeNull()
            ->and($sso['organization']['branch']['id'])->toBe($branch->id)
            ->and($sso['organization']['branch'])->toHaveKeys([
                'id', 'name', 'slug', 'is_headquarters', 'is_active',
                'console_branch_id', 'console_organization_id',
            ]);
    });

    test('branch is null when it belongs to a different org', function () {
        $org = OrganizationFactory::new()->create();
        $otherOrg = OrganizationFactory::new()->create();
        $branch = BranchFactory::new()->create([
            'console_organization_id' => $otherOrg->console_organization_id,
        ]);
        Session::put('current_organization_id', $org->console_organization_id);
        Session::put('current_branch_id', $branch->console_branch_id);

        $sso = pkgRunMiddleware();

        expect($sso['organization']['branch'])->toBeNull();
    });

    test('branch is null when session branch id matches no db record', function () {
        $org = OrganizationFactory::new()->create();
        Session::put('current_organization_id', $org->console_organization_id);
        Session::put('current_branch_id', (string) Str::uuid());

        $sso = pkgRunMiddleware();

        expect($sso['organization']['branch'])->toBeNull();
    });

    // ── Organizations list ────────────────────────────────────────────────────

    test('organizations list is empty when user model has no organizations method', function () {
        $user = $this->createUser();

        $sso = pkgRunMiddleware(fn () => $user);

        expect($sso['organization']['organizations'])->toBe([]);
    });

    test('organizations list is populated when user has organizations method', function () {
        $org = OrganizationFactory::new()->create();
        $orgId = $org->id;

        $user = new class extends User
        {
            protected $table = 'users';

            public string $testOrgId = '';

            public function getForeignKey(): string
            {
                return 'user_id';
            }

            public function organizations()
            {
                return Organization::where('id', $this->testOrgId);
            }
        };
        $user->id = (string) Str::uuid();
        $user->name = 'Test';
        $user->email = 'orgs-test@example.com';
        $user->testOrgId = $orgId;

        $sso = pkgRunMiddleware(fn () => $user);

        $orgIds = array_column($sso['organization']['organizations'], 'id');
        expect($sso['organization']['organizations'])->not->toBeEmpty()
            ->and($orgIds)->toContain($orgId);
    });

    // ── Locale ────────────────────────────────────────────────────────────────

    test('locale defaults to en', function () {
        $sso = pkgRunMiddleware();

        expect($sso['locale'])->toBe('en');
    });

    test('locale reflects the current app locale', function () {
        App::setLocale('ja');

        $sso = pkgRunMiddleware();

        expect($sso['locale'])->toBe('ja');
    });
});
