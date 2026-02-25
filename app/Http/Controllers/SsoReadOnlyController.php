<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Omnify\SsoClient\Models\Permission;
use Omnify\SsoClient\Models\Role;
use OpenApi\Attributes as OA;

/**
 * Read-only access to roles and permissions for authenticated users.
 * These endpoints are available without admin/org requirements.
 */
#[OA\Tag(name: 'SSO Read-Only', description: 'Read-only access to roles and permissions for dashboard display')]
class SsoReadOnlyController extends Controller
{
    /**
     * List all roles (read-only).
     */
    #[OA\Get(
        path: '/api/sso/roles',
        summary: 'List all roles (read-only)',
        description: 'Get all roles for display purposes. Available to any authenticated user.',
        tags: ['SSO Read-Only'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Roles list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Role')
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function roles(): JsonResponse
    {
        $roles = Role::withCount('permissions')
            ->orderBy('level', 'desc')
            ->get();

        return response()->json([
            'data' => $roles,
        ]);
    }

    /**
     * Get a specific role (read-only).
     */
    #[OA\Get(
        path: '/api/sso/roles/{id}',
        summary: 'Get role details (read-only)',
        description: 'Get a specific role with its permissions. Available to any authenticated user.',
        tags: ['SSO Read-Only'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Role ID',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Role details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Role'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Role not found'),
        ]
    )]
    public function role(string $id): JsonResponse
    {
        $role = Role::with('permissions')->findOrFail($id);

        return response()->json([
            'data' => $role,
        ]);
    }

    /**
     * List all permissions (read-only).
     */
    #[OA\Get(
        path: '/api/sso/permissions',
        summary: 'List all permissions (read-only)',
        description: 'Get all permissions for display purposes. Available to any authenticated user.',
        tags: ['SSO Read-Only'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'group',
                in: 'query',
                required: false,
                description: 'Filter by permission group',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Search by slug or name',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'grouped',
                in: 'query',
                required: false,
                description: 'Return grouped by group',
                schema: new OA\Schema(type: 'boolean')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Permissions list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Permission')
                        ),
                        new OA\Property(
                            property: 'groups',
                            type: 'array',
                            items: new OA\Items(type: 'string'),
                            description: 'List of unique permission groups'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function permissions(Request $request): JsonResponse
    {
        $query = Permission::withCount('roles');

        // Filter by group
        if ($request->has('group')) {
            $query->where('group', $request->query('group'));
        }

        // Search
        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('slug', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        // Check if grouped response is requested
        if ($request->boolean('grouped')) {
            $permissions = $query->orderBy('group')->orderBy('slug')->get();
            $grouped = $permissions->groupBy('group')->map(fn ($items) => $items->values());

            return response()->json($grouped);
        }

        $permissions = $query->orderBy('group')->orderBy('slug')->get();

        // Get unique groups
        $groups = Permission::distinct()->pluck('group')->filter()->values();

        return response()->json([
            'data' => $permissions,
            'groups' => $groups,
        ]);
    }

    /**
     * Get permission matrix (read-only).
     */
    #[OA\Get(
        path: '/api/sso/permission-matrix',
        summary: 'Get permission matrix (read-only)',
        description: 'Get a matrix of roles and their assigned permissions. Available to any authenticated user.',
        tags: ['SSO Read-Only'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Permission matrix',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'roles',
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
                        new OA\Property(
                            property: 'permissions',
                            type: 'object',
                            description: 'Permissions grouped by group name',
                            additionalProperties: new OA\AdditionalProperties(
                                type: 'array',
                                items: new OA\Items(
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer'),
                                        new OA\Property(property: 'slug', type: 'string'),
                                        new OA\Property(property: 'name', type: 'string'),
                                    ],
                                    type: 'object'
                                )
                            )
                        ),
                        new OA\Property(
                            property: 'matrix',
                            type: 'object',
                            description: 'Role slug to permission slugs mapping',
                            additionalProperties: new OA\AdditionalProperties(
                                type: 'array',
                                items: new OA\Items(type: 'string')
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function permissionMatrix(): JsonResponse
    {
        $roles = Role::orderBy('level', 'desc')->get(['id', 'slug', 'name']);

        $permissions = Permission::orderBy('group')
            ->orderBy('slug')
            ->get()
            ->groupBy('group')
            ->map(fn ($items) => $items->map(fn ($p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
            ])->values());

        // Build matrix
        $matrix = [];
        foreach ($roles as $role) {
            $rolePermissions = $role->permissions()->pluck('slug')->toArray();
            $matrix[$role->slug] = $rolePermissions;
        }

        return response()->json([
            'roles' => $roles,
            'permissions' => $permissions,
            'matrix' => $matrix,
        ]);
    }
}
