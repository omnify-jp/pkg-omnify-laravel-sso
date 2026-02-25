<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Omnify\SsoClient\Models\Location;
use Omnify\SsoClient\Services\ConsoleApiService;
use Omnify\SsoClient\Services\ConsoleTokenService;

/**
 * Location controller - Proxy locations from Console and cache locally.
 */
class SsoLocationController extends Controller
{
    public function __construct(
        private readonly ConsoleApiService $consoleApi,
        private readonly ConsoleTokenService $tokenService
    ) {}

    /**
     * Get locations for the current organization/branch, synced from Console.
     *
     * Query params:
     *   - organization_id (required): Organization slug or console_organization_id
     *   - branch_id (optional): console_branch_id to filter by branch
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'error' => 'UNAUTHENTICATED',
                'message' => 'User not authenticated',
            ], 401);
        }

        $organizationId = $request->header('X-Organization-Id')
            ?? $request->query('organization_id')
            ?? null;

        if (! $organizationId) {
            return response()->json([
                'error' => 'NO_ORGANIZATION',
                'message' => 'No organization selected. Send X-Organization-Id header or organization_id param.',
            ], 400);
        }

        $branchId = $request->header('X-Branch-Id')
            ?? $request->query('branch_id')
            ?? null;

        $accessToken = $this->tokenService->getAccessToken($user);

        if (! $accessToken) {
            return $this->getLocationsFromCache($organizationId, $branchId);
        }

        try {
            $locations = $this->consoleApi->getLocations($accessToken, $organizationId, $branchId);

            // Auto-cache locations to database
            $this->cacheLocations($locations, $organizationId);

            return response()->json([
                'locations' => $locations,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Failed to fetch locations from console, using cache', [
                'error' => $e->getMessage(),
                'organization_id' => $organizationId,
                'branch_id' => $branchId,
            ]);

            return $this->getLocationsFromCache($organizationId, $branchId);
        }
    }

    /**
     * Get locations from local cache (fallback).
     */
    private function getLocationsFromCache(string $organizationId, ?string $branchId): JsonResponse
    {
        $query = Location::where('console_organization_id', $organizationId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($branchId) {
            $query->where('console_branch_id', $branchId);
        }

        $locations = $query->get()->map(fn ($loc) => $this->formatLocation($loc))->values();

        return response()->json([
            'locations' => $locations,
        ]);
    }

    /**
     * Auto-cache locations from Console response.
     *
     * @param  array<array{id: string, code: string, name: string, type: string, branch_id: string, organization_id: string}>  $locations
     */
    private function cacheLocations(array $locations, string $consoleOrganizationId): void
    {
        foreach ($locations as $loc) {
            $consoleLocationId = (string) ($loc['id'] ?? '');
            $consoleBranchId = (string) ($loc['branch_id'] ?? '');

            if (! $consoleLocationId || ! $consoleBranchId) {
                continue;
            }

            Location::withTrashed()->updateOrCreate(
                ['console_location_id' => $consoleLocationId],
                [
                    'console_branch_id' => $consoleBranchId,
                    'console_organization_id' => $consoleOrganizationId,
                    'code' => strtoupper($loc['code'] ?? 'UNKNOWN'),
                    'name' => $loc['name'] ?? 'Unknown',
                    'type' => $loc['type'] ?? 'office',
                    'is_active' => $loc['is_active'] ?? true,
                    'address' => $loc['address'] ?? null,
                    'city' => $loc['city'] ?? null,
                    'state_province' => $loc['state_province'] ?? null,
                    'postal_code' => $loc['postal_code'] ?? null,
                    'country_code' => $loc['country_code'] ?? null,
                    'latitude' => $loc['latitude'] ?? null,
                    'longitude' => $loc['longitude'] ?? null,
                    'phone' => $loc['phone'] ?? null,
                    'email' => $loc['email'] ?? null,
                    'timezone' => $loc['timezone'] ?? null,
                    'capacity' => $loc['capacity'] ?? null,
                    'sort_order' => $loc['sort_order'] ?? 0,
                    'description' => $loc['description'] ?? null,
                    'settings' => $loc['settings'] ?? null,
                    'metadata' => $loc['metadata'] ?? null,
                    'deleted_at' => null,
                ]
            );
        }
    }

    /**
     * Format a cached Location model for API response.
     *
     * @return array<string, mixed>
     */
    private function formatLocation(Location $location): array
    {
        return [
            'id' => $location->console_location_id,
            'code' => $location->code,
            'name' => $location->name,
            'type' => $location->type,
            'is_active' => $location->is_active,
            'branch_id' => $location->console_branch_id,
            'organization_id' => $location->console_organization_id,
            'address' => $location->address,
            'city' => $location->city,
            'state_province' => $location->state_province,
            'postal_code' => $location->postal_code,
            'country_code' => $location->country_code,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'phone' => $location->phone,
            'email' => $location->email,
            'timezone' => $location->timezone,
            'capacity' => $location->capacity,
            'sort_order' => $location->sort_order,
            'description' => $location->description,
            'settings' => $location->settings,
            'metadata' => $location->metadata,
        ];
    }
}
