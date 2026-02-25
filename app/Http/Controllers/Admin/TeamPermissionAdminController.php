<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Omnify\SsoClient\Models\Permission;
use Omnify\SsoClient\Models\TeamPermission;
use Omnify\SsoClient\Services\OrganizationAccessService;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'SSO Team Permissions', description: 'Team permission management endpoints')]
class TeamPermissionAdminController extends Controller
{
    public function __construct(
        private readonly OrganizationAccessService $organizationAccessService
    ) {}

    /**
     * List all teams with their permissions.
     */
    #[OA\Get(
        path: '/api/admin/sso/teams/permissions',
        summary: 'List team permissions',
        description: 'List all teams with their assigned permissions for the current organization',
        tags: ['SSO Team Permissions'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Teams with their assigned permissions',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'teams',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'console_team_id', type: 'integer', example: 12345),
                                    new OA\Property(property: 'name', type: 'string', example: 'Development Team'),
                                    new OA\Property(property: 'path', type: 'string', nullable: true, example: '/engineering/development'),
                                    new OA\Property(
                                        property: 'permissions',
                                        type: 'array',
                                        items: new OA\Items(
                                            properties: [
                                                new OA\Property(property: 'id', type: 'integer'),
                                                new OA\Property(property: 'slug', type: 'string'),
                                            ],
                                            type: 'object'
                                        )
                                    ),
                                ],
                                type: 'object'
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden - requires admin role'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $request->attributes->get('organizationId');
        $organizationId = $request->attributes->get('organizationId');

        // Get teams from Console
        $teams = $this->organizationAccessService->getUserTeams($user, $organizationId);

        // Get team permissions from DB
        $teamIds = collect($teams)->pluck('id')->toArray();

        $teamPermissions = TeamPermission::where('console_organization_id', $organizationId)
            ->whereIn('console_team_id', $teamIds)
            ->whereNull('deleted_at')
            ->with('permission')
            ->get()
            ->groupBy('console_team_id');

        // Merge data
        $result = collect($teams)->map(function ($team) use ($teamPermissions) {
            $permissions = $teamPermissions->get($team['id'], collect())
                ->map(fn ($tp) => [
                    'id' => $tp->permission->id,
                    'slug' => $tp->permission->slug,
                ])
                ->values();

            return [
                'console_team_id' => $team['id'],
                'name' => $team['name'],
                'path' => $team['path'] ?? null,
                'permissions' => $permissions,
            ];
        });

