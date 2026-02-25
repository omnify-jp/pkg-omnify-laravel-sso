<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SsoAuthenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Use Sanctum authentication
        if (! Auth::guard('sanctum')->check()) {
            return response()->json([
                'error' => 'UNAUTHENTICATED',
                'message' => 'Authentication required',
            ], 401);
        }

        // Set authenticated user on request
        $user = Auth::guard('sanctum')->user();
        $request->setUserResolver(fn () => $user);

        // Add SSO user info to request attributes
        $request->attributes->set('ssoUser', $user);

        return $next($request);
    }
}
