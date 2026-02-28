# Authorization Guide

## Overview

The SSO Client provides a flexible Role-Based Access Control (RBAC) system with **Branch-Level Permissions** (Scoped Role Assignments), following industry standards from NIST RBAC, WorkOS, Salesforce, and Permit.io.

**Key Features:**
- **Roles** - Global role templates with hierarchical levels
- **Scoped Role Assignments** - Roles assigned per org/branch context
- **Permissions** - Granular permissions assigned to roles
- **Team Permissions** - Organization-level permissions via Console

---

## Kiến trúc: Console vs Service

> **Console** = Authentication + Subscription only
> **Service** = Database riêng, Roles/Permissions riêng, hoàn toàn độc lập

### Console làm gì?

| Chức năng | Mô tả |
|-----------|-------|
| **Authentication** | Login, logout, JWT token |
| **Subscription** | Plans, pricing, billing |
| **Access Control** | User được dùng service nào |

**Console KHÔNG quản lý:** Service data, roles, permissions, business logic

### Service làm gì?

| Chức năng | Mô tả |
|-----------|-------|
| **Database riêng** | Không liên quan đến Console |
| **Users (local)** | Lưu `console_user_id` để link với Console |
| **Roles** | Tự định nghĩa (admin, manager, staff...) |
| **Permissions** | Tự định nghĩa (`orders.create`, `reports.view`...) |
| **Business Data** | Orders, products, customers... |

### Tại sao thiết kế như vậy?

| Lý do | Giải thích |
|-------|------------|
| **Database độc lập** | Service scale/backup/migrate độc lập |
| **No single point of failure** | Console down ≠ Service down |
| **Domain-specific** | Mỗi service có roles phù hợp với domain |
| **Performance** | Authorization check local, không network latency |
| **Data isolation** | Service A không access được data Service B |

---

## Architecture: Scoped Role Assignments

> "Every authorization decision must be tenant-aware. You don't just check 'is user an admin?', you check 'is user an admin **in this tenant**?'" — WorkOS

### Scope Hierarchy

```
┌─────────────────────────────────────────────────────┐
│                   GLOBAL SCOPE                       │
│         organization_id = null, branch_id = null             │
│                                                      │
│         System Admin - Can access EVERYTHING         │
└───────────────────────┬─────────────────────────────┘
                        │
        ┌───────────────┼───────────────┐
        ▼               ▼               ▼
┌───────────────┐ ┌───────────────┐ ┌───────────────┐
│   ORG SCOPE   │ │   ORG SCOPE   │ │   ORG SCOPE   │
│   organization_id = A  │ │   organization_id = B  │ │   organization_id = C  │
│ branch_id=null│ │ branch_id=null│ │ branch_id=null│
│               │ │               │ │               │
│  Org Manager  │ │  Org Manager  │ │  Org Manager  │
│  All branches │ │  All branches │ │  All branches │
└───────┬───────┘ └───────────────┘ └───────────────┘
        │
┌───────┴───────────────────┐
▼                           ▼
┌───────────────┐   ┌───────────────┐
│ BRANCH SCOPE  │   │ BRANCH SCOPE  │
│  organization_id = A   │   │  organization_id = A   │
│ branch_id = 1 │   │ branch_id = 2 │
│               │   │               │
│  Tokyo Staff  │   │  Osaka Staff  │
│  Only Tokyo   │   │  Only Osaka   │
└───────────────┘   └───────────────┘
```

### Example: Same User, Different Roles

```php
$user = User::find($userId);
$adminRole = Role::where('slug', 'admin')->first();
$staffRole = Role::where('slug', 'staff')->first();

// User C is Admin at Tokyo, but Staff at Osaka
$user->assignRole($adminRole, $organizationId, $tokyoBranchId);
$user->assignRole($staffRole, $organizationId, $osakaBranchId);

// When user accesses Tokyo branch → has Admin permissions
// When user accesses Osaka branch → has Staff permissions only
```

---

## Models

### Role (Global Template)

Roles are **global templates** - they are defined once and reused across all orgs/branches.

