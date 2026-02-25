<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Omnify\SsoClient\Http\Resources\PermissionResource;
use Omnify\SsoClient\Models\Permission;
use Omnify\SsoClient\Services\PermissionService;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'SSO Permissions', description: 'Permission management endpoints')]
class PermissionAdminController extends Controller
{
    public function __construct(
        private PermissionService $permissionService
    ) {}

    /**
     * List all permissions.
     */
    #[OA\Get(
        path: '/api/admin/sso/permissions',
        summary: 'List all permissions',
        tags: ['SSO Permissions'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'filter[search]', in: 'query', description: 'Search by name or slug', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'filter[group]', in: 'query', description: 'Filter by group', schema: new OA\Schema(type: 'string')),
        ],
        responses: [new OA\Response(response: 200, description: 'Permissions list with groups')]
    )]
    public function index(Request $request): JsonResponse
    {
        $groups = $this->permissionService->getGroups();

        if ($request->boolean('grouped')) {
            return response()->json($this->permissionService->getGrouped());
        }

        $permissions = $this->permissionService->list([
            'search' => $request->input('search'),
            'group' => $request->input('group'),
        ]);

        return response()->json([
            'data' => PermissionResource::collection($permissions),
            'groups' => $groups,
        ]);
    }

    /**
     * Create a new permission.
     */
    #[OA\Post(
        path: '/api/admin/sso/permissions',
        summary: 'Create a new permission',
        tags: ['SSO Permissions'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Permission created'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:100', 'unique:permissions,slug'],
            'name' => ['required', 'string', 'max:100'],
            'group' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $result = $this->permissionService->create($validated);

        return response()->json([
            'data' => new PermissionResource($result['permission']),
            'message' => $result['message'],
        ], 201);
    }

    /**
     * Get a specific permission.
     */
    #[OA\Get(
        path: '/api/admin/sso/permissions/{id}',
        summary: 'Get a specific permission',
        tags: ['SSO Permissions'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Permission details'),
            new OA\Response(response: 404, description: 'Permission not found'),
        ]
    )]
    public function show(Permission $permission): JsonResponse
    {
        $permission->load('roles');

        return response()->json([
            'data' => new PermissionResource($permission),
        ]);
    }

    /**
     * Update a permission.
     */
    #[OA\Put(
        path: '/api/admin/sso/permissions/{id}',
        summary: 'Update a permission',
        tags: ['SSO Permissions'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Permission updated'),
            new OA\Response(response: 404, description: 'Permission not found'),
        ]
    )]
    public function update(Request $request, Permission $permission): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'group' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $permission = $this->permissionService->update($permission, $validated);

        return response()->json([
            'data' => new PermissionResource($permission),
            'message' => 'Permission updated successfully',
        ]);
    }

    /**
     * Delete a permission.
     */
    #[OA\Delete(
        path: '/api/admin/sso/permissions/{id}',
        summary: 'Delete a permission',
        tags: ['SSO Permissions'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 204, description: 'Permission deleted')]
    )]
    public function destroy(Permission $permission): JsonResponse
    {
        $this->permissionService->delete($permission);

        return response()->json(null, 204);
    }

    /**
     * Get permission matrix (roles vs permissions).
     */
    #[OA\Get(
        path: '/api/admin/sso/permission-matrix',
        summary: 'Get permission matrix',
        description: 'Get matrix of roles vs permissions for the current organization.',
        tags: ['SSO Permissions'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'Permission matrix')]
    )]
    public function matrix(Request $request): JsonResponse
    {
        $organizationId = $request->header('X-Organization-Id');

        $matrix = $this->permissionService->getMatrix($organizationId);

        return response()->json($matrix);
    }
}
