<?php

declare(strict_types=1);

namespace Omnify\Core\Http\Controllers;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the organization selection page.
 *
 * Shown when a user is authenticated but has no organization context.
 * The page lets the user pick an org (and branch if required) without
 * loading the app shell — preventing background API calls with unknown org.
 *
 * Organization and branch data come from CoreHandleInertiaRequests shared props.
 */
class SelectOrganizationController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render(
            config('omnify-auth.routes.select_org_page', 'sso/select-organization')
        );
    }
}