```php
use Omnify\Core\Models\Role;

// Create a role (global template)
$role = Role::create([
    'name' => 'Administrator',
    'slug' => 'admin',
    'description' => 'Full administrative access',
    'level' => 100,
]);

// Check role's permissions
$role->hasPermission('users.create');
$role->hasAnyPermission(['users.create', 'users.update']);
$role->hasAllPermissions(['users.create', 'users.update']);

// Get all permissions
$permissions = $role->permissions;
```

### Permission

```php
use Omnify\Core\Models\Permission;

// Create a permission
$permission = Permission::create([
    'name' => 'Create Users',
    'slug' => 'users.create',
    'group' => 'users',
]);

// Get roles with this permission
$roles = $permission->roles;
```

### Role-Permission Pivot

```php
// Assign permission to role
$role->permissions()->attach($permission->id);

// Sync multiple permissions
$role->permissions()->sync([$perm1->id, $perm2->id, $perm3->id]);

// Using RoleAdminController API
PUT /api/admin/sso/roles/{role}/permissions
{
    "permissions": ["users.create", "users.update", "uuid-of-permission"]
}
```

---

## User Role Assignment (Scoped)

### Assign Roles with Scope

```php
$user = User::find($userId);
$adminRole = Role::where('slug', 'admin')->first();
$managerRole = Role::where('slug', 'manager')->first();
$staffRole = Role::where('slug', 'staff')->first();

// Global admin (can do everything everywhere)
$user->assignRole($adminRole);
// → organization_id=null, branch_id=null

// Org-wide manager (all branches in org)
$user->assignRole($managerRole, $organizationId);
// → organization_id=X, branch_id=null

// Branch-specific staff (only at Tokyo branch)
$user->assignRole($staffRole, $organizationId, $tokyoBranchId);
// → organization_id=X, branch_id=tokyo
```

### Remove Roles

```php
// Remove global role
$user->removeRole($adminRole);

// Remove org-wide role
$user->removeRole($managerRole, $organizationId);

// Remove branch-specific role
$user->removeRole($staffRole, $organizationId, $tokyoBranchId);
```

### Get Roles for Context

```php
// Get roles for branch context
// Returns: global + org-wide + branch-specific roles
$roles = $user->getRolesForContext($organizationId, $branchId);

// Get roles for org context only
// Returns: global + org-wide roles
$roles = $user->getRolesForContext($organizationId);

// Get global roles only
$roles = $user->getRolesForContext();

// Get all role assignments with scope info
$assignments = $user->roles()->withPivot(['console_organization_id', 'console_branch_id'])->get();
foreach ($assignments as $role) {
    echo "{$role->name}: org={$role->pivot->console_organization_id}, branch={$role->pivot->console_branch_id}";
}
```

---

## Permission Checking

### Basic Permission Checks

```php
$user = auth()->user();

// With explicit context
$user->hasPermission('users.create', $organizationId, $branchId);
$user->hasAnyPermission(['users.create', 'users.update'], $organizationId, $branchId);
$user->hasAllPermissions(['users.create', 'users.update'], $organizationId, $branchId);

// With session context (set by middleware)
$user->hasPermission('users.create');
$user->hasAnyPermission(['users.create', 'users.update']);
$user->hasAllPermissions(['users.create', 'users.update']);
```

### Role Checks

```php
// Check if user has specific role in context
if ($user->hasRoleInContext('admin', $organizationId, $branchId)) {
    // User is admin at this branch
}

// Get highest role level in context
$level = $user->getHighestRoleLevelInContext($organizationId, $branchId);
if ($level >= 50) {  // Manager level or above
    // Allow management actions
}
```

### Permission Resolution Flow

When checking permissions, the system aggregates from all applicable scopes:

```
API Request with X-Organization-Id: A, X-Branch-Id: 1
         │
         ▼
┌─────────────────────────────────────────┐
│ Get User's Role Assignments             │
├─────────────────────────────────────────┤
│ 1. Global roles (org=null, branch=null) │
│ 2. Org-wide roles (org=A, branch=null)  │
│ 3. Branch roles (org=A, branch=1)       │
└─────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────┐
│ Aggregate Permissions from All Roles    │
│ + Team Permissions (unchanged)          │
└─────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────┐
│ Final Permission Set                    │
│ ['orders.create', 'orders.view', ...]   │
└─────────────────────────────────────────┘
```

---

## Laravel Gates Integration

