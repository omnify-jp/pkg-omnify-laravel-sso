<?php

declare(strict_types=1);

namespace Omnify\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guest middleware for admin routes.
 *
 * Redirects already-authenticated admins away from the login page.
 */
class AdminRedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('admin')->check()) {
            $adminPrefix = config('omnify-auth.routes.standalone_admin_prefix', 'admin');

            return redirect('/'.$adminPrefix);
        }

        return $next($request);
    }
}
