# Omnify Core Package (`pkg-omnify-laravel-core`)

## What This Is

A **self-contained Laravel package** (like Telescope/Horizon) providing authentication, IAM (roles/permissions), and multi-tenant management (organizations, branches, brands, locations, users). Install via Composer and get full functionality — the host app only provides a layout.

## Namespace

`Omnify\Core` — all classes live under this namespace.

## Dual-Mode Architecture

Controlled by `OMNIFY_AUTH_MODE` env var (config: `omnify-auth.mode`):

### Standalone Mode (default)
- Email/password authentication (local)
- Full admin CRUD: organizations, branches, locations, users
- Admin model (separate from User) — for system administrators
- `AdminCreateCommand` (`php artisan admin:create`)
- Routes: `/admin/*` (admin CRUD), `/admin/iam/*` (IAM)

### Console Mode
- OAuth SSO via Omnify Console
- NO local admin CRUD — managed by Console
- NO Admin model
- Routes: `/sso/*` (OAuth), `/admin/iam/*` (IAM)

## Key Files

| Path | Purpose |
|------|---------|
| `config/omnify-auth.php` | All config (mode, routes, layout, cache, security) |
| `app/CoreServiceProvider.php` | Boot: routes, middleware, commands, migrations, publishing |
| `routes/sso.php` | API routes (auth, admin API) |
| `routes/access.php` | IAM pages (Inertia) — both modes |
| `routes/auth-standalone.php` | Standalone login/password pages |
| `routes/auth.php` | Console OAuth flow |
| `routes/admin-standalone.php` | Standalone admin CRUD pages |
| `app/Http/Controllers/Admin/` | API + page controllers for admin |
| `app/Http/Controllers/Auth/` | Standalone + 2FA auth controllers |
| `app/Http/Controllers/AccessPageController.php` | IAM Inertia pages (1 controller, many pages) |
| `resources/js/pages/admin/iam/` | IAM React pages |
| `resources/js/pages/admin/organizations/` | Org admin React pages (standalone) |
| `resources/js/pages/admin/branches/` | Branch admin React pages (standalone) |
| `resources/js/pages/admin/users/` | User admin React pages (standalone) |

## Middleware Aliases

| Alias | Class | Purpose |
|-------|-------|---------|
| `core.auth` | SsoAuthenticate | Console SSO JWT auth |
| `core.organization` | SsoOrganizationAccess | Org access check |
| `core.role` | SsoRoleCheck | Role-based access (e.g., `core.role:admin`) |
| `core.permission` | SsoPermissionCheck | Permission-based access |
| `core.branch` | SetBranchFromHeader | Branch context from header |
| `core.share` | ShareSsoData | Share SSO data with Inertia |
| `core.standalone.org` | StandaloneOrganizationContext | Set org context (standalone) |

## Config Layout Pattern

Host app provides layout path via config:
```php
// config/omnify-auth.php
'layout' => 'layouts/admin-layout',
```

Package renders Inertia pages using this layout. Pages path is also configurable:
```php
'routes' => [
    'standalone_admin_pages_path' => 'admin', // Inertia page path prefix
    'access_pages_path' => 'admin/iam',
]
```

## Naming Rules

- **NO abbreviations**: `OrganizationAdminController` (NOT `OrgAdminController`)
- Full entity names: `Organization`, `Branch`, `Location` (NOT `Org`, `Br`, `Loc`)
- Standalone-specific classes: prefix/suffix with `Standalone` (e.g., `UserStandaloneAdminController`, `StandaloneLoginController`)

## Multi-Tenant Entities

Core package manages the full tenant hierarchy:
- **Organization** — top-level tenant
- **Branch** — belongs to Organization
- **Location** — belongs to Branch
- **User** — belongs to Organization, scoped by Branch
- **Admin** — standalone only, system administrator (separate from User)

## Publishing

```bash
php artisan vendor:publish --tag=omnify-auth-config  # Config
php artisan vendor:publish --tag=sso-migrations       # Migrations
php artisan vendor:publish --tag=sso-pages            # Auth pages
php artisan vendor:publish --tag=sso-admin-pages      # Admin CRUD pages
php artisan vendor:publish --tag=sso-react            # Contexts/hooks/providers
php artisan vendor:publish --tag=sso-seeders          # Seeders
```

<!-- OMNIFY_SECTION_START -->
## Omnify Schema System

This project uses **Omnify** for schema-driven code generation.

### Quick Reference

- **Schema Guide**: @.claude/omnify/guides/omnify/schema-guide.md
- **Config Guide**: @.claude/omnify/guides/omnify/config-guide.md

### Commands

```bash
npx omnify generate    # Generate code from schemas
npx omnify validate    # Validate schemas
php artisan migrate    # Run database migrations
```

### Critical Rules

#### DO NOT EDIT Auto-Generated Files
- `database/migrations/omnify/**` - Regenerated on `npx omnify generate`
- `app/Models/OmnifyBase/**` - Base models (extend, don't edit)
- `app/Http/Requests/OmnifyBase/**` - Base requests
- `app/Http/Resources/OmnifyBase/**` - Base resources

#### Schema-First Workflow
1. Edit YAML schema in `schemas/`
2. Run `npx omnify generate`
3. Run `php artisan migrate`

**NEVER use `php artisan make:migration`** - Always use schemas!
<!-- OMNIFY_SECTION_END -->
