<?php

declare(strict_types=1);

namespace Omnify\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Omnify\Core\Models\Organization;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cookie-only mode middleware for organization context resolution.
 *
 * Used when OMNIFY_ORG_ROUTE_PREFIX is empty (no org slug in URL).
 * Registered as middleware alias 'core.standalone.org'.
 *
 * Resolution priority:
 *   1. Cookie `current_organization_id` — set by org switcher frontend
 *   2. User's default org — `$user->console_organization_id`
 *   3. First active org — fallback when user has no default
 *
 * Sets `organizationId` on request attributes (camelCase, required by ContextService).
 * Does nothing when user is not authenticated.
 *
 * Counterpart: ResolveOrganizationFromUrl (URL mode, alias 'core.org.url')
 *
 * @see \Omnify\Core\Http\Middleware\ResolveOrganizationFromUrl  URL mode equivalent
 * @see config('omnify-auth.routes.org_route_prefix')            Mode switch
 */
class StandaloneOrganizationContext
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $organization = null;

            // Priority 1: Cookie (set by org switcher or ResolveOrganizationFromUrl)
            $cookieOrgId = $request->cookie('current_organization_id');
            if ($cookieOrgId) {
                $organization = Organization::where('is_active', true)
                    ->currentMode()
                    ->where('console_organization_id', $cookieOrgId)
                    ->first();
            }

            // Priority 2: User's default org
            if (! $organization && $user->console_organization_id) {
                $organization = Organization::where('is_active', true)
                    ->currentMode()
                    ->where('console_organization_id', $user->console_organization_id)
                    ->first();
            }

            // Priority 3: First active org
            $organization ??= Organization::where('is_active', true)->currentMode()->first();

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
