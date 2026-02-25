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
        'pages' => [
            'login' => env('OMNIFY_AUTH_PAGE_LOGIN', 'auth/login'),
            'register' => env('OMNIFY_AUTH_PAGE_REGISTER', 'auth/register'),
            'forgot_password' => env('OMNIFY_AUTH_PAGE_FORGOT_PASSWORD', 'auth/forgot-password'),
            'reset_password' => env('OMNIFY_AUTH_PAGE_RESET_PASSWORD', 'auth/reset-password'),
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
        'prefix' => 'api/sso',
        'admin_prefix' => 'api/admin/sso',
        'middleware' => [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'api',
        ],
        'admin_middleware' => [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'api',
            'sso.auth',
            'sso.organization',
            'sso.role:admin',
        ],
        'access_enabled' => env('SSO_ACCESS_ROUTES_ENABLED', true),
        'access_prefix' => env('SSO_ACCESS_PREFIX', 'admin/iam'),
        // null = auto-detect theo mode:
        //   console    → ['web', 'sso.auth']  (Console SSO authentication)
        //   standalone → ['web', 'auth']       (standard Laravel session auth)
        'access_middleware' => null,
        'access_pages_path' => env('SSO_ACCESS_PAGES_PATH', 'admin/iam'),
        'auth_enabled' => env('SSO_AUTH_ROUTES_ENABLED', true),
        'auth_prefix' => env('SSO_AUTH_PREFIX', 'sso'),
        'auth_middleware' => ['web', 'guest'],
        'auth_pages_path' => env('SSO_AUTH_PAGES_PATH', 'sso'),
    ],

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
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('SSO_LOGGING_ENABLED', true),
        'channel' => env('SSO_LOG_CHANNEL', 'sso'),
        'level' => env('SSO_LOG_LEVEL', 'debug'),
    ],

];