        return response()->json([
            'teams' => $result,
        ]);
    }

    /**
     * Get permissions for a specific team.
     */
    #[OA\Get(
        path: '/api/admin/sso/teams/{teamId}/permissions',
        summary: 'Get team permissions',
        description: 'Get all permissions assigned to a specific team',
        tags: ['SSO Team Permissions'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'teamId',
                in: 'path',
                required: true,
                description: 'Console team ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Team permissions',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'console_team_id', type: 'integer', example: 12345),
                        new OA\Property(
                            property: 'permissions',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer'),
                                    new OA\Property(property: 'slug', type: 'string'),
                                    new OA\Property(property: 'name', type: 'string'),
                                ],
                                type: 'object'
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden - requires admin role'),
        ]
    )]
    public function show(Request $request, int $teamId): JsonResponse
    {
        $organizationId = $request->attributes->get('organizationId');

        $permissions = TeamPermission::where('console_organization_id', $organizationId)
            ->where('console_team_id', $teamId)
            ->whereNull('deleted_at')
            ->with('permission')
            ->get()
            ->map(fn ($tp) => [
                'id' => $tp->permission->id,
                'slug' => $tp->permission->slug,
                'name' => $tp->permission->name,
            ]);

        return response()->json([
            'console_team_id' => $teamId,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Sync permissions for a team.
     */
    #[OA\Put(
        path: '/api/admin/sso/teams/{teamId}/permissions',
        summary: 'Sync team permissions',
        description: 'Replace all permissions for a team with the provided list',
        tags: ['SSO Team Permissions'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'teamId',
                in: 'path',
                required: true,
                description: 'Console team ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['permissions'],
                properties: [
                    new OA\Property(
                        property: 'permissions',
                        type: 'array',
                        description: 'Array of permission IDs or slugs',
                        items: new OA\Items(
                            oneOf: [
                                new OA\Schema(type: 'integer', example: 1),
                                new OA\Schema(type: 'string', example: 'projects.create'),
                            ]
                        ),
                        example: [1, 2, 'projects.create']
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Permissions synced successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Team permissions synced'),
                        new OA\Property(property: 'console_team_id', type: 'integer', example: 12345),
                        new OA\Property(property: 'attached', type: 'integer', description: 'Number of permissions added', example: 2),
                        new OA\Property(property: 'detached', type: 'integer', description: 'Number of permissions removed', example: 1),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden - requires admin role'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function sync(Request $request, int $teamId): JsonResponse
    {
        $organizationId = $request->attributes->get('organizationId');

        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['required'],
        ]);

        // Handle both IDs and slugs
        $permissionIds = collect($validated['permissions'])->map(function ($item) {
            if (is_numeric($item)) {
                return (int) $item;
            }

            $permission = Permission::where('slug', $item)->first();

            return $permission?->id;
        })->filter()->values()->toArray();

        // Get current permissions
        $current = TeamPermission::where('console_organization_id', $organizationId)
            ->where('console_team_id', $teamId)
            ->whereNull('deleted_at')
            ->pluck('permission_id')
            ->toArray();

        // Calculate diff
        $toAttach = array_diff($permissionIds, $current);
        $toDetach = array_diff($current, $permissionIds);

        // Soft delete removed permissions
        if (! empty($toDetach)) {
            TeamPermission::where('console_organization_id', $organizationId)
                ->where('console_team_id', $teamId)
                ->whereIn('permission_id', $toDetach)
                ->update(['deleted_at' => now()]);
        }

        // Add new permissions (or restore soft-deleted)
        foreach ($toAttach as $permissionId) {
            TeamPermission::updateOrCreate(
                [
                    'console_organization_id' => $organizationId,
                    'console_team_id' => $teamId,
                    'permission_id' => $permissionId,
                ],
                ['deleted_at' => null]
            );
        }

        return response()->json([
            'message' => 'Team permissions synced',
            'console_team_id' => $teamId,
            'attached' => count($toAttach),
            'detached' => count($toDetach),
        ]);
    }

    /**
     * Remove all permissions for a team (soft delete).
     */
    #[OA\Delete(
        path: '/api/admin/sso/teams/{teamId}/permissions',
        summary: 'Remove team permissions',
        description: 'Soft delete all permissions for a team. Can be restored later.',
        tags: ['SSO Team Permissions'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'teamId',
                in: 'path',
                required: true,
                description: 'Console team ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Permissions removed successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden - requires admin role'),
        ]
    )]
    public function destroy(Request $request, int $teamId): JsonResponse
    {
        $organizationId = $request->attributes->get('organizationId');

        TeamPermission::where('console_organization_id', $organizationId)
            ->where('console_team_id', $teamId)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);

        return response()->json(null, 204);
    }

    /**
     * List orphaned team permissions.
     */
    #[OA\Get(
        path: '/api/admin/sso/teams/orphaned',
        summary: 'List orphaned permissions',
        description: 'List team permissions that are orphaned (team no longer exists in Console or soft-deleted)',
        tags: ['SSO Team Permissions'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Orphaned team permissions',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'orphaned_teams',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'console_team_id', type: 'integer', example: 12345),
                                    new OA\Property(property: 'permissions_count', type: 'integer', example: 5),
                                    new OA\Property(
                                        property: 'permissions',
                                        type: 'array',
                                        items: new OA\Items(type: 'string'),
                                        example: ['projects.create', 'projects.edit']
                                    ),
                                    new OA\Property(property: 'deleted_at', type: 'string', format: 'date-time', nullable: true),
                                ],
                                type: 'object'
                            )
                        ),
                        new OA\Property(property: 'total_orphaned_permissions', type: 'integer', example: 10),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden - requires admin role'),
        ]
    )]
    public function orphaned(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $request->attributes->get('organizationId');
        $organizationId = $request->attributes->get('organizationId');

        // Get current teams from Console
        $teams = $this->organizationAccessService->getUserTeams($user, $organizationId);
        $activeTeamIds = collect($teams)->pluck('id')->toArray();

        // Find orphaned (team IDs in DB but not in Console, or already soft-deleted)
        $orphaned = TeamPermission::where('console_organization_id', $organizationId)
            ->where(function ($query) use ($activeTeamIds) {
                $query->whereNotIn('console_team_id', $activeTeamIds)
                    ->orWhereNotNull('deleted_at');
            })
            ->with('permission')
            ->get()
            ->groupBy('console_team_id')
            ->map(function ($items, $teamId) {
                $first = $items->first();

                return [
                    'console_team_id' => $teamId,
                    'permissions_count' => $items->count(),
                    'permissions' => $items->pluck('permission.slug')->toArray(),
                    'deleted_at' => $first->deleted_at?->toIso8601String(),
                ];
            })
            ->values();

        // Auto soft-delete newly orphaned teams
        if (! empty($activeTeamIds)) {
            TeamPermission::where('console_organization_id', $organizationId)
                ->whereNotIn('console_team_id', $activeTeamIds)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => now()]);
        }

        return response()->json([
            'orphaned_teams' => $orphaned,
            'total_orphaned_permissions' => $orphaned->sum('permissions_count'),
        ]);
    }

    /**
     * Restore orphaned team permissions.
     */
    #[OA\Post(
        path: '/api/admin/sso/teams/orphaned/{teamId}/restore',
        summary: 'Restore orphaned permissions',
        description: 'Restore soft-deleted permissions for a team',
        tags: ['SSO Team Permissions'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'teamId',
                in: 'path',
                required: true,
                description: 'Console team ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Permissions restored successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Team permissions restored'),
                        new OA\Property(property: 'console_team_id', type: 'integer', example: 12345),
                        new OA\Property(property: 'restored_count', type: 'integer', example: 3),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden - requires admin role'),
        ]
    )]
    public function restore(Request $request, int $teamId): JsonResponse
    {
        $organizationId = $request->attributes->get('organizationId');

        $count = TeamPermission::where('console_organization_id', $organizationId)
            ->where('console_team_id', $teamId)
            ->whereNotNull('deleted_at')
            ->update(['deleted_at' => null]);

        return response()->json([
            'message' => 'Team permissions restored',
            'console_team_id' => $teamId,
            'restored_count' => $count,
        ]);
    }

    /**
     * Hard delete orphaned team permissions.
     */
    #[OA\Delete(
        path: '/api/admin/sso/teams/orphaned',
        summary: 'Cleanup orphaned permissions',
        description: 'Permanently delete orphaned team permissions. This action cannot be undone.',
        tags: ['SSO Team Permissions'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'console_team_id',
                        type: 'integer',
                        nullable: true,
                        description: 'Optional: Only delete permissions for this team',
                        example: 12345
                    ),
                    new OA\Property(
                        property: 'older_than_days',
                        type: 'integer',
                        minimum: 1,
                        nullable: true,
                        description: 'Optional: Only delete permissions soft-deleted more than N days ago',
                        example: 30
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Orphaned permissions permanently deleted',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Orphaned team permissions permanently deleted'),
                        new OA\Property(property: 'deleted_count', type: 'integer', example: 5),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden - requires admin role'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function cleanupOrphaned(Request $request): JsonResponse
    {
        $organizationId = $request->attributes->get('organizationId');

        $validated = $request->validate([
            'console_team_id' => ['nullable', 'integer'],
            'older_than_days' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = TeamPermission::where('console_organization_id', $organizationId)
            ->whereNotNull('deleted_at');

        if (isset($validated['console_team_id'])) {
            $query->where('console_team_id', $validated['console_team_id']);
        }

        if (isset($validated['older_than_days'])) {
            $query->where('deleted_at', '<', now()->subDays($validated['older_than_days']));
        }

        $count = $query->forceDelete();

        return response()->json([
            'message' => 'Orphaned team permissions permanently deleted',
            'deleted_count' => $count,
        ]);
    }
}
