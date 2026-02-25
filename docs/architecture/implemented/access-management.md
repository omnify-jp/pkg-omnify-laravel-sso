# Access Management Implementation (Option A - AWS IAM Style)

## Overview

Implement AWS IAM-style access management with clear scope hierarchy:
- **Global** (service-wide) - `console_organization_id = NULL, console_branch_id = NULL`
- **Organization-wide** - `console_organization_id = X, console_branch_id = NULL`
- **Branch-specific** - `console_organization_id = X, console_branch_id = Y`

---

## Phase 1: Add Organization to User Cache

### 1.1 Database Changes
- [x] Update `User.yaml` schema - add `console_organization_id` field
- [x] Run `npx omnify generate` to generate migration
- [x] Run `php artisan migrate`
- [x] Create `OMNIFY_FEATURE_REQUESTS.md` for Omnify issues (Uuid type, index support)

### 1.2 Backend Logic Updates
- [x] Update `SsoCallbackController` - save org_id when user logs in
- [x] Update `UserAdminController::index()` - add filter by org_id  
- [x] Update `UserResource` - include org info in response
- [ ] Update `OrgAccessService` - sync user's org when accessing (optional)

### 1.3 API Changes
- [x] GET /api/admin/sso/users - add `filter[org_id]` parameter
- [x] Response should include user's organization info

### 1.4 Frontend Updates
- [x] Update `users.tsx` - add org filter dropdown
- [x] Update user table - show organization column
- [x] Add translation keys for organization filter
- [ ] Update service types if needed (deferred - using local type extension)

### 1.5 Tests
- [ ] Unit tests for User model with org
- [ ] Feature tests for filtered user list API

---

## Phase 2: Fix Assign Role Modal

### 2.1 UI Changes
- [x] Add 3 scope options: Global / Organization / Branch
- [x] When "Organization" selected - show org dropdown (all orgs)
- [x] When "Branch" selected - show org dropdown + branch multi-select
- [x] Preview text showing what will be assigned

### 2.2 Backend Changes
- [x] Backend already supports null org/branch (existing)
- [x] Validation handled by existing unique constraint

### 2.3 Tests
- [ ] Test assigning global roles
- [ ] Test assigning org-wide roles
- [ ] Test assigning branch-specific roles

---

## Phase 3: Roles Page Redesign

### 3.1 UI Changes
- [x] Scope filter dropdown (All / Global / Organization)
- [x] Organization column showing org name or "Global"
- [x] Create role modal with scope selection

### 3.2 Backend Changes
- [x] GET /api/admin/sso/roles - add `scope` and `org_id` filters
- [x] Create role with `scope` parameter for global/org roles
- [x] Include organization info in role response

### 3.3 Tests
- [ ] Test listing global vs org roles
- [ ] Test creating org-specific roles

---

## Phase 4: User Detail Modal Improvements

### 4.1 UI Changes
- [x] Show user's primary organization in User Info card
- [x] Role assignments sorted by scope (Global > Org > Branch)
- [x] Show org/branch names instead of just IDs
- [x] Improved scope display with org context

### 4.2 Backend Changes
- [x] Include org/branch names in permissions breakdown API
- [x] Include user's primary organization in response
- [x] Sort role assignments by scope priority

---

## Phase 5: Users Page Improvements ✅

### 5.1 UI Changes
- [x] Add Organization column to users table
- [x] Add Organization filter dropdown
- [x] Show "(Global)" for users without org

### 5.2 Backend
- [x] UserResource includes organization info
- [x] filter[org_id] filter in UserAdminController

---

## Database Schema Reference

```
users
├── id (uuid, PK)
├── console_user_id (uuid, UNIQUE)
├── console_organization_id (uuid, nullable) ◄── NEW: Primary org
├── name, email
└── tokens...

roles
├── id (uuid, PK)
├── console_organization_id (uuid, nullable) ◄── NULL = Global role
├── name, slug, level
└── ...

role_user
├── id (auto, PK)
├── user_id (FK)
├── role_id (FK)
├── console_organization_id (nullable) ◄── WHERE role applies
├── console_branch_id (nullable) ◄── WHERE role applies
└── UNIQUE(user, role, org, branch)
```

