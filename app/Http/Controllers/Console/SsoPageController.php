<?php

declare(strict_types=1);

namespace Omnify\Core\Http\Controllers\Console;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Omnify\Core\Services\ConsoleApiService;

/**
 * Controller for SSO authentication pages.
 *
 * Renders Inertia pages for SSO login and callback.
 * Page paths are configurable via 'omnify-auth.routes.auth_pages_path'.
 */
class SsoPageController extends Controller
{
    public function __construct(
        private readonly ConsoleApiService $consoleApi,
    ) {}

    /**
     * Get the base path for SSO auth pages.
     */
    protected function getPagePath(string $page): string
    {
        $basePath = config('omnify-auth.routes.auth_pages_path', 'sso');

        return "{$basePath}/{$page}";
    }

    /**
     * SSO login page.
     *
     * Renders a page with the Console SSO authorize URL
     * so the frontend can redirect users to Console for authentication.
     */
    public function login(): Response
    {
        $consoleUrl = $this->consoleApi->getConsoleUrl();
        $serviceSlug = $this->consoleApi->getServiceSlug();
        $callbackUrl = url(config('omnify-auth.service.callback_url', '/sso/callback'));

        $ssoAuthorizeUrl = $consoleUrl.'/sso/authorize?'.http_build_query([
            'service_slug' => $serviceSlug,
            'redirect_uri' => $callbackUrl,
        ]);

        return Inertia::render($this->getPagePath('login'), [
            'ssoAuthorizeUrl' => $ssoAuthorizeUrl,
            'consoleUrl' => $consoleUrl,
        ]);
    }

    /**
     * SSO callback page.
     *
     * Renders a page that receives the authorization code from Console
     * and exchanges it for tokens via the API.
     */
    public function callback(): Response
    {
        $redirectRoute = config('omnify-auth.console.redirect_after_login', 'dashboard');

        return Inertia::render($this->getPagePath('callback'), [
            'callbackApiUrl' => url(config('omnify-auth.routes.prefix', 'api/sso').'/callback'),
            'redirectUrl' => route($redirectRoute),
        ]);
    }
}
