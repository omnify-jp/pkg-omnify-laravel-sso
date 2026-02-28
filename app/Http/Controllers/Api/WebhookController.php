<?php

declare(strict_types=1);

namespace Omnify\Core\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Omnify\Core\Services\JwksService;
use Omnify\Core\Services\OrganizationAccessService;

class WebhookController extends Controller
{
    /**
     * Purge permission and JWKS caches for a given user/organization.
     *
     * Called by Console when roles/permissions change so the service immediately
     * reflects the new state without waiting for the TTL to expire.
     */
    public function cachePurge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'organization_id' => 'required|string',
            'user_id' => 'nullable|string',
        ]);

        $orgId = $validated['organization_id'];
        $userId = $validated['user_id'] ?? null;

        if ($userId !== null && $userId !== '') {
            // Clear org access cache for this specific user
            app(OrganizationAccessService::class)->clearCache($userId, $orgId);

            // Clear team permissions cache if the user model can be resolved
            $userModel = config('auth.providers.users.model');
            if ($userModel) {
                $user = $userModel::find($userId);
                if ($user !== null) {
                    $user->clearPermissionCache($orgId);
                }
            }
        }

        // Always refresh JWKS in case signing keys rotated
        app(JwksService::class)->clearCache();

        return response()->json([
            'status' => 'cleared',
            'organization_id' => $orgId,
            'user_id' => $userId,
        ]);
    }
}
