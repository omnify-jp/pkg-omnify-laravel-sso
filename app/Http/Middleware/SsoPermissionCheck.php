<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware for checking user permissions.
 *
 * Supports branch-level permissions via X-Branch-Id header context.
 * Permission check considers: global roles + org-wide roles + branch-specific roles + team permissions.
 *
 * @see HasTeamPermissions::hasAnyPermission() For permission resolution logic
 */
class SsoPermissionCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(Request): Response  $next
     * @param  string  $permissions  Permission(s) required (pipe-separated for OR logic)
     */
    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'error' => 'UNAUTHENTICATED',
                'message' => 'Authentication required',
            ], 401);
        }

        // Check if user model has hasPermission method
        if (! method_exists($user, 'hasPermission') && ! method_exists($user, 'hasAnyPermission')) {
            return response()->json([
                'error' => 'CONFIGURATION_ERROR',
                'message' => 'User model does not support permission checking',
            ], 500);
        }

        // Get org and branch context from request attributes (set by SsoOrganizationAccess middleware)
        $organizationId = $request->attributes->get('organizationId');
        $branchId = $request->attributes->get('branchId'); // NEW: Branch context for branch-level permissions

        // Cast to string for scoped permission methods (organizationId/branchId can be int or string depending on source)
        $organizationId = $organizationId !== null ? (string) $organizationId : null;
        $branchId = $branchId !== null ? (string) $branchId : null;

        // Parse permissions (pipe-separated for OR logic)
        $permissionList = explode('|', $permissions);

        // Check if user has any of the required permissions
        // Permission check now considers branch context for scoped role permissions
        $hasPermission = false;

        if (method_exists($user, 'hasAnyPermission')) {
            $hasPermission = $user->hasAnyPermission($permissionList, $organizationId, $branchId);
        } else {
            foreach ($permissionList as $permission) {
                if ($user->hasPermission(trim($permission), $organizationId, $branchId)) {
                    $hasPermission = true;
                    break;
                }
            }
        }

        if (! $hasPermission) {
            return response()->json([
                'error' => 'PERMISSION_DENIED',
                'message' => 'Required permission not granted',
                'required_permissions' => $permissionList,
            ], 403);
        }

        return $next($request);
    }
}
