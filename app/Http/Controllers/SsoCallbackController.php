<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Omnify\SsoClient\Services\ConsoleApiService;
use Omnify\SsoClient\Services\ConsoleTokenService;
use Omnify\SsoClient\Services\JwtVerifier;
use Omnify\SsoClient\Services\OrganizationAccessService;
use Omnify\SsoClient\Support\RedirectUrlValidator;
use Omnify\SsoClient\Support\SsoLogger;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'SSO Auth', description: 'SSO authentication endpoints')]
class SsoCallbackController extends Controller
{
    public function __construct(
        private readonly ConsoleApiService $consoleApi,
        private readonly JwtVerifier $jwtVerifier,
        private readonly ConsoleTokenService $tokenService,
        private readonly OrganizationAccessService $organizationAccessService,
        private readonly SsoLogger $logger
    ) {}

    /**
     * Handle SSO callback.
     * Exchange code for tokens, create/update user, return auth response.
     */
    #[OA\Post(
        path: '/api/sso/callback',
        summary: 'SSO callback',
        description: 'Exchange authorization code for tokens and authenticate user',
        tags: ['SSO Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code'],
                properties: [
                    new OA\Property(property: 'code', type: 'string', description: 'Authorization code from Console SSO'),
                    new OA\Property(property: 'device_name', type: 'string', maxLength: 255, nullable: true, description: 'Device name for mobile apps (returns API token)'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Authentication successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'user', ref: '#/components/schemas/SsoUser'),
                        new OA\Property(property: 'organizations', type: 'array', items: new OA\Items(ref: '#/components/schemas/Organization')),
                        new OA\Property(property: 'token', type: 'string', nullable: true, description: 'API token (only for mobile apps)'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Invalid code or token'),
        ]
    )]
    public function callback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'], // For mobile apps
        ]);

        // Exchange code for tokens
        try {
            $tokens = $this->consoleApi->exchangeCode($validated['code']);
        } catch (\Throwable $e) {
            $this->logger->codeExchange(false, $e->getMessage());

            return response()->json([
                'error' => 'INVALID_CODE',
                'message' => 'Failed to exchange SSO code',
            ], 401);
        }

        if (! $tokens) {
            $this->logger->codeExchange(false, 'Invalid or expired code');

            return response()->json([
                'error' => 'INVALID_CODE',
                'message' => 'Failed to exchange SSO code',
            ], 401);
        }

        $this->logger->codeExchange(true);

        // Verify JWT and get user info
        $claims = $this->jwtVerifier->verify($tokens['access_token']);

        if (! $claims) {
            $this->logger->jwtVerification(false, 'Invalid signature or expired token');

            return response()->json([
                'error' => 'INVALID_TOKEN',
                'message' => 'Failed to verify access token',
            ], 401);
        }

        $this->logger->jwtVerification(true);

        // Find or create user
        $userModel = config('omnify-auth.user_model');
        $user = $userModel::where('console_user_id', $claims['sub'])->first();

        if (! $user) {
            // Create new user (SSO user - no password, authentication via Console tokens)
            $user = new $userModel;
            $user->console_user_id = $claims['sub'];
            $user->email = $claims['email'];
            $user->name = $claims['name'];
        } else {
            // Update existing user
            $user->email = $claims['email'];
            $user->name = $claims['name'];
        }

        // Store Console tokens
        $this->tokenService->storeTokens($user, $tokens);

        // Get organizations
        $organizations = $this->organizationAccessService->getOrganizations($user);

        // Save primary organization to user (first org or existing)
        if (! $user->console_organization_id && ! empty($organizations)) {
            $user->console_organization_id = $organizations[0]['organization_id'] ?? null;
            $user->save();
        }

        // Create authentication response
        $response = [
            'user' => [
                'id' => $user->id,
                'console_user_id' => $user->console_user_id,
                'console_organization_id' => $user->console_organization_id,
                'email' => $user->email,
                'name' => $user->name,
            ],
            'organizations' => $organizations,
        ];

        // Mobile app: Create API token
        if (! empty($validated['device_name'])) {
            $token = $user->createToken($validated['device_name']);
            $response['token'] = $token->plainTextToken;
            $this->logger->authAttempt($user->email, true);
        } else {
            // Web SPA: Create session (cookie-based auth)
            Auth::login($user);
            $this->logger->authAttempt($user->email, true);
        }

        return response()->json($response);
    }

    /**
     * Logout user.
     */
    #[OA\Post(
        path: '/api/sso/logout',
        summary: 'Logout',
        description: 'Logout current user and revoke tokens',
        tags: ['SSO Auth'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Logged out successfully',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string')])
            ),
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            $userId = $user->id;

            // Revoke Console tokens
            $this->tokenService->revokeTokens($user);

            // Delete current token (for API token auth)
            if ($request->bearerToken()) {
                $user->currentAccessToken()?->delete();
            }

            // Logout from session
            Auth::guard('web')->logout();

            $this->logger->logout($userId);
        }

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get current authenticated user.
     */
    #[OA\Get(
        path: '/api/sso/user',
        summary: 'Get current user',
        description: 'Get authenticated user info with organizations',
        tags: ['SSO Auth'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User info',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'user', ref: '#/components/schemas/SsoUser'),
                        new OA\Property(property: 'organizations', type: 'array', items: new OA\Items(ref: '#/components/schemas/Organization')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'error' => 'UNAUTHENTICATED',
                'message' => 'Not authenticated',
            ], 401);
        }

        // Get organizations
        $organizations = $this->organizationAccessService->getOrganizations($user);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'console_user_id' => $user->console_user_id,
                'console_organization_id' => $user->console_organization_id,
                'email' => $user->email,
                'name' => $user->name,
            ],
            'organizations' => $organizations,
        ]);
    }

    /**
     * Get global logout URL for Console.
     */
    #[OA\Get(
        path: '/api/sso/global-logout-url',
        summary: 'Get global logout URL',
        description: 'Get Console SSO global logout URL for single sign-out',
        tags: ['SSO Auth'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'redirect_uri', in: 'query', schema: new OA\Schema(type: 'string'), description: 'Redirect URL after logout'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Logout URL',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'logout_url', type: 'string', format: 'uri')])
            ),
        ]
    )]
    public function globalLogoutUrl(Request $request): JsonResponse
    {
        $requestedUri = $request->query('redirect_uri');

        // Validate redirect URL to prevent Open Redirect attacks
        $validator = new RedirectUrlValidator;
        $redirectUri = $validator->validate(
            $requestedUri,
            url('/')
        );

        // Log if redirect was blocked
        if ($requestedUri && $redirectUri !== $requestedUri) {
            $this->logger->securityEvent('blocked_redirect', [
                'requested_uri' => $requestedUri,
                'used_uri' => $redirectUri,
            ]);
        }

        $logoutUrl = $this->consoleApi->getConsoleUrl().'/sso/logout?'.http_build_query([
            'redirect_uri' => $redirectUri,
        ]);

        return response()->json([
            'logout_url' => $logoutUrl,
        ]);
    }
}
