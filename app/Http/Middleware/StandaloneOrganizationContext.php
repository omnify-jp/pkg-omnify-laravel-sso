<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Omnify\SsoClient\Models\Organization;
use Symfony\Component\HttpFoundation\Response;

class StandaloneOrganizationContext
{
    /**
     * Handle an incoming request.
     *
     * Sets the organizationId request attribute so HasOrganizationScope models
     * can resolve org context without a RuntimeException.
     *
     * Only sets the attribute when a user is authenticated and an active
     * organization can be found. Falls back gracefully (ContextService will
     * then try the session fallback path).
     *
     * @param  \Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $organization = null;

            if ($user->console_organization_id) {
                $organization = Organization::where('is_active', true)
                    ->where('console_organization_id', $user->console_organization_id)
                    ->first();
            }

            $organization ??= Organization::where('is_active', true)->first();

            if ($organization) {
                // CRITICAL: must be camelCase 'organizationId' — ContextService reads
                // this exact key via $request->attributes->get('organizationId').
                // Using snake_case 'organization_id' will cause a RuntimeException
                // in every HasOrganizationScope model query.
                $request->attributes->set('organizationId', $organization->id);
            }
        }

        return $next($request);
    }
}