---

## Data Seeding & Auto-Setup

### 6.1 Seeders

```bash
# Run all seeders (creates roles + permissions)
php artisan db:seed

# Or run specific seeders
php artisan db:seed --class=SsoRolesSeeder
php artisan db:seed --class=AppPermissionsSeeder

# Using sync-permissions command (alternative)
php artisan sso:sync-permissions
```

### 6.2 What Gets Created

**SsoRolesSeeder** (from SSO package):
- 5 Global Roles: admin (100), manager (50), supervisor (30), member (10), viewer (5)
- 21 Base Permissions: service-admin.*, dashboard.*

**AppPermissionsSeeder** (app-specific):
- 16 App Permissions: timesheet.*, project.*, report.*, settings.*
- Auto-assigns to existing roles based on level

### 6.3 Trigger Points for Auto-Setup

| Trigger                         | What Happens                          | Where                                           |
| ------------------------------- | ------------------------------------- | ----------------------------------------------- |
| **App Install**                 | Create global roles & permissions     | `php artisan db:seed` or `sso:sync-permissions` |
| **Org Cached (first time)**     | Fire `OrganizationCreated` event | `Organization` model `created` event       |
| **User Login**                  | Cache user + org data                 | `SsoCallbackController::callback()`             |
| **API Request with org header** | Cache org if not exists               | `SsoOrganizationAccess` middleware              |

### 6.4 OrganizationCreated Event

When a new organization is cached, the app can:
- Create org-specific roles
- Set up default settings
- Initialize org data

```php
// app/Listeners/SetupOrganizationDefaults.php
class SetupOrganizationDefaults
{
    public function handle(OrganizationCreated $event): void
    {
        $org = $event->organization;
        
        // Create org-specific roles
        Role::firstOrCreate([
            'slug' => 'org-admin',
            'console_organization_id' => $org->console_organization_id,
        ], [
            'name' => 'Organization Admin',
            'level' => 90,
        ]);
    }
}
```

### 6.5 Flow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                     APP INSTALLATION                         │
├─────────────────────────────────────────────────────────────┤
│  php artisan migrate                                         │
│  php artisan db:seed                                         │
│     └── SsoRolesSeeder → Global Roles + Base Permissions    │
│     └── AppPermissionsSeeder → App Permissions + Assign     │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                     USER LOGIN (SSO)                         │
├─────────────────────────────────────────────────────────────┤
│  1. Console redirects with JWT token                         │
│  2. SsoCallbackController::callback()                        │
│     └── User::updateOrCreate() ─ Cache user data       │
│     └── Organization::updateOrCreate() ─ If new:       │
│         └── Fire OrganizationCreated event             │
│              └── SetupOrganizationDefaults listener         │
│                   └── Create org-specific roles (optional)  │
│  3. Create Sanctum token for session                         │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                     API REQUEST                              │
├─────────────────────────────────────────────────────────────┤
│  Request with X-Organization-Id header                                │
│  └── SsoOrganizationAccess middleware                       │
│       └── Organization::updateOrCreate()               │
│            └── Fire event if new org                        │
└─────────────────────────────────────────────────────────────┘
```

---

## Progress Tracking

| Phase   | Status     | Notes                                               |
| ------- | ---------- | --------------------------------------------------- |
| Phase 1 | ✅ Complete | Add org to users - DB, Backend, Frontend done |
| Phase 2 | ✅ Complete | Assign Role Modal - 3 scopes (Global/Org/Branch)    |
| Phase 3 | ✅ Complete | Roles Page - scope filter, org column, create modal |
| Phase 4 | ✅ Complete | User Detail - org info, sorted roles, names display |
| Phase 5 | ✅ Complete | Users Page - org column, filter, global tag         |
| Phase 6 | ✅ Complete | Seeders + Auto-Setup events                         |