The package automatically registers gates for all permissions:

```php
// In controllers
if (Gate::allows('users.create')) {
    // Authorized
}

if (Gate::denies('users.delete')) {
    abort(403);
}

// Using authorize helper
$this->authorize('users.update', $user);
```

### Blade Directives

```blade
@can('users.create')
    <button>Create User</button>
@endcan

@cannot('users.delete')
    <p>You cannot delete users</p>
@endcannot

@canany(['users.create', 'users.update'])
    <button>Manage Users</button>
@endcanany
```

---

## Team Permissions (Unchanged)

Team permissions work the same as before, providing organization-level permissions via Console:

```php
use Omnify\Core\Models\Team;
use Omnify\Core\Models\TeamPermission;

// Create team
$team = Team::create([
    'name' => 'Engineering',
    'console_team_id' => 'uuid-string',  // Now UUID
    'console_organization_id' => 'uuid-string',   // Now UUID
]);

// Assign permission to team
TeamPermission::create([
    'console_team_id' => $team->console_team_id,
    'console_organization_id' => $team->console_organization_id,
    'permission_id' => $permission->id,
]);

// Check team permissions
$team->hasPermission('projects.create');
```

---

## Role Hierarchy

Roles have a `level` field for hierarchy:

| Role | Level | Description |
|------|-------|-------------|
| admin | 100 | Full access |
| manager | 50 | Management access |
| member | 10 | Basic access |

```php
// Create roles with levels
Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
Role::create(['name' => 'Manager', 'slug' => 'manager', 'level' => 50]);
Role::create(['name' => 'Member', 'slug' => 'member', 'level' => 10]);

// Check role level in context
$level = $user->getHighestRoleLevelInContext($organizationId, $branchId);
```

---

## Middleware

### Permission Check Middleware

```php
// routes/api.php
Route::middleware(['sso.auth', 'sso.organization'])->group(function () {

    // Permission check (considers branch context from X-Branch-Id header)
    Route::middleware('sso.permission:orders.create')->group(function () {
        Route::post('/orders', [OrderController::class, 'store']);
    });

    // Role level check (considers branch context)
    Route::middleware('sso.role:admin')->group(function () {
        Route::delete('/orders/{order}', [OrderController::class, 'destroy']);
    });

    // Multiple permissions (OR logic)
    Route::middleware('sso.permission:orders.create|orders.update')->group(function () {
        // User needs either permission
    });
});
```

### Request Headers

| Header | Required | Description |
|--------|----------|-------------|
| `Authorization` | Yes | Bearer token |
| `X-Organization-Id` | Yes | Organization slug |
| `X-Branch-Id` | No | Branch UUID (for branch-specific operations) |

---

## Admin API Endpoints

### Roles CRUD

```
GET    /api/admin/sso/roles              # List roles
POST   /api/admin/sso/roles              # Create role
GET    /api/admin/sso/roles/{id}         # Get role
PUT    /api/admin/sso/roles/{id}         # Update role
DELETE /api/admin/sso/roles/{id}         # Delete role (not system roles)

GET    /api/admin/sso/roles/{id}/permissions     # Get role permissions
PUT    /api/admin/sso/roles/{id}/permissions     # Sync role permissions
```

### Permissions CRUD

```
GET    /api/admin/sso/permissions        # List permissions
POST   /api/admin/sso/permissions        # Create permission
GET    /api/admin/sso/permissions/{id}   # Get permission
PUT    /api/admin/sso/permissions/{id}   # Update permission
DELETE /api/admin/sso/permissions/{id}   # Delete permission

GET    /api/admin/sso/permission-matrix  # Get role-permission matrix
```

### User Role Assignments (NEW - Branch-Level)

```
GET    /api/admin/sso/users/{userId}/roles          # List user's role assignments
POST   /api/admin/sso/users/{userId}/roles          # Assign role with scope
PUT    /api/admin/sso/users/{userId}/roles/sync     # Sync roles in scope
DELETE /api/admin/sso/users/{userId}/roles/{roleId} # Remove role assignment
```

**Request Body (POST):**

```json
{
  "role_id": "uuid-of-role",
  "console_organization_id": "uuid-of-org",        // null = global
  "console_branch_id": "uuid-of-branch"   // null = org-wide
}
```

