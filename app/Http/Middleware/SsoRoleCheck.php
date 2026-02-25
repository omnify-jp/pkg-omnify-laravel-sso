<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware for checking user role level.
 *
 * Supports branch-level permissions via X-Branch-Id header context.
 * Role check considers: global roles + org-wide roles + branch-specific roles.
 *
 * Uses role hierarchy: higher level = more privileged.
 * Default levels: admin=100, manager=50, member=10
 *
 * @see User::getHighestRoleLevelInContext() For role level resolution
 */
class SsoRoleCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(Request): Response  $next
     * @param  string  $role  Required role name (user must have this role or higher level)
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'error' => 'UNAUTHENTICATED',
                'message' => 'Authentication required',
            ], 401);
        }

        // Get org and branch context from request attributes (set by SsoOrganizationAccess middleware)
        $organizationId = $request->attributes->get('organizationId');
        $branchId = $request->attributes->get('branchId'); // NEW: Branch context for branch-level permissions

        // Cast to string for scoped role methods (organizationId/branchId can be int or string depending on source)
        $organizationId = $organizationId !== null ? (string) $organizationId : null;
        $branchId = $branchId !== null ? (string) $branchId : null;

        // Get role levels from config
        $roleLevels = config('omnify-auth.role_levels', [
            'admin' => 100,
            'manager' => 50,
            'member' => 10,
        ]);

        $requiredLevel = $roleLevels[$role] ?? 0;

        // Get user's highest role level in this context
        // Uses scoped role assignment logic: global + org-wide + branch-specific
        $userLevel = 0;

        if (method_exists($user, 'getHighestRoleLevelInContext')) {
            // New method: considers scoped role assignments
            $userLevel = $user->getHighestRoleLevelInContext($organizationId, $branchId);
        } else {
            // Fallback: use service role from Console (legacy behavior)
            $userRole = $request->attributes->get('serviceRole');
            $userLevel = $roleLevels[$userRole] ?? 0;
        }

        if ($userLevel < $requiredLevel) {
            return response()->json([
                'error' => 'INSUFFICIENT_ROLE',
                'message' => "Role '{$role}' or higher is required",
                'required_role' => $role,
                'required_level' => $requiredLevel,
                'current_level' => $userLevel,
            ], 403);
        }

        return $next($request);
    }
}
