<?php

declare(strict_types=1);

namespace Omnify\Core\Http\Middleware;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Omnify\Core\Models\Branch;
use Omnify\Core\Models\Organization;
use Omnify\Core\Services\OrganizationAccessService;

/**
 * Base Inertia middleware provided by Core.
 *
 * Shares organization context, auth, locale, and org settings sections.
 * Host apps should extend this class and add app-specific shared props.
 */
class CoreHandleInertiaRequests extends Middleware
{
    /** @var array<string, mixed>|null */
    protected ?array $organizationDataCache = null;

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth_mode' => config('omnify-auth.mode', 'standalone'),
            // true when org slug is in URL (/@acme/dashboard), false for cookie-only (/dashboard).
            // Frontend uses this to decide: navigate to /@{slug}/... or reload page after org switch.
            'org_url_mode' => config('omnify-auth.routes.org_route_prefix', '') !== '',
            'locale' => app()->getLocale(),
            'auth' => [
                'user' => $request->user(),
            ],
            'organization' => fn () => $this->getOrganizationData($request),
            'org_settings_sections' => fn () => $this->buildOrgSettingsSections(),
        ];
    }

    /**
     * Get organization data with per-request caching.
     *
     * @return array<string, mixed>
     */
    protected function getOrganizationData(Request $request): array
    {
        return $this->organizationDataCache ??= $this->buildOrganizationData($request);
    }

    /**
     * Build organization and branch context data.
     *
     * Resolves current org from route parameter (URL-based) first,
     * then falls back to cookie (for non-org-prefixed routes).
     *
     * @return array{current: Organization|null, slug: string|null, list: array<int, array<string, mixed>>, currentBranch: Branch|null, branches: array<int, array<string, mixed>>}
     */
    protected function buildOrganizationData(Request $request): array
    {
        $user = $request->user();

        $organizations = $this->resolveUserOrganizations($user);

        // Resolve current org: route parameter (URL) > cookie > first user org
        $current = null;
        $routeSlug = $request->route('organization');

        if ($routeSlug) {
            $current = $organizations->firstWhere('slug', $routeSlug);
        }

        if (! $current) {
            $currentOrgId = $request->cookie('current_organization_id');
            $current = $currentOrgId
                ? $organizations->firstWhere('console_organization_id', $currentOrgId)
                : null;
        }

        // Default to first org the user has access to
        $current ??= $organizations->first();

        $branches = Branch::whereIn('console_organization_id', $organizations->pluck('console_organization_id'))
            ->where('is_active', true)
            ->select(['id', 'console_branch_id', 'console_organization_id', 'name', 'slug', 'is_headquarters'])
            ->orderByDesc('is_headquarters')
            ->orderBy('name')
            ->get();

        $currentBranch = null;

        if ($current) {
            $orgBranches = $branches->where('console_organization_id', $current->console_organization_id);
            $currentBranchId = $request->cookie('current_branch_id');
            $currentBranch = $currentBranchId
                ? $orgBranches->firstWhere('console_branch_id', $currentBranchId)
                : $orgBranches->firstWhere('is_headquarters', true) ?? $orgBranches->first();
        }

        return [
            'current' => $current,
            'slug' => $current?->slug,
            'list' => $organizations->toArray(),
            'currentBranch' => $currentBranch,
            'branches' => $branches->toArray(),
        ];
    }

    /**
     * Resolve the list of organizations accessible to the given user.
     *
     * Console mode: uses OrganizationAccessService (calls Console API + local cache fallback)
     * so org list always reflects the user's actual access granted by the Console.
     *
     * Standalone mode: uses role_user_pivot (local IAM assignments).
     *
     * @return Collection<int, Organization>
     */
    protected function resolveUserOrganizations(?object $user): Collection
    {
        if (! $user) {
            return Organization::where('is_active', true)
                ->select(['id', 'console_organization_id', 'name', 'slug'])
                ->orderBy('name')
                ->get();
        }

        if (config('omnify-auth.mode') === 'console') {
            // Console mode: OrganizationAccessService calls Console API (with local cache fallback)
            // to get the list of orgs the user actually has access to, then returns local records.
            $orgAccessList = app(OrganizationAccessService::class)->getOrganizations($user);
            $consoleOrgIds = array_column($orgAccessList, 'organization_id');

            if (empty($consoleOrgIds)) {
                return collect();
            }

            return Organization::whereIn('console_organization_id', $consoleOrgIds)
                ->where('is_active', true)
                ->select(['id', 'console_organization_id', 'name', 'slug'])
                ->orderBy('name')
                ->get();
        }

        // Standalone mode: scope org list to user's local role assignments
        if (method_exists($user, 'organizations')) {
            return $user->organizations()
                ->where('organizations.is_active', true)
                ->select(['organizations.id', 'organizations.console_organization_id', 'organizations.name', 'organizations.slug'])
                ->orderBy('organizations.name')
                ->get();
        }

        return Organization::where('is_active', true)
            ->select(['id', 'console_organization_id', 'name', 'slug'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Build org settings sections with tabs for layout navigation.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildOrgSettingsSections(): array
    {
        $accessPrefix = config('omnify-auth.routes.access_prefix', 'settings/iam');

        $sections = [
            [
                'key' => 'iam',
                'path_prefix' => $accessPrefix,
                'tabs' => [
                    ['suffix' => '', 'label_key' => 'orgSettings.tabs.overview', 'label_default' => 'Overview'],
                    ['suffix' => '/users', 'label_key' => 'orgSettings.tabs.users', 'label_default' => 'Users'],
                    ['suffix' => '/roles', 'label_key' => 'orgSettings.tabs.roles', 'label_default' => 'Roles'],
                    ['suffix' => '/permissions', 'label_key' => 'orgSettings.tabs.permissions', 'label_default' => 'Permissions'],
                ],
            ],
        ];

        $extraSections = config('omnify-auth.org_settings.extra_sections', []);
        foreach ($extraSections as $extra) {
            if (! empty($extra['tabs'])) {
                $sections[] = [
                    'key' => $extra['key'],
                    'path_prefix' => $extra['path_suffix'] ?? '',
                    'tabs' => $extra['tabs'],
                ];
            }
        }

        return $sections;
    }
}