**Scope Examples:**

```json
// Global Admin
{ "role_id": "admin-uuid", "console_organization_id": null, "console_branch_id": null }

// Org-wide Manager
{ "role_id": "manager-uuid", "console_organization_id": "org-uuid", "console_branch_id": null }

// Branch-specific Staff
{ "role_id": "staff-uuid", "console_organization_id": "org-uuid", "console_branch_id": "branch-uuid" }
```

---

## Database Schema

### role_user Pivot Table

```sql
CREATE TABLE role_user (
    id UUID PRIMARY KEY,
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    role_id UUID REFERENCES roles(id) ON DELETE CASCADE,
    console_organization_id VARCHAR(36) NULL,      -- null = global
    console_branch_id VARCHAR(36) NULL,   -- null = org-wide
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    INDEX (user_id, console_organization_id, console_branch_id),
    INDEX (role_id, user_id)
);
```

### Example Data

| user_id | role_id | console_organization_id | console_branch_id | Meaning |
|---------|---------|----------------|-------------------|---------|
| user-A | admin | null | null | Global Admin |
| user-B | manager | org-X | null | Org-wide Manager |
| user-C | admin | org-X | branch-tokyo | Tokyo Admin |
| user-C | staff | org-X | branch-osaka | Osaka Staff |

---

## Complete Example

### Setup RBAC System

```php
// 1. Create permissions
$permissions = [
    Permission::create(['name' => 'View Dashboard', 'slug' => 'dashboard.view', 'group' => 'dashboard']),
    Permission::create(['name' => 'Create Orders', 'slug' => 'orders.create', 'group' => 'orders']),
    Permission::create(['name' => 'Manage Users', 'slug' => 'users.manage', 'group' => 'users']),
];

// 2. Create roles (global templates)
$adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin', 'level' => 100]);
$managerRole = Role::create(['name' => 'Manager', 'slug' => 'manager', 'level' => 50]);
$staffRole = Role::create(['name' => 'Staff', 'slug' => 'staff', 'level' => 10]);

// 3. Assign permissions to roles
$adminRole->permissions()->sync($permissions->pluck('id'));       // Admin gets all
$managerRole->permissions()->sync([$permissions[0]->id, $permissions[1]->id]); // Dashboard + Orders
$staffRole->permissions()->sync([$permissions[0]->id]);           // Dashboard only

// 4. Assign roles to users with scope
$userA = User::find('user-A-uuid');
$userB = User::find('user-B-uuid');
$userC = User::find('user-C-uuid');

// User A: Global Admin
$userA->assignRole($adminRole);

// User B: Manager for all branches in org
$userB->assignRole($managerRole, $organizationId);

// User C: Admin at Tokyo, Staff at Osaka
$userC->assignRole($adminRole, $organizationId, $tokyoBranchId);
$userC->assignRole($staffRole, $organizationId, $osakaBranchId);

// 5. Check permissions
$userC->hasPermission('users.manage', $organizationId, $tokyoBranchId);  // true (Admin)
$userC->hasPermission('users.manage', $organizationId, $osakaBranchId);  // false (Staff)
```

---

## Migration from Old System

If you're upgrading from the old single `role_id` system:

### Backward Compatibility

- Existing role assignments have `console_organization_id = null` and `console_branch_id = null`
- They are treated as **global roles**
- Permission checks without branch context work as before

### Migration Script

```php
// Old: User has single role_id
// New: Convert to role_user pivot

User::whereNotNull('role_id')->each(function ($user) {
    // Existing roles become global
    $user->roles()->attach($user->role_id, [
        'console_organization_id' => null,
        'console_branch_id' => null,
    ]);
});
```

---

## References

- [NIST RBAC Standard (ANSI/INCITS 359-2012)](https://csrc.nist.gov/Projects/Role-Based-Access-Control)
- [WorkOS: Multi-Tenant RBAC Design](https://workos.com/blog/how-to-design-multi-tenant-rbac-saas)
- [Aserto: Multi-Tenant RBAC](https://www.aserto.com/blog/authorization-101-multi-tenant-rbac)
- [Permit.io: Multi-Tenant Authorization](https://www.permit.io/blog/best-practices-for-multi-tenant-authorization)
