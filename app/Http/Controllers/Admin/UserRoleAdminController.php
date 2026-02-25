<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Omnify\SsoClient\Models\User;
use Omnify\SsoClient\Services\UserRoleService;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'SSO User Roles', description: 'User role assignment endpoints')]
class UserRoleAdminController extends Controller
{
    public function __construct(
        private UserRoleService $userRoleService
    ) {}

    /**
     * Get user's role assignments.
     */
    #[OA\Get(
        path: '/api/admin/sso/users/{userId}/roles',
        summary: 'Get user role assignments',
        description: 'Get all role assignments for a user with scope information.',
        tags: ['SSO User Roles'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'User role assignments'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function index(Request $request, string $userId): JsonResponse
    {
        $user = User::find($userId);

        if (! $user) {
            return response()->json(['error' => 'USER_NOT_FOUND', 'message' => 'User not found'], 404);
        }

        $organizationId = $request->header('X-Organization-Id');
        $roles = $this->userRoleService->getUserRoles($user, $organizationId);

        return response()->json(['data' => $roles]);
    }

    /**
     * Assign a role to a user.
     */
    #[OA\Post(
        path: '/api/admin/sso/users/{userId}/roles',
        summary: 'Assign role to user',
        description: 'Assign a role to a user with optional org/branch scope.',
        tags: ['SSO User Roles'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 201, description: 'Role assigned'),
            new OA\Response(response: 404, description: 'User or role not found'),
            new OA\Response(response: 422, description: 'Validation error or duplicate assignment'),
        ]
    )]
    public function store(Request $request, string $userId): JsonResponse
    {
        $user = User::find($userId);

        if (! $user) {
            return response()->json(['error' => 'USER_NOT_FOUND', 'message' => 'User not found'], 404);
        }

        $validated = $request->validate([
            'role_id' => ['required', 'string', 'exists:roles,id'],
            'console_organization_id' => ['nullable', 'string'],
            'console_branch_id' => ['nullable', 'string'],
        ]);

        $result = $this->userRoleService->assignRole($user, $validated);

        if (! $result['success']) {
            return response()->json([
                'error' => $result['error'],
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'message' => $result['message'],
        ], 201);
    }

    /**
     * Sync user's roles.
     */
    #[OA\Put(
        path: '/api/admin/sso/users/{userId}/roles/sync',
        summary: 'Sync user roles',
        description: 'Replace all role assignments for a user.',
        tags: ['SSO User Roles'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Roles synced'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function sync(Request $request, string $userId): JsonResponse
    {
        $user = User::find($userId);

        if (! $user) {
            return response()->json(['error' => 'USER_NOT_FOUND', 'message' => 'User not found'], 404);
        }

        $validated = $request->validate([
            'assignments' => ['required', 'array'],
            'assignments.*.role_id' => ['required', 'string', 'exists:roles,id'],
            'assignments.*.console_organization_id' => ['nullable', 'string'],
            'assignments.*.console_branch_id' => ['nullable', 'string'],
        ]);

        $organizationId = $request->header('X-Organization-Id');
        $result = $this->userRoleService->syncRoles($user, $validated['assignments'], $organizationId);

        return response()->json($result);
    }

    /**
     * Remove a role from a user.
     */
    #[OA\Delete(
        path: '/api/admin/sso/users/{userId}/roles/{roleId}',
        summary: 'Remove role from user',
        description: 'Remove a specific role assignment from a user.',
        tags: ['SSO User Roles'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'roleId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'console_organization_id', in: 'query', required: false, schema: new OA\Schema(type: 'string', nullable: true)),
            new OA\Parameter(name: 'console_branch_id', in: 'query', required: false, schema: new OA\Schema(type: 'string', nullable: true)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Role removed'),
            new OA\Response(response: 404, description: 'User or assignment not found'),
        ]
    )]
    public function destroy(Request $request, string $userId, string $roleId): JsonResponse
    {
        $user = User::find($userId);

        if (! $user) {
            return response()->json(['error' => 'USER_NOT_FOUND', 'message' => 'User not found'], 404);
        }

        $organizationId = $request->query('console_organization_id');
        $branchId = $request->query('console_branch_id');

        $result = $this->userRoleService->removeRole($user, $roleId, $organizationId, $branchId);

        if (! $result['success']) {
            return response()->json([
                'error' => $result['error'],
                'message' => $result['message'],
            ], 404);
        }

        return response()->json(['message' => $result['message']]);
    }
}
