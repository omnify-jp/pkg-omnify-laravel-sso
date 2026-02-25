<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'SSO Tokens', description: 'API token management endpoints')]
class SsoTokenController extends Controller
{
    /**
     * List all API tokens for current user.
     * For mobile apps to manage their tokens.
     */
    #[OA\Get(
        path: '/api/sso/tokens',
        summary: 'List API tokens',
        description: 'List all API tokens for current user',
        tags: ['SSO Tokens'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Token list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'tokens', type: 'array', items: new OA\Items(ref: '#/components/schemas/ApiToken')),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $tokens = $user->tokens()
            ->orderBy('last_used_at', 'desc')
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'created_at' => $token->created_at->toIso8601String(),
                'is_current' => $token->id === $user->currentAccessToken()?->id,
            ]);

        return response()->json([
            'tokens' => $tokens,
        ]);
    }

    /**
     * Revoke a specific token.
     */
    #[OA\Delete(
        path: '/api/sso/tokens/{tokenId}',
        summary: 'Revoke token',
        description: 'Revoke a specific API token',
        tags: ['SSO Tokens'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'tokenId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Token revoked', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string')])),
            new OA\Response(response: 404, description: 'Token not found'),
        ]
    )]
    public function destroy(Request $request, int $tokenId): JsonResponse
    {
        $user = $request->user();

        $token = $user->tokens()->find($tokenId);

        if (! $token) {
            return response()->json([
                'error' => 'TOKEN_NOT_FOUND',
                'message' => 'Token not found',
            ], 404);
        }

        $token->delete();

        return response()->json([
            'message' => 'Token revoked successfully',
        ]);
    }

    /**
     * Revoke all tokens except current.
     */
    #[OA\Post(
        path: '/api/sso/tokens/revoke-others',
        summary: 'Revoke other tokens',
        description: 'Revoke all tokens except current one',
        tags: ['SSO Tokens'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tokens revoked',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'revoked_count', type: 'integer'),
                    ]
                )
            ),
        ]
    )]
    public function revokeOthers(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentTokenId = $user->currentAccessToken()?->id;

        $count = $user->tokens()
            ->when($currentTokenId, fn ($query) => $query->where('id', '!=', $currentTokenId))
            ->delete();

        return response()->json([
            'message' => 'Tokens revoked successfully',
            'revoked_count' => $count,
        ]);
    }
}
