# Configuration Reference

All configuration lives in `config/omnify-auth.php`.

## Environment Variables

### Auth Mode

| Variable | Values | Default | Description |
|----------|--------|---------|-------------|
| `OMNIFY_AUTH_MODE` | `standalone` / `console` | `standalone` | Authentication mode |

### Standalone Mode

| Variable | Default | Description |
|----------|---------|-------------|
| `OMNIFY_AUTH_REGISTRATION` | `false` | Allow open registration |
| `OMNIFY_AUTH_PASSWORD_RESET` | `true` | Enable forgot-password flow |
| `OMNIFY_AUTH_REDIRECT_AFTER_LOGIN` | `dashboard` | Named route after login |
| `OMNIFY_AUTH_REDIRECT_AFTER_LOGOUT` | `login` | Named route after logout |
| `OMNIFY_AUTH_ROUTE_PREFIX` | `` (empty) | URL prefix for auth routes |

### Console SSO Mode

| Variable | Default | Description |
|----------|---------|-------------|
| `SSO_CONSOLE_URL` | `http://auth.test` | Omnify Console base URL |
| `SSO_CONSOLE_TIMEOUT` | `10` | HTTP timeout in seconds |
| `SSO_CONSOLE_RETRY` | `2` | HTTP retry attempts |
| `SSO_SERVICE_SLUG` | `` | Service identifier in Console |
| `SSO_CALLBACK_URL` | `/sso/callback` | OAuth callback path |
| `SSO_AUTH_PREFIX` | `sso` | URL prefix for SSO routes |

### Service

| Variable | Default | Description |
|----------|---------|-------------|
| `SSO_SERVICE_SLUG` | `boilerplate` | Service slug (used in API calls) |
| `SSO_SERVICE_SECRET` | `` | Secret for service-to-service API (sync command) |

### Routes

| Variable | Default | Description |
|----------|---------|-------------|
| `SSO_ACCESS_ROUTES_ENABLED` | `true` | Mount IAM admin routes |
| `SSO_ACCESS_PREFIX` | `admin/iam` | URL prefix for IAM pages |
| `SSO_ACCESS_PAGES_PATH` | `admin/iam` | Inertia component path prefix |
| `SSO_AUTH_ROUTES_ENABLED` | `true` | Mount auth routes |
| `SSO_AUTH_PREFIX` | `sso` | URL prefix for SSO routes |
| `SSO_AUTH_PAGES_PATH` | `sso` | Inertia component path for SSO pages |

### Cache TTLs

| Variable | Default | Description |
|----------|---------|-------------|
| `SSO_JWKS_CACHE_TTL` | `60` | JWKS cache in seconds |
| `SSO_ORG_ACCESS_CACHE_TTL` | `300` | Org access cache in seconds |
| `SSO_USER_TEAMS_CACHE_TTL` | `300` | User teams cache in seconds |
| `SSO_ROLE_PERMISSIONS_CACHE_TTL` | `3600` | Role permissions cache in seconds |
| `SSO_TEAM_PERMISSIONS_CACHE_TTL` | `3600` | Team permissions cache in seconds |

### Security

| Variable | Default | Description |
|----------|---------|-------------|
| `SSO_ALLOWED_REDIRECT_HOSTS` | `` | Comma-separated allowed redirect hosts |
| `SSO_REQUIRE_HTTPS_REDIRECTS` | `true` | Require HTTPS for redirects |

### Logging

| Variable | Default | Description |
|----------|---------|-------------|
| `SSO_LOGGING_ENABLED` | `true` | Enable SSO log channel |
| `SSO_LOG_CHANNEL` | `sso` | Log channel name |
| `SSO_LOG_LEVEL` | `debug` | Log level |

### Branch

| Variable | Default | Description |
|----------|---------|-------------|
| `SSO_BRANCH_FALLBACK_TO_HQ` | `false` | Fallback to HQ branch if user has none |

---

## Full Config File

`config/omnify-auth.php` — annotated:

