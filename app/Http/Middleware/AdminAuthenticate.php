<?php

declare(strict_types=1);

namespace Omnify\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Require authentication via the 'admin' guard.
 *
 * Redirects unauthenticated requests to the admin login page.
 * For XHR/JSON requests, returns 401 instead of redirecting.
 */
class AdminAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('admin')->check()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return redirect()->guest(route('admin.login'));
    }
}
