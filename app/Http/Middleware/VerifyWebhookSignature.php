<?php

declare(strict_types=1);

namespace Omnify\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    /**
     * Handle an incoming request.
     *
     * Validates the HMAC-SHA256 signature from the X-Omnify-Signature header
     * using SSO_SERVICE_SECRET as the signing key.
     *
     * @param  \Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('omnify-auth.service.secret', '');

        if (empty($secret)) {
            return response()->json(['error' => 'Invalid webhook signature'], 403);
        }

        $signature = $request->header('X-Omnify-Signature', '');
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            return response()->json(['error' => 'Invalid webhook signature'], 403);
        }

        return $next($request);
    }
}
