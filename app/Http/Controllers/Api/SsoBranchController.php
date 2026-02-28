<?php

declare(strict_types=1);

namespace Omnify\Core\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Omnify\Core\Models\Branch;
use Omnify\Core\Models\Organization;
use Omnify\Core\Services\ConsoleApiService;
use Omnify\Core\Services\ConsoleTokenService;
use OpenApi\Attributes as OA;

/**
 * Branch controller - Proxy branches from Console.
 */
#[OA\Tag(name: 'SSO Branches', description: 'Branch access for authenticated users')]
class SsoBranchController extends Controller
{
    public function __construct(
        private readonly ConsoleApiService $consoleApi,
        private readonly ConsoleTokenService $tokenService
    ) {}

    /**
     * Get branches for current user in the selected organization.
     */
    #[OA\Get(
        path: '/api/sso/branches',
        summary: 'Get user branches in organization',
        description: 'Get all branches the authenticated user can access in the current organization.',
        tags: ['SSO Branches'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'organization_slug',
                in: 'query',
                required: false,
                description: 'Organization slug (defaults to current org)',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Branches list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'all_branches_access', type: 'boolean', description: 'User has access to all branches'),
                        new OA\Property(
                            property: 'branches',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer'),
                                    new OA\Property(property: 'slug', type: 'string'),
                                    new OA\Property(property: 'name', type: 'string'),
                                    new OA\Property(property: 'is_headquarters', type: 'boolean'),
                                    new OA\Property(property: 'is_primary', type: 'boolean'),
                                    new OA\Property(property: 'is_assigned', type: 'boolean'),
                                    new OA\Property(property: 'access_type', type: 'string', enum: ['explicit', 'implicit']),
                                    new OA\Property(property: 'timezone', type: 'string', nullable: true),
                                    new OA\Property(property: 'currency', type: 'string', nullable: true),
                                    new OA\Property(property: 'locale', type: 'string', nullable: true),
                                ]
                            )
                        ),
                        new OA\Property(property: 'primary_branch_id', type: 'integer', nullable: true),
                        new OA\Property(
                            property: 'organization',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'slug', type: 'string'),
                                new OA\Property(property: 'name', type: 'string'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'No organization selected'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 500, description: 'Failed to fetch branches'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'error' => 'UNAUTHENTICATED',
                'message' => 'User not authenticated',
            ], 401);
        }

        // Get organization from X-Organization-Id header (preferred) or query/user default
        $organizationId = $request->header('X-Organization-Id')
            ?? $request->query('organization_id')
            ?? $user->sso_current_org_id
            ?? null;

        // If organizationId is a slug/code, resolve to actual ID
        if ($organizationId) {
            $org = Organization::where('id', $organizationId)
                ->orWhere('slug', $organizationId)
                ->orWhere('name', $organizationId)
                ->first();
            $organizationId = $org?->id;
        }

        if (! $organizationId) {
            return response()->json([
                'error' => 'NO_ORGANIZATION',
                'message' => 'No organization selected. Send X-Organization-Id header.',
            ], 400);
        }

        // Get access token for console API
        $accessToken = $this->tokenService->getAccessToken($user);

        // If no token, try to fetch from local cache (for local development)
        if (! $accessToken) {
            return $this->getBranchesFromCache($organizationId);
        }

        // Fetch branches from console with fallback to cache
        try {
            $result = $this->consoleApi->getUserBranches($accessToken, $organizationId);

            if ($result === null) {
                // Fallback to cache if console fails
                return $this->getBranchesFromCache($organizationId);
            }

            // Auto-cache organization and branches
            $this->cacheOrganizationAndBranches($result);

            return response()->json($result);
        } catch (\Throwable $e) {
            // Log error and fallback to cache
            \Log::warning('Failed to fetch branches from console, using cache', [
                'error' => $e->getMessage(),
                'organization_id' => $organizationId,
            ]);

            return $this->getBranchesFromCache($organizationId);
        }
    }

    /**
     * Get branches from local cache (fallback for local development).
     */
    private function getBranchesFromCache(string $organizationId): JsonResponse
    {
        $org = Organization::find($organizationId);

        if (! $org) {
            return response()->json([
                'error' => 'ORGANIZATION_NOT_FOUND',
                'message' => 'Organization not found in cache',
            ], 404);
        }

        $branches = Branch::where('console_organization_id', $organizationId)
            ->where('is_active', true)
            ->get();

        return response()->json([
            'all_branches_access' => true,
            'branches' => $branches->map(fn ($b) => [
                'id' => $b->id,
                'slug' => $b->slug,
                'name' => $b->name,
                'is_headquarters' => (bool) $b->is_headquarters,
                'is_primary' => (bool) $b->is_headquarters,
                'is_assigned' => true,
                'access_type' => 'implicit',
                'timezone' => null,
                'currency' => null,
                'locale' => null,
            ])->values(),
            'primary_branch_id' => $branches->firstWhere('is_headquarters', true)?->id,
            'organization' => [
                'id' => $org->id,
                'slug' => $org->slug ?: $org->name,
                'name' => $org->name,
            ],
        ]);
    }

    /**
     * Auto-cache organization and branches from Console response.
     *
     * @param  array{organization?: array{id: int|string, slug: string, name: string}, branches?: array<array{id: int|string, code: string, name: string, is_headquarters: bool}>}  $result
     */
    private function cacheOrganizationAndBranches(array $result): void
    {
        // Cache organization
        if (isset($result['organization']) && is_array($result['organization'])) {
            $org = $result['organization'];
            $consoleOrgId = (string) ($org['id'] ?? '');

            if ($consoleOrgId) {
                Organization::withTrashed()->updateOrCreate(
                    ['console_organization_id' => $consoleOrgId],
                    [
                        'name' => $org['name'] ?? 'Unknown',
                        'slug' => $org['slug'] ?? $consoleOrgId,
                        'is_active' => true,
                        'deleted_at' => null,
                    ]
                );
            }
        }

        // Cache branches
        if (isset($result['branches']) && is_array($result['branches'])) {
            $consoleOrgId = (string) ($result['organization']['id'] ?? '');

            foreach ($result['branches'] as $branch) {
                $consoleBranchId = (string) ($branch['id'] ?? '');

                if ($consoleBranchId && $consoleOrgId) {
                    Branch::withTrashed()->updateOrCreate(
                        ['console_branch_id' => $consoleBranchId],
                        [
                            'console_organization_id' => $consoleOrgId,
                            'slug' => $branch['slug'] ?? 'DEFAULT',
                            'name' => $branch['name'] ?? 'Unknown',
                            'is_headquarters' => $branch['is_headquarters'] ?? false,
                            'is_active' => true,
                            'deleted_at' => null,
                        ]
                    );
                }
            }
        }
    }
}
