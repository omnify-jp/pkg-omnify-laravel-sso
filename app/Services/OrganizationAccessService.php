<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Omnify\SsoClient\Models\Organization;

class OrganizationAccessService
{
    private const CACHE_KEY_PREFIX = 'sso:organization_access';

    public function __construct(
        private readonly ConsoleApiService $consoleApi,
        private readonly ConsoleTokenService $tokenService,
        private readonly int $cacheTtl = 300
    ) {}

    /**
     * Check if user has access to organization.
     *
     * @return array{organization_id: string, organization_slug: string, organization_role: string, service_role: string|null, service_role_level: int}|null
     */
    public function checkAccess(Model $user, string $organizationId): ?array
    {
        $cacheKey = $this->getCacheKey($user->console_user_id, $organizationId);

        return Cache::remember(
            $cacheKey,
            $this->cacheTtl,
            function () use ($user, $organizationId) {
                $accessToken = $this->tokenService->getAccessToken($user);

                if (! $accessToken) {
                    // Fallback to local check for local development
                    return $this->checkAccessLocal($user, $organizationId);
                }

                // Try console API with fallback to local check
                try {
                    $result = $this->consoleApi->getAccess($accessToken, $organizationId);
                    if ($result !== null) {
                        return $result;
                    }
                } catch (\Throwable $e) {
                    // Log and fallback to local check
                    \Log::warning('Console API access check failed, using local fallback', [
                        'error' => $e->getMessage(),
                        'organization_id' => $organizationId,
                    ]);
                }

                // Fallback to local check
                return $this->checkAccessLocal($user, $organizationId);
            }
        );
    }

    /**
     * Fallback access check for local development when no SSO token available.
     *
     * @return array{organization_id: string, organization_slug: string, organization_name: string, organization_role: string, service_role: string|null, service_role_level: int}|null
     */
    private function checkAccessLocal(Model $user, string $organizationId): ?array
    {
        // Check if org exists in cache
        $organization = Organization::where('id', $organizationId)
            ->orWhere('slug', $organizationId)
            ->orWhere('name', $organizationId)
            ->first();

        if (! $organization) {
            return null;
        }

        // For local dev, grant admin access to user's org
        $userOrganizationId = $user->console_organization_id ?? null;

        // Allow access if:
        // 1. User's org matches
        // 2. Or no user org set (super admin mode for dev)
        if ($userOrganizationId && $organization->id !== $userOrganizationId && $organization->console_organization_id !== $userOrganizationId) {
            return null;
        }

        return [
            'organization_id' => $organization->id,
            'organization_slug' => $organization->slug ?: $organization->name,
            'organization_name' => $organization->name,
            'organization_role' => 'admin',
            'service_role' => 'admin',
            'service_role_level' => 100,
        ];
    }

    /**
     * Get all organizations user has access to.
     *
     * @return array<array{organization_id: string, organization_slug: string, organization_name: string, organization_role: string, service_role: string|null}>
     */
    public function getOrganizations(Model $user): array
    {
        $accessToken = $this->tokenService->getAccessToken($user);

        if (! $accessToken) {
            // Fallback to cached organizations when no token available (for local development)
            return $this->getCachedOrganizations($user);
        }

        $organizations = $this->consoleApi->getOrganizations($accessToken);

        // Auto-cache organizations to database
        $this->cacheOrganizations($organizations);

        return $organizations;
    }

    /**
     * Get organizations from local cache (fallback for local development).
     *
     * @return array<array{organization_id: string, organization_slug: string, organization_name: string, organization_role: string, service_role: string|null}>
     */
    private function getCachedOrganizations(Model $user): array
    {
        // If user has a console_organization_id, return that org from cache
        $consoleOrganizationId = $user->console_organization_id ?? null;

        $query = Organization::query()->where('is_active', true);

        // If user has specific org, prioritize it
        if ($consoleOrganizationId) {
            $query->where(function ($q) use ($consoleOrganizationId) {
                $q->where('id', $consoleOrganizationId)
                    ->orWhere('console_organization_id', $consoleOrganizationId);
            });
        }

        $organizations = $query->get();

        return $organizations->map(fn ($organization) => [
            'organization_id' => $organization->id,
            'organization_slug' => $organization->slug ?: $organization->name,
            'organization_name' => $organization->name,
            'organization_role' => 'admin', // Default for local dev
            'service_role' => 'admin',
        ])->all();
    }

    /**
     * Auto-cache organizations from Console response.
     *
     * @param  array<array{organization_id: string, organization_slug: string, organization_name: string}>  $organizations
     */
    private function cacheOrganizations(array $organizations): void
    {
        foreach ($organizations as $organization) {
            $consoleOrganizationId = $organization['organization_id'] ?? null;
            $slug = $organization['organization_slug'] ?? $consoleOrganizationId;

            if (! $consoleOrganizationId) {
                continue;
            }

            try {
                // First, try to find existing record by console_organization_id OR slug
                // This handles cases where the same org exists with different console_organization_id
                $existingOrg = Organization::withTrashed()
                    ->where('console_organization_id', $consoleOrganizationId)
                    ->orWhere('slug', $slug)
                    ->first();

                if ($existingOrg) {
                    // Update existing record (sync with Console)
                    $existingOrg->update([
                        'console_organization_id' => $consoleOrganizationId,
                        'name' => $organization['organization_name'] ?? 'Unknown',
                        'slug' => $slug,
                        'is_active' => true,
                        'deleted_at' => null,
                    ]);
                } else {
                    // Create new record
                    Organization::create([
                        'console_organization_id' => $consoleOrganizationId,
                        'name' => $organization['organization_name'] ?? 'Unknown',
                        'slug' => $slug,
                        'is_active' => true,
                    ]);
                }
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                \Log::error('SSO: Failed to cache organization - duplicate entry', [
                    'console_organization_id' => $consoleOrganizationId,
                    'slug' => $slug,
                    'organization_name' => $organization['organization_name'] ?? 'Unknown',
                    'error' => $e->getMessage(),
                    'hint' => 'Local seeder data may be out of sync with Console. Run: php artisan db:seed --class=OrganizationSeeder to resync.',
                ]);

                throw new \RuntimeException(
                    "SSO Error: Organization '{$slug}' already exists with different console_organization_id. ".
                    'Local database may be out of sync with Console. '.
                    'Please run: php artisan migrate:fresh --seed or manually update organizations table.',
                    0,
                    $e
                );
            }
        }
    }

    /**
     * Clear access cache for user/org.
     */
    public function clearCache(int|string $consoleUserId, ?string $organizationId = null): void
    {
        if ($organizationId) {
            Cache::forget($this->getCacheKey($consoleUserId, $organizationId));
        }
        // Note: For clearing all orgs for a user, we would need cache tags
        // which requires a cache driver that supports tags (Redis, Memcached)
    }

    /**
     * Get cache key for org access.
     */
    private function getCacheKey(int|string $consoleUserId, string $organizationId): string
    {
        return self::CACHE_KEY_PREFIX.":{$consoleUserId}:{$organizationId}";
    }
}
