<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Omnify\SsoClient\Models\Branch;
use Omnify\SsoClient\Models\Organization;
use Symfony\Component\HttpFoundation\Response;

/**
 * Share SSO context data with Inertia frontend.
 *
 * Provides auth, organization, and locale data as Inertia shared props
 * under the `sso` key. Designed to work with the SsoProvider React component.
 *
 * Should be added to the web middleware group after HandleInertiaRequests.
 */
class ShareSsoData
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Inertia::share('sso', fn () => $this->buildSsoData($request));

        return $next($request);
    }

    /**
     * Build the SSO shared data payload.
     *
     * @return array<string, mixed>
     */
    protected function buildSsoData(Request $request): array
    {
        return [
            'auth' => $this->buildAuthData($request),
            'organization' => $this->buildOrganizationData($request),
            'locale' => app()->getLocale(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildAuthData(Request $request): array
    {
        $user = $request->user();

        if (! $user) {
            return [
                'user' => null,
                'permissions' => [],
                'roles' => [],
            ];
        }

        $organizationId = session('current_organization_id');
        $branchId = session('current_branch_id');

        $permissions = method_exists($user, 'getAllPermissions')
            ? $user->getAllPermissions($organizationId, $branchId)
            : [];

        $roles = method_exists($user, 'roles')
            ? $user->roles()->select(['roles.id', 'roles.name', 'roles.slug'])->get()->toArray()
            : [];

        return [
            'user' => $user,
            'permissions' => array_map(
                fn (string $slug) => ['slug' => $slug],
                $permissions
            ),
            'roles' => $roles,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildOrganizationData(Request $request): array
    {
        $organizationId = session('current_organization_id');
        $branchId = session('current_branch_id');

        $current = null;
        $branch = null;
        $organizations = [];

        if ($organizationId) {
            $current = Organization::where('console_organization_id', $organizationId)
                ->first(['id', 'name', 'slug', 'is_active', 'console_organization_id']);

            if ($branchId) {
                $branch = Branch::where('console_branch_id', $branchId)
                    ->where('console_organization_id', $organizationId)
                    ->first(['id', 'name', 'slug', 'is_headquarters', 'is_active', 'console_branch_id', 'console_organization_id']);
            }
        }

        // Get all organizations the user has access to
        $user = $request->user();
        if ($user && method_exists($user, 'organizations')) {
            $organizations = $user->organizations()
                ->select(['organizations.id', 'organizations.name', 'organizations.slug', 'organizations.is_active'])
                ->get()
                ->toArray();
        }

        return [
            'current' => $current,
            'branch' => $branch,
            'organizations' => $organizations,
        ];
    }
}
