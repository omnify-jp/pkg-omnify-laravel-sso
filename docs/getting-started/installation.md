# Installation Guide

## Requirements

- PHP 8.2 or higher
- Laravel 11.0+ or 12.0+
- MySQL 8.0+ / PostgreSQL 13+ / SQLite 3.35+
- Composer 2.0+

## Step 1: Install via Composer

```bash
composer require omnifyjp/pkg-omnify-laravel-core
```

Laravel auto-discovers the service provider. No manual registration needed.

## Step 2: Publish & Run Migrations

```bash
php artisan vendor:publish --tag=sso-migrations
php artisan migrate
```

Tables created:
- `users` — with SSO fields (console_user_id, tokens, etc.)
- `branches` — Console branch mirror
- `locations` — Location under a branch
- `roles` — Local roles with level hierarchy
- `permissions` — Local permissions
- `role_permissions` — Role ↔ Permission pivot
- `role_user` — Scoped user ↔ role pivot

## Step 3: Configure Environment

Add to your `.env`:

```env
# Auth mode: 'standalone' or 'console'
OMNIFY_AUTH_MODE=standalone
```

### Standalone mode (email/password)

```env
OMNIFY_AUTH_MODE=standalone
OMNIFY_AUTH_REGISTRATION=false
OMNIFY_AUTH_PASSWORD_RESET=true
OMNIFY_AUTH_REDIRECT_AFTER_LOGIN=dashboard
OMNIFY_AUTH_REDIRECT_AFTER_LOGOUT=login
```

### Console SSO mode

```env
OMNIFY_AUTH_MODE=console
SSO_CONSOLE_URL=https://console.omnify.jp
SSO_SERVICE_SLUG=your-service-slug
SSO_CALLBACK_URL=/sso/callback
SSO_SERVICE_SECRET=your-secret   # for bulk sync command
```

## Step 4: Set Up Your User Model

Your `app/Models/User.php` must extend the package's User:

```php
<?php

namespace App\Models;

use Omnify\Core\Models\User as SsoUser;

class User extends SsoUser
{
    // Add your application-specific methods here
}
```

## Step 5: Register Middleware

In `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'sso.auth'         => \Omnify\Core\Http\Middleware\SsoRoleCheck::class,
        'sso.organization' => \Omnify\Core\Http\Middleware\SsoOrganizationAccess::class,
        'sso.role'         => \Omnify\Core\Http\Middleware\SsoRoleCheck::class,
    ]);
})
```

## Step 6: Seed Initial Data (Optional)

**Standalone mode** — seed a full demo org:

```bash
php artisan db:seed --class=\\Omnify\\Core\\Database\\Seeders\\SsoStandaloneSeeder
```

**Console mode** — bulk import from Console:

```bash
php artisan sso:sync-from-console --organization=your-org-slug
```

> For console mode, users auto-sync on first SSO login. The sync command is useful for pre-populating data before first login (dev/staging only).

## Verification

```bash
# Check routes are registered
php artisan route:list --path=sso
php artisan route:list --path=admin/iam

# Check migrations ran
php artisan migrate:status
```

## Troubleshooting

### Routes not found

```bash
php artisan package:discover
php artisan route:clear
```

### Migration errors

```bash
php artisan config:clear
php artisan cache:clear
php artisan migrate:fresh
```

## Next Steps

- [Configuration](configuration.md) — All env vars and config options
- [Authentication](../guides/authentication.md) — Auth flows for both modes
- [Authorization](../guides/authorization.md) — RBAC and permissions
- [Seeders](../reference/seeders.md) — Seed users, roles, permissions
