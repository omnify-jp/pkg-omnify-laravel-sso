# Database Seeders

## Overview

| Seeder / Command | Mode | Purpose |
|-----------------|------|---------|
| `SsoStandaloneSeeder` | standalone | Seed demo org with users, roles, permissions, branches, locations |
| `sso:sync-from-console` | console | Bulk import users + branches from Omnify Console API |

---

## SsoStandaloneSeeder

Full demo dataset for standalone mode (email/password). Idempotent — safe to run multiple times.

### Usage

```bash
# Run directly
php artisan db:seed --class=\\Omnify\\Core\\Database\\Seeders\\SsoStandaloneSeeder

# Or call from DatabaseSeeder.php
$this->call(\Omnify\Core\Database\Seeders\SsoStandaloneSeeder::class);
```

### What it creates

**Permissions (10)**

| Slug | Group |
|------|-------|
| `users.view` | users |
| `users.create` | users |
| `users.edit` | users |
| `users.delete` | users |
| `branches.view` | branches |
| `branches.manage` | branches |
| `reports.view` | reports |
| `reports.export` | reports |
| `settings.view` | settings |
| `settings.manage` | settings |

**Roles (3)**

| Name | Slug | Level | Permissions |
|------|------|-------|-------------|
| Quản trị viên | `admin` | 100 | All 10 |
| Quản lý | `manager` | 50 | All except `*.delete`, `settings.manage` |
| Nhân viên | `staff` | 10 | `*.view` only |

**Branches (3)**

| Name | Slug | HQ? |
|------|------|-----|
| Chi nhánh Hà Nội | `ha-noi` | ✅ |
| Chi nhánh TP.HCM | `tp-hcm` | |
| Chi nhánh Đà Nẵng | `da-nang` | |

**Locations (5)** — spread across branches

**Users (8)**

| Email | Password | Role | Branch |
|-------|----------|------|--------|
| `admin@abc-tech.vn` | `Admin@2024` | admin | Hà Nội (HQ) |
| `nguyen.van.an@abc-tech.vn` | `Manager@2024` | manager | Hà Nội (HQ) |
| `tran.thi.bich@abc-tech.vn` | `Manager@2024` | manager | TP.HCM |
| `le.van.cuong@abc-tech.vn` | `Staff@2024` | staff | Hà Nội (HQ) |
| `pham.thi.dung@abc-tech.vn` | `Staff@2024` | staff | TP.HCM |
| `hoang.van.em@abc-tech.vn` | `Staff@2024` | staff | Đà Nẵng |
| `do.thi.phuong@abc-tech.vn` | `Staff@2024` | staff | Hà Nội (HQ) |
| `vu.van.giang@abc-tech.vn` | `Staff@2024` | staff | TP.HCM |

### Fake org/branch IDs

The seeder uses fixed UUIDs for consistency across runs:

```php
const ORG_ID     = '01936a00-0000-7000-0000-000000000001';
const BRANCH_HN  = '01936a00-0000-7000-0000-000000000010';
const BRANCH_HCM = '01936a00-0000-7000-0000-000000000011';
const BRANCH_DN  = '01936a00-0000-7000-0000-000000000012';
```

These are stored in `console_organization_id` / `console_branch_id` fields on users and branches.

---

## sso:sync-from-console (Console Mode)

Bulk imports users and branches from Omnify Console via service-to-service API.

> **Production note:** The service calls Console via HTTPS API — never by direct DB access. In production, users auto-sync on first SSO login. This command is for pre-populating data in dev/staging.

### Requirements

- `OMNIFY_AUTH_MODE=console`
- `SSO_SERVICE_SECRET` set in `.env`
- Console must expose `/api/sso/service/users` and `/api/sso/service/branches` endpoints

### Usage

```bash
# Sync branches + users for an organization
php artisan sso:sync-from-console --organization=abc-tech

# Options
php artisan sso:sync-from-console --organization=abc-tech --dry-run        # preview only
php artisan sso:sync-from-console --organization=abc-tech --users-only
php artisan sso:sync-from-console --organization=abc-tech --branches-only
php artisan sso:sync-from-console --organization=abc-tech --per-page=50    # default: 100
```

### What it does

**Branches** — calls `GET /api/sso/service/branches?organization={slug}`

- Creates or updates branches by `console_branch_id`
- Restores soft-deleted branches if they reappear

**Users** — calls `GET /api/sso/service/users?organization={slug}&page=N&per_page=N`

- Creates or updates users by `console_user_id`
- Paginated automatically
- **No password set** — users log in via SSO and get tokens on first login

### Console API authentication

Requests are authenticated with two headers:

```
X-Service-Slug:   {SSO_SERVICE_SLUG}
X-Service-Secret: {SSO_SERVICE_SECRET}
```

---

## Custom Seeder Pattern

To seed app-specific permissions on top of the package defaults:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Omnify\Core\Models\Permission;
use Omnify\Core\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Run package seeder for users/roles/branches
        $this->call(\Omnify\Core\Database\Seeders\SsoStandaloneSeeder::class);

        // 2. Add app-specific permissions
        $permissions = [
            ['slug' => 'orders.view',   'name' => 'View Orders',   'group' => 'orders'],
            ['slug' => 'orders.create', 'name' => 'Create Orders', 'group' => 'orders'],
            ['slug' => 'orders.delete', 'name' => 'Delete Orders', 'group' => 'orders'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['slug' => $perm['slug']], $perm);
        }

        // 3. Assign new permissions to roles
        $admin = Role::where('slug', 'admin')->first();
        $admin?->permissions()->syncWithoutDetaching(
            Permission::whereIn('slug', ['orders.view', 'orders.create', 'orders.delete'])->pluck('id')
        );

        $manager = Role::where('slug', 'manager')->first();
        $manager?->permissions()->syncWithoutDetaching(
            Permission::whereIn('slug', ['orders.view', 'orders.create'])->pluck('id')
        );
    }
}
```

---

## AssignsRoles Trait

Available in the `Concerns` namespace for use in your own seeders:

```php
use Omnify\Core\Database\Seeders\Concerns\AssignsRoles;

class MySeeder extends Seeder
{
    use AssignsRoles;

    public function run(): void
    {
        // Assign by email + role slug
        $this->assignRoleToUserByEmail(
            'admin@example.com',
            'admin',
            $organizationId,  // console_organization_id (UUID)
            $branchId         // console_branch_id (UUID), null = org-wide
        );

        // Remove all roles for a user in a scope
        $this->removeUserRolesInScope($user, $organizationId, $branchId);
    }
}
```

Scope rules:

| `$organizationId` | `$branchId` | Scope |
|-------------------|-------------|-------|
| `null` | `null` | Global (any org/branch) |
| `uuid` | `null` | Org-wide |
| `uuid` | `uuid` | Branch-specific |
