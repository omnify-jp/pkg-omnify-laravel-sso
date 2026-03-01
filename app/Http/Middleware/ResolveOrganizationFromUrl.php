<?php

declare(strict_types=1);

namespace Omnify\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Omnify\Core\Models\Organization;
use Symfony\Component\HttpFoundation\Response;

/**
 * URL mode middleware for organization context resolution.
 *
 * Used when OMNIFY_ORG_ROUTE_PREFIX='@{organization}' (org slug in URL).
 * Registered as middleware alias 'core.org.url'.
 *
 * Reads {organization} route parameter (slug) → resolves org from DB
 * → sets `organizationId` on request attributes + syncs cookies.
 *
 * The cookies it sets (`current_organization_id`, `current_organization_slug`)
 * are used by:
 *   - API calls (non-org-prefixed routes that need org context)
 *   - Post-login redirects (home route reads cookie to build /@{slug}/dashboard)
 *   - StandaloneOrganizationContext (if mode is switched later)
 *
 * Counterpart: StandaloneOrganizationContext (cookie-only mode, alias 'core.standalone.org')
 *
 * @see \Omnify\Core\Http\Middleware\StandaloneOrganizationContext  Cookie-only mode equivalent
 * @see config('omnify-auth.routes.org_route_prefix')               Mode switch
 */
class ResolveOrganizationFromUrl
{
    /**
     * Resolve organization context from the {organization} route parameter (slug).
     *
     * @param  \Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('organization');

        if (! $slug) {
            return $next($request);
        }

        $organization = Organization::where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (! $organization) {
            return redirect('/');
        }

        // CRITICAL: must be camelCase 'organizationId' — ContextService reads
        // this exact key via $request->attributes->get('organizationId').
        $request->attributes->set('organizationId', $organization->id);

        // Remove {organization} from route parameters so it doesn't leak into
        // controller method injection. Without this, Laravel's positional
        // parameter matching causes controllers to receive the org slug
        // instead of their own parameters (e.g. $userId gets "default").
        $request->route()->forgetParameter('organization');

        $response = $next($request);

        // Sync cookies so API calls and login redirects know the current org
        $cookieMaxAge = 60 * 24 * 365; // 1 year in minutes
        $response->headers->setCookie(cookie(
            'current_organization_id',
            $organization->console_organization_id,
            $cookieMaxAge,
        ));
        $response->headers->setCookie(cookie(
            'current_organization_slug',
            $organization->slug,
            $cookieMaxAge,
        ));

        return $response;
    }
}
