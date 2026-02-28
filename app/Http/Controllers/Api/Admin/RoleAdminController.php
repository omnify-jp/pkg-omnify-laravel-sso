<?php

declare(strict_types=1);

namespace Omnify\Core\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Omnify\Core\Services\RoleService;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'SSO Roles', description: 'Role management endpoints')]
class RoleAdminController extends Controller
{
    public function __construct(
        private RoleService $roleService
    ) {}

    /**
     * List all roles for the current organization.
     */
    #[OA\Get(
        path: '/api/admin/sso/roles',
        summary: 'List all roles',
        tags: ['SSO Roles'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'filter[scope]', in: 'query', description: 'Filter by scope: global, org, all', schema: new OA\Schema(type: 'string', enum: ['global', 'org', 'all'])),
            new OA\Parameter(name: 'filter[organization_id]', in: 'query', description: 'Filter by specific organization ID', schema: new OA\Schema(type: 'string')),
        ],
        responses: [new OA\Response(response: 200, description: 'Roles list')]
    )]
    public function index(Request $request): JsonResponse
    {
        $organizationId = $request->header('X-Organization-Id');
        $scope = $request->input('filter.scope', 'all');
        $filterOrganizationId = $request->input('filter.organization_id');

        $roles = $this->roleService->list($organizationId, [
            'scope' => $scope,
            'filter_organization_id' => $filterOrganizationId,
        ]);

        return response()->json(['data' => $roles]);
    }

    /**
     * Create a new role.
     */
    #[OA\Post(
        path: '/api/admin/sso/roles',
        summary: 'Create a new role',
        tags: ['SSO Roles'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Role created'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:100'],
            'level' => ['required', 'integer', 'min:0', 'max:100'],
            'description' => ['nullable', 'string'],
            'scope' => ['nullable', 'string', 'in:global,org'],
            'console_organization_id' => ['nullable', 'string', 'max:36'],
        ]);

        $result = $this->roleService->create($validated, $request->header('X-Organization-Id'));

        if (! $result['success']) {
            return response()->json([
                'error' => $result['error'],
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'data' => $result['role'],
            'message' => $result['message'],
        ], 201);
    }

    /**
     * Get a specific role.
     */
    #[OA\Get(
        path: '/api/admin/sso/roles/{id}',
        summary: 'Get a specific role',
        tags: ['SSO Roles'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Role details'),
            new OA\Response(response: 404, description: 'Role not found'),
        ]
    )]
    public function show(Request $request, string $id): JsonResponse
    {
        $organizationId = $request->header('X-Organization-Id');

        $role = $this->roleService->find($id, $organizationId);

        if (! $role) {
            return response()->json(['error' => 'NOT_FOUND', 'message' => 'Role not found'], 404);
        }

        return response()->json(['data' => $role]);
    }

    /**
     * Update a role.
     */
    #[OA\Put(
        path: '/api/admin/sso/roles/{id}',
        summary: 'Update a role',
        tags: ['SSO Roles'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Role updated'),
            new OA\Response(response: 404, description: 'Role not found'),
        ]
    )]
    public function update(Request $request, string $id): JsonResponse
    {
        $organizationId = $request->header('X-Organization-Id');

        $role = $this->roleService->find($id, $organizationId);

        if (! $role) {
            return response()->json(['error' => 'NOT_FOUND', 'message' => 'Role not found'], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'level' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $role = $this->roleService->update($role, $validated);

        return response()->json([
            'data' => $role,
            'message' => 'Role updated successfully',
        ]);
    }

    /**
     * Delete a role.
     */
    #[OA\Delete(
        path: '/api/admin/sso/roles/{id}',
        summary: 'Delete a role',
        tags: ['SSO Roles'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 204, description: 'Role deleted'),
            new OA\Response(response: 422, description: 'Cannot delete system role'),
        ]
    )]
    public function destroy(Request $request, string $id): JsonResponse
    {
        $organizationId = $request->header('X-Organization-Id');

        $role = $this->roleService->find($id, $organizationId);

        if (! $role) {
            return response()->json(['error' => 'NOT_FOUND', 'message' => 'Role not found'], 404);
        }

        $result = $this->roleService->delete($role);

        if (! $result['success']) {
            return response()->json([
                'error' => $result['error'],
                'message' => $result['message'],
            ], 422);
        }

        return response()->json(null, 204);
    }

    /**
     * Get role's permissions.
     */
    #[OA\Get(
        path: '/api/admin/sso/roles/{id}/permissions',
        summary: 'Get role permissions',
        tags: ['SSO Roles'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Role permissions')]
    )]
    public function permissions(Request $request, string $id): JsonResponse
    {
        $organizationId = $request->header('X-Organization-Id');

        $role = $this->roleService->find($id, $organizationId);

        if (! $role) {
            return response()->json(['error' => 'NOT_FOUND', 'message' => 'Role not found'], 404);
        }

        return response()->json($this->roleService->getPermissions($role));
    }

    /**
     * Sync role's permissions.
     */
    #[OA\Put(
        path: '/api/admin/sso/roles/{id}/permissions',
        summary: 'Sync role permissions',
        tags: ['SSO Roles'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Permissions synced')]
    )]
    public function syncPermissions(Request $request, string $id): JsonResponse
    {
        $organizationId = $request->header('X-Organization-Id');

        $role = $this->roleService->find($id, $organizationId);

        if (! $role) {
            return response()->json(['error' => 'NOT_FOUND', 'message' => 'Role not found'], 404);
        }

        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['required'],
        ]);

        $result = $this->roleService->syncPermissions($role, $validated['permissions']);

        return response()->json($result);
    }
}
