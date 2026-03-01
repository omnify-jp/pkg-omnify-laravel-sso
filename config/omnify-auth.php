<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Mode
    |--------------------------------------------------------------------------
    |
    |   'standalone' – Email/password login managed by this service (default).
    |   'console'    – Login delegated to Omnify Console (OAuth SSO).
    |
    */
    'mode' => env('OMNIFY_AUTH_MODE', 'standalone'),

    /*
    |--------------------------------------------------------------------------
    | Standalone Mode
    |--------------------------------------------------------------------------
    */
    'standalone' => [
        'registration' => env('OMNIFY_AUTH_REGISTRATION', false),
        'password_reset' => env('OMNIFY_AUTH_PASSWORD_RESET', true),
        'redirect_after_login' => env('OMNIFY_AUTH_REDIRECT_AFTER_LOGIN', 'dashboard'),
        'redirect_after_logout' => env('OMNIFY_AUTH_REDIRECT_AFTER_LOGOUT', 'login'),
        'route_prefix' => env('OMNIFY_AUTH_ROUTE_PREFIX', ''),
        'admin_enabled' => env('OMNIFY_STANDALONE_ADMIN_ENABLED', true),
        'admin_redirect_after_login' => env('OMNIFY_ADMIN_REDIRECT_AFTER_LOGIN', 'admin.index'),
        'admin_redirect_after_logout' => env('OMNIFY_ADMIN_REDIRECT_AFTER_LOGOUT', 'admin.login'),
        'pages' => [
            'login' => env('OMNIFY_AUTH_PAGE_LOGIN', 'auth/login'),
            'register' => env('OMNIFY_AUTH_PAGE_REGISTER', 'auth/register'),
            'forgot_password' => env('OMNIFY_AUTH_PAGE_FORGOT_PASSWORD', 'auth/forgot-password'),
            'reset_password' => env('OMNIFY_AUTH_PAGE_RESET_PASSWORD', 'auth/reset-password'),
            'admin_login' => env('OMNIFY_AUTH_PAGE_ADMIN_LOGIN', 'admin/auth/login'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Console SSO Mode
    |--------------------------------------------------------------------------
    |
    | Connection settings + auth pages when mode = 'console'.
    |
    */
    'console' => [
        // Connection to Omnify Console
        'url' => env('SSO_CONSOLE_URL', 'http://auth.test'),
        'timeout' => env('SSO_CONSOLE_TIMEOUT', 10),
        'retry' => env('SSO_CONSOLE_RETRY', 2),

        // OAuth / auth pages
        'service_slug' => env('SSO_SERVICE_SLUG', ''),
        'callback_url' => env('SSO_CALLBACK_URL', '/sso/callback'),
        'route_prefix' => env('SSO_AUTH_PREFIX', 'sso'),
        'redirect_after_login' => env('OMNIFY_SSO_REDIRECT_AFTER_LOGIN', 'dashboard'),
        'pages' => [
            'login' => env('OMNIFY_SSO_PAGE_LOGIN', 'sso/login'),
            'callback' => env('OMNIFY_SSO_PAGE_CALLBACK', 'sso/callback'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Service
    |--------------------------------------------------------------------------
    */
    'service' => [
        'slug' => env('SSO_SERVICE_SLUG', 'boilerplate'),
        // Used for service-to-service API calls to Console (sync, bulk import)
        'secret' => env('SSO_SERVICE_SECRET', ''),
        'callback_url' => env('SSO_CALLBACK_URL', '/sso/callback'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache TTLs
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'jwks_ttl' => env('SSO_JWKS_CACHE_TTL', 60),
        'org_access_ttl' => env('SSO_ORG_ACCESS_CACHE_TTL', 300),
        'user_teams_ttl' => env('SSO_USER_TEAMS_CACHE_TTL', 300),
        'role_permissions_ttl' => env('SSO_ROLE_PERMISSIONS_CACHE_TTL', 3600),
        'team_permissions_ttl' => env('SSO_TEAM_PERMISSIONS_CACHE_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Role Levels
    |--------------------------------------------------------------------------
    */
    'role_levels' => [
        'admin' => 100,
        'manager' => 50,
        'member' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    */
    'routes' => [
        // Organization Context Mode — determines how org context is resolved.
        //
        // URL mode ('@{organization}'):
        //   - Org-scoped routes get prefix: /@{slug}/dashboard, /@{slug}/settings/iam
        //   - Middleware: ResolveOrganizationFromUrl reads slug from URL → sets cookie + request attribute
        //   - Org switcher navigates to /@{new-slug}/dashboard
        //   - Auth routes (login/register) and API routes stay unprefixed
        //
        // Cookie-only mode (''):
        //   - Org-scoped routes have no prefix: /dashboard, /settings/iam
        //   - Middleware: StandaloneOrganizationContext reads cookie → sets request attribute
        //   - Resolution priority: 1) cookie current_organization_id, 2) user default org, 3) first active org
        //   - Org switcher sets cookie + reloads page (URL stays the same)
        //
        // Both modes use cookies for org state. URL mode auto-sets cookie when user visits URL.
        // Shared Inertia prop `org_url_mode` (boolean) lets frontend adapt behavior.
        // Switch modes by changing this env var only — no code changes needed.
        'org_route_prefix' => env('OMNIFY_ORG_ROUTE_PREFIX', ''),

        'prefix' => 'api/sso',
        'admin_prefix' => 'api/admin/sso',
        'middleware' => [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'api',
        ],
        'admin_middleware' => [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'api',
            'core.auth',
            'core.organization',
            'core.role:admin',
        ],
        'access_enabled' => env('SSO_ACCESS_ROUTES_ENABLED', true),
        'access_prefix' => env('SSO_ACCESS_PREFIX', 'settings/iam'),
        // null = auto-detect theo mode:
        //   console    → ['web', 'core.auth']  (Console SSO authentication)
        //   standalone → ['web', 'auth']       (standard Laravel session auth)
        'access_middleware' => null,
        'access_pages_path' => env('SSO_ACCESS_PAGES_PATH', 'settings/iam'),
        'auth_enabled' => env('SSO_AUTH_ROUTES_ENABLED', true),
        'auth_prefix' => env('SSO_AUTH_PREFIX', 'sso'),
        'auth_middleware' => ['web', 'guest'],
        'auth_pages_path' => env('SSO_AUTH_PAGES_PATH', 'sso'),
        'standalone_admin_prefix' => env('SSO_STANDALONE_ADMIN_PREFIX', 'admin'),
        // null = auto-detect: ['web', 'core.admin']
        'standalone_admin_middleware' => null,
        'standalone_admin_pages_path' => env('SSO_STANDALONE_ADMIN_PAGES_PATH', 'admin'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    |
    | The Inertia layout component path provided by the host app.
    | Package pages render inside this layout.
    | Set to null to use the host app's default layout.
    |
    */
    'layout' => env('OMNIFY_AUTH_LAYOUT', null),

    /*
    |--------------------------------------------------------------------------
    | Branch
    |--------------------------------------------------------------------------
    */
    'branch' => [
        'fallback_to_hq' => env('SSO_BRANCH_FALLBACK_TO_HQ', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    */
    'user_model' => env('OMNIFY_AUTH_USER_MODEL', \App\Models\User::class),

    /*
    |--------------------------------------------------------------------------
    | Admin Model
    |--------------------------------------------------------------------------
    |
    | The admin guard and provider are registered automatically by the
    | CoreServiceProvider when mode = 'standalone'. No need to configure
    | auth.php in the host app.
    |
    */
    'admin_model' => env('OMNIFY_AUTH_ADMIN_MODEL', \Omnify\Core\Models\Admin::class),

    /*
    |--------------------------------------------------------------------------
    | Default Middleware
    |--------------------------------------------------------------------------
    */
    'guest_middleware' => ['web', 'guest'],
    'auth_middleware' => ['web', 'auth'],

    /*
    |--------------------------------------------------------------------------
    | Locale
    |--------------------------------------------------------------------------
    */
    'locale' => [
        'enabled' => env('SSO_LOCALE_ENABLED', true),
        'header' => 'Accept-Language',
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | The list of locale codes accepted via the locale cookie.
    | Used by the SetLocale middleware.
    |
    */
    'locales' => ['ja', 'en', 'vi'],

    /*
    |--------------------------------------------------------------------------
    | Organization Settings Hub
    |--------------------------------------------------------------------------
    |
    | Hook point for packages to register sections on the org settings page.
    | Packages push section configs into extra_sections during boot().
    |
    */
    'org_settings' => [
        'extra_sections' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Socialite (Social Login)
    |--------------------------------------------------------------------------
    |
    | Enable social login via Laravel Socialite.
    | Only available in standalone mode. Each provider requires
    | client_id, client_secret, and redirect URL.
    |
    */
    'socialite' => [
        'enabled' => env('OMNIFY_SOCIALITE_ENABLED', false),
        'providers' => [
            // 'google' => [
            //     'client_id' => env('GOOGLE_CLIENT_ID'),
            //     'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            //     'redirect' => env('GOOGLE_REDIRECT_URL', '/auth/google/callback'),
            // ],
            // 'github' => [
            //     'client_id' => env('GITHUB_CLIENT_ID'),
            //     'client_secret' => env('GITHUB_CLIENT_SECRET'),
            //     'redirect' => env('GITHUB_REDIRECT_URL', '/auth/github/callback'),
            // ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    */
    'security' => [
        'allowed_redirect_hosts' => array_filter(explode(',', env('SSO_ALLOWED_REDIRECT_HOSTS', ''))),
        'require_https_redirects' => env('SSO_REQUIRE_HTTPS_REDIRECTS', true),
        'max_redirect_url_length' => 2048,
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings Page
    |--------------------------------------------------------------------------
    |
    | Configuration for the user settings page (/settings).
    | Host apps can register additional sections via extra_sections.
    |
    | Each extra section: ['key' => 'string', 'label' => 'string', 'icon' => 'string', 'href' => '/path']
    |
    */
    'settings' => [
        'enabled' => env('OMNIFY_SETTINGS_ENABLED', true),
        'prefix' => env('OMNIFY_SETTINGS_PREFIX', 'settings'),
        'page' => env('OMNIFY_SETTINGS_PAGE', 'settings/index'),
        'extra_sections' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('SSO_LOGGING_ENABLED', true),
        'channel' => env('SSO_LOG_CHANNEL', 'sso'),
        'level' => env('SSO_LOG_LEVEL', 'debug'),
    ],

];