```php
return [

    // 'standalone' | 'console'
    'mode' => env('OMNIFY_AUTH_MODE', 'standalone'),

    'standalone' => [
        'registration'          => env('OMNIFY_AUTH_REGISTRATION', false),
        'password_reset'        => env('OMNIFY_AUTH_PASSWORD_RESET', true),
        'redirect_after_login'  => env('OMNIFY_AUTH_REDIRECT_AFTER_LOGIN', 'dashboard'),
        'redirect_after_logout' => env('OMNIFY_AUTH_REDIRECT_AFTER_LOGOUT', 'login'),
        'route_prefix'          => env('OMNIFY_AUTH_ROUTE_PREFIX', ''),
        'pages' => [
            'login'          => env('OMNIFY_AUTH_PAGE_LOGIN', 'auth/login'),
            'register'       => env('OMNIFY_AUTH_PAGE_REGISTER', 'auth/register'),
            'forgot_password'=> env('OMNIFY_AUTH_PAGE_FORGOT_PASSWORD', 'auth/forgot-password'),
            'reset_password' => env('OMNIFY_AUTH_PAGE_RESET_PASSWORD', 'auth/reset-password'),
        ],
    ],

    'console' => [
        'url'          => env('SSO_CONSOLE_URL', 'http://auth.test'),
        'timeout'      => env('SSO_CONSOLE_TIMEOUT', 10),
        'retry'        => env('SSO_CONSOLE_RETRY', 2),
        'service_slug' => env('SSO_SERVICE_SLUG', ''),
        'callback_url' => env('SSO_CALLBACK_URL', '/sso/callback'),
        'route_prefix' => env('SSO_AUTH_PREFIX', 'sso'),
        'pages' => [
            'login'    => env('OMNIFY_SSO_PAGE_LOGIN', 'sso/login'),
            'callback' => env('OMNIFY_SSO_PAGE_CALLBACK', 'sso/callback'),
        ],
    ],

    'service' => [
        'slug'         => env('SSO_SERVICE_SLUG', 'boilerplate'),
        'secret'       => env('SSO_SERVICE_SECRET', ''),    // for sso:sync-from-console
        'callback_url' => env('SSO_CALLBACK_URL', '/sso/callback'),
    ],

    'cache' => [
        'jwks_ttl'              => env('SSO_JWKS_CACHE_TTL', 60),
        'org_access_ttl'        => env('SSO_ORG_ACCESS_CACHE_TTL', 300),
        'user_teams_ttl'        => env('SSO_USER_TEAMS_CACHE_TTL', 300),
        'role_permissions_ttl'  => env('SSO_ROLE_PERMISSIONS_CACHE_TTL', 3600),
        'team_permissions_ttl'  => env('SSO_TEAM_PERMISSIONS_CACHE_TTL', 3600),
    ],

    'role_levels' => [
        'admin'   => 100,
        'manager' => 50,
        'member'  => 10,
    ],

    'routes' => [
        'prefix'            => 'api/sso',
        'admin_prefix'      => 'api/admin/sso',
        'access_enabled'    => env('SSO_ACCESS_ROUTES_ENABLED', true),
        'access_prefix'     => env('SSO_ACCESS_PREFIX', 'admin/iam'),
        'access_middleware' => ['web', 'sso.auth'],
        'access_pages_path' => env('SSO_ACCESS_PAGES_PATH', 'admin/iam'),
        'auth_enabled'      => env('SSO_AUTH_ROUTES_ENABLED', true),
        'auth_prefix'       => env('SSO_AUTH_PREFIX', 'sso'),
        'auth_middleware'   => ['web', 'guest'],
        'auth_pages_path'   => env('SSO_AUTH_PAGES_PATH', 'sso'),
    ],

    'branch' => [
        'fallback_to_hq' => env('SSO_BRANCH_FALLBACK_TO_HQ', false),
    ],

    'user_model' => env('OMNIFY_AUTH_USER_MODEL', \App\Models\User::class),

    'security' => [
        'allowed_redirect_hosts'  => array_filter(explode(',', env('SSO_ALLOWED_REDIRECT_HOSTS', ''))),
        'require_https_redirects' => env('SSO_REQUIRE_HTTPS_REDIRECTS', true),
        'max_redirect_url_length' => 2048,
    ],

    'logging' => [
        'enabled' => env('SSO_LOGGING_ENABLED', true),
        'channel' => env('SSO_LOG_CHANNEL', 'sso'),
        'level'   => env('SSO_LOG_LEVEL', 'debug'),
    ],

];
```

---

## Role Level Hierarchy

Roles use integer levels for hierarchy checks:

```php
'role_levels' => [
    'admin'   => 100,
    'manager' => 50,
    'member'  => 10,
],
```

`sso.role:manager` middleware allows `manager` (level 50) **and** any higher level (`admin` = 100).
Customize in `config/omnify-auth.php` to add your own roles.

---

## Security: Allowed Redirect Hosts

Prevents open redirect attacks. Set allowed hosts in `.env`:

```env
# Allow specific domains (comma-separated)
SSO_ALLOWED_REDIRECT_HOSTS=myapp.com,api.myapp.com

# Development — leave empty to allow same-host redirects
SSO_ALLOWED_REDIRECT_HOSTS=
```

In development with HTTPS disabled:

```env
SSO_REQUIRE_HTTPS_REDIRECTS=false
```
