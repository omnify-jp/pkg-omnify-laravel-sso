<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Omnify\SsoClient\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that requires branch context to be set.
 *
 * Use this middleware on routes that must have a branch context.
 * Returns 400 Bad Request if X-Branch-Id header is missing.
 *
 * Usage in routes:
 *   Route::get('/locations', ...)->middleware('sso.require-branch');
 *
 * @see \Omnify\SsoClient\Http\Middleware\SsoOrganizationAccess
 */
class RequireBranch
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // First check organization
        if (! Context::hasOrganization()) {
            return response()->json([
                'error' => 'ORGANIZATION_REQUIRED',
                'message' => 'X-Organization-Id header is required for this endpoint',
            ], 400);
        }

        // Then check branch
        if (! Context::hasBranch()) {
            return response()->json([
                'error' => 'BRANCH_REQUIRED',
                'message' => 'X-Branch-Id header is required for this endpoint',
            ], 400);
        }

        return $next($request);
    }
}
