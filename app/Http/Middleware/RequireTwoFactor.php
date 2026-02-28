<?php

declare(strict_types=1);

namespace Omnify\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that enforces 2FA verification for authenticated users who have
 * enabled TOTP-based two-factor authentication.
 *
 * - If the user has not enabled 2FA (no google2fa_secret or not confirmed), pass through.
 * - If the user has enabled 2FA and has verified it in the current session (within the
 *   remember_device_days window), pass through.
 * - Otherwise, redirect to the 2FA challenge page.
 *
 * Register as alias '2fa' in bootstrap/app.php and apply to routes that require 2FA:
 *   $middleware->alias(['2fa' => RequireTwoFactor::class]);
 */
class RequireTwoFactor
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // If not authenticated, pass through (let auth middleware handle it).
        if (! $user) {
            return $next($request);
        }

        // If user has not enabled 2FA, pass through without challenge.
        if (! $this->isAuthenticated($user)) {
            return $next($request);
        }

        // Check if 2FA has been verified in the current session.
        if ($this->isVerifiedInSession($request)) {
            return $next($request);
        }

        // Redirect to challenge page if Inertia or standard request.
        return redirect()->route('auth.two-factor-challenge');
    }

    /**
     * Determine if the user has 2FA fully enabled (secret set and confirmed).
     */
    protected function isAuthenticated(mixed $user): bool
    {
        return ! empty($user->google2fa_secret) && ! empty($user->two_factor_confirmed_at);
    }

    /**
     * Check if 2FA has been verified in the current session within the remember window.
     */
    protected function isVerifiedInSession(Request $request): bool
    {
        $verifiedAt = $request->session()->get('2fa_verified_at');

        if (! $verifiedAt) {
            return false;
        }

        $rememberDays = (int) config('security.two_factor.remember_device_days', 30);

        if ($rememberDays === 0) {
            return true;
        }

        return (int) $verifiedAt > now()->subDays($rememberDays)->timestamp;
    }
}
