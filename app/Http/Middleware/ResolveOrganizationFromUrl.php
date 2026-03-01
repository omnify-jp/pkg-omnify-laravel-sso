<?php

declare(strict_types=1);

namespace Omnify\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Omnify\Core\Models\Organization;
use Symfony\Component\HttpFoundation\Response;

class ResolveOrganizationFromUrl
{
    /**
     * Resolve organization context from the {organization} route parameter (slug).
     *
     * Sets the same request attribute as StandaloneOrganizationContext so that
     * HasOrganizationScope models resolve the org automatically.
     * Also syncs cookies for API calls and post-login redirects.
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
