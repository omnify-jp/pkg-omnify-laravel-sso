<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Omnify\SsoClient\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that requires organization context to be set.
 *
 * Use this middleware on routes that must have an organization context.
 * Returns 400 Bad Request if X-Organization-Id header is missing.
 *
 * Usage in routes:
 *   Route::get('/departments', ...)->middleware('sso.require-organization');
 *
 * @see \Omnify\SsoClient\Http\Middleware\SsoOrganizationAccess
 */
class RequireOrganization
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Context::hasOrganization()) {
            return response()->json([
                'error' => 'ORGANIZATION_REQUIRED',
                'message' => 'X-Organization-Id header is required for this endpoint',
            ], 400);
        }

        return $next($request);
    }
}
