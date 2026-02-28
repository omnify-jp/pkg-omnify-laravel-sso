<?php

namespace Omnify\Core\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Omnify\Core\Http\Requests\Admin\UserAdminUpdateRequest;
use Omnify\Core\Http\Resources\UserResource;
use Omnify\Core\Models\User;
use Omnify\Core\Services\UserService;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Admin - Users', description: 'User management endpoints (Admin only)')]
class UserAdminController extends Controller
{
    public function __construct(
        private UserService $userService
    ) {}

    /**
     * Display a listing of users.
     *
     * Requires X-Organization-Id header. Returns users belonging to the organization.
     */
    #[OA\Get(
        path: '/api/admin/sso/users',
        summary: 'List users',
        description: 'Paginated list with search and sorting. **Admin only.** Requires X-Organization-Id header.',
        tags: ['Admin - Users'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'X-Organization-Id', in: 'header', required: true, description: 'Organization ID (required)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'filter[search]', in: 'query', description: 'Partial match on: name, email', schema: new OA\Schema(type: 'string'), example: '田中'),
            new OA\Parameter(name: 'page', in: 'query', description: 'Page number', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Items per page', schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\Parameter(name: 'sort', in: 'query', description: 'Sort field. Prefix `-` for descending.', schema: new OA\Schema(type: 'string', enum: ['id', '-id', 'name', '-name', 'email', '-email', 'created_at', '-created_at']), example: '-created_at'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated user list'),
            new OA\Response(response: 400, description: 'Missing X-Organization-Id header'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        // Organization ID from middleware (sso.organization validates X-Organization-Id header)
        $organizationId = $request->attributes->get('organizationId');

        $users = $this->userService->list([
            'organization_id' => $organizationId,
            'per_page' => $request->input('per_page', 10),
        ]);

        return UserResource::collection($users);
    }

    /**
     * Display the specified user.
     */
    #[OA\Get(
        path: '/api/admin/sso/users/{id}',
        summary: 'Get user',
        description: 'Get user by ID. **Admin only.**',
        tags: ['Admin - Users'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'User details'),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }

    /**
     * Update the specified user.
     */
    #[OA\Put(
        path: '/api/admin/sso/users/{id}',
        summary: 'Update user',
        description: 'Update user (partial update supported). **Admin only.**',
        tags: ['Admin - Users'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'User updated'),
            new OA\Response(response: 422, description: 'Validation Error'),
        ]
    )]
    public function update(UserAdminUpdateRequest $request, User $user): UserResource
    {
        $user = $this->userService->update($user, $request->validated());

        return new UserResource($user);
    }

    /**
     * Remove the specified user.
     */
    #[OA\Delete(
        path: '/api/admin/sso/users/{id}',
        summary: 'Delete user',
        description: 'Permanently delete user. **Admin only.**',
        tags: ['Admin - Users'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 204, description: 'No Content')]
    )]
    public function destroy(User $user): JsonResponse
    {
        $this->userService->delete($user);

        return response()->json(null, 204);
    }

    /**
     * Search users by email (autocomplete).
     *
     * Requires X-Organization-Id header.
     */
    #[OA\Get(
        path: '/api/admin/sso/users/search',
        summary: 'Search users by email',
        description: 'Search users by email for autocomplete. Requires X-Organization-Id header.',
        tags: ['Admin - Users'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'X-Organization-Id', in: 'header', required: true, description: 'Organization ID (required)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'email', in: 'query', description: 'Email to search (partial match, min 2 chars)', required: true, schema: new OA\Schema(type: 'string', minLength: 2)),
        ],
        responses: [new OA\Response(response: 200, description: 'Matching users list')]
    )]
    public function search(Request $request): AnonymousResourceCollection
    {
        // Organization ID from middleware (sso.organization validates X-Organization-Id header)
        $organizationId = $request->attributes->get('organizationId');

        $email = $request->input('email', '');
        $currentUserId = $request->user()?->id;

        $users = $this->userService->searchByEmail($email, $organizationId, $currentUserId);

        return UserResource::collection($users);
    }

    /**
     * Get user permissions breakdown.
     */
    #[OA\Get(
        path: '/api/admin/sso/users/{user}/permissions',
        summary: 'Get user permissions breakdown',
        description: 'Get comprehensive breakdown of user permissions from roles and teams.',
        tags: ['Admin - Users'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'organization_id', in: 'query', required: false, description: 'Organization ID for context', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'branch_id', in: 'query', required: false, description: 'Branch ID for context', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'User permissions breakdown'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function permissions(User $user): JsonResponse
    {
        $organizationId = request()->query('organization_id');
        $branchId = request()->query('branch_id');

        $breakdown = $this->userService->getPermissionsBreakdown($user, $organizationId, $branchId);

        return response()->json($breakdown);
    }
}
