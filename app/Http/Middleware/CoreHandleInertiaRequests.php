<?php

declare(strict_types=1);

namespace Omnify\Core\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Omnify\Core\Models\Branch;
use Omnify\Core\Models\Organization;

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
        $organizations = Organization::where('is_active', true)
            ->select(['id', 'console_organization_id', 'name', 'slug'])
            ->orderBy('name')
            ->get();

        // Resolve current org: route parameter (URL) > cookie > null
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
