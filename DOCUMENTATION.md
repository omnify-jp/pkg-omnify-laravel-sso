# Omnify SSO Client Laravel Package

## Overview

**Package Name:** `omnifyjp/omnify-client-laravel-sso`

**Purpose:** Laravel package cung cấp Single Sign-On (SSO) integration với Omnify Console, bao gồm Role-Based Access Control (RBAC), team permissions, **Branch-Level Permissions**, và các tính năng bảo mật toàn diện.

**Requirements:**
- PHP 8.2+
- Laravel 11.0+ hoặc 12.0+

---

## IMPORTANT: Kiến trúc hệ thống

> **Console** chỉ làm nhiệm vụ **xác thực (authentication)** và quản lý **subscription/access**.
> **Mỗi Service** có **database riêng**, hoàn toàn độc lập với Console.

### Phân chia trách nhiệm

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              OMNIFY CONSOLE                                  │
│                    (Authentication & Subscription Provider)                  │
│                                                                              │
│   CHỈ làm các việc sau:                                                     │
│   ┌─────────────────────────────────────────────────────────────────────┐   │
│   │ 1. AUTHENTICATION (Xác thực)                                        │   │
│   │    • User login/logout                                              │   │
│   │    • JWT token issuance & validation                                │   │
│   │    • Password management                                            │   │
│   │                                                                     │   │
│   │ 2. SUBSCRIPTION & ACCESS CONTROL                                    │   │
│   │    • Plans & Pricing                                                │   │
│   │    • User subscriptions                                             │   │
│   │    • Cho phép user truy cập service nào                             │   │
│   │    • Organization/Team structure (for access control)               │   │
│   └─────────────────────────────────────────────────────────────────────┘   │
│                                                                              │
│   KHÔNG quản lý:                                                            │
│   ✗ Service data                                                            │
│   ✗ Service roles/permissions                                               │
│   ✗ Business logic của services                                             │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                         JWT Token (authentication only)
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                         SERVICE (với SSO Client Package)                     │
│                            (Database riêng, độc lập)                         │
│                                                                              │
│   ┌─────────────────────────────────────────────────────────────────────┐   │
│   │ DATABASE RIÊNG - Không liên quan đến Console                        │   │
│   │                                                                     │   │
│   │ • users (local user records, linked via console_user_id)            │   │
│   │ • roles (service-specific roles)                                    │   │
│   │ • permissions (service-specific permissions)                        │   │
│   │ • role_user (role assignments với org/branch scope)                 │   │
│   │ • teams, branches (local references nếu cần)                        │   │
│   │ • [tất cả business data khác của service]                           │   │
│   └─────────────────────────────────────────────────────────────────────┘   │
│                                                                              │
│   Service TỰ QUẢN LÝ:                                                       │
│   ✓ Roles & Permissions                                                     │
│   ✓ Authorization logic                                                     │
│   ✓ Business data                                                           │
│   ✓ User preferences/settings trong service                                 │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Dữ liệu được quản lý ở đâu?

| Data                       | Console | Service | Ghi chú                       |
| -------------------------- | ------- | ------- | ----------------------------- |
| **User Authentication**    | ✅       | ❌       | Login, password, JWT          |
| **Plans & Subscriptions**  | ✅       | ❌       | Pricing, billing              |
| **Service Access Control** | ✅       | ❌       | User được dùng service nào    |
| **Users (local)**          | ❌       | ✅       | Lưu `console_user_id` để link |
| **Roles**                  | ❌       | ✅       | Service tự định nghĩa         |
| **Permissions**            | ❌       | ✅       | Service tự định nghĩa         |
| **Role Assignments**       | ❌       | ✅       | Scoped by org/branch          |
| **Business Data**          | ❌       | ✅       | Orders, products, etc.        |

### Authentication Flow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         AUTHENTICATION FLOW                                  │
└─────────────────────────────────────────────────────────────────────────────┘

1. User truy cập Service
   └─► Service redirect đến Console login

2. User đăng nhập tại Console
   └─► Console xác thực credentials
   └─► Console kiểm tra user có quyền truy cập service không (subscription)
   └─► Console issue JWT token

3. Redirect về Service với JWT
   └─► Service validate JWT signature (via JWKS)
   └─► Service tạo/update local user record (console_user_id)
   └─► Service tạo session

4. Subsequent requests
   └─► Service check local roles/permissions
   └─► KHÔNG cần gọi Console API
```

### Tại sao thiết kế như vậy?

| Lý do                             | Giải thích                                          |
| --------------------------------- | --------------------------------------------------- |
| **Database độc lập**              | Service có thể scale, backup, migrate độc lập       |
| **Không single point of failure** | Console down ≠ Service down (sau khi đã login)      |
| **Domain-specific**               | Mỗi service có roles/permissions phù hợp với domain |
| **Performance**                   | Authorization check local, không network latency    |
| **Data isolation**                | Service A không thể access data của Service B       |
| **Simple Console**                | Console chỉ làm auth + subscription, không phình to |

---

## Key Features

| Feature                      | Description                                                     |
| ---------------------------- | --------------------------------------------------------------- |
| JWT-Based SSO                | Xác thực an toàn qua token exchange với Omnify Console          |
| UUID Primary Keys            | Tất cả models sử dụng UUID để tương thích với Console           |
| Role-Based Access Control    | Quản lý role và permission linh hoạt                            |
| **Branch-Level Permissions** | **NEW!** Scoped role assignments cho multi-branch organizations |
| Team Permissions             | Quản lý permission cấp team qua Console API                     |
| Minimal Schema               | Chỉ lưu Console references, fetch data từ Console API           |
| Security Features            | Open redirect protection, encrypted tokens, rate limiting       |
| Audit Logging                | SSO log channel riêng cho security events                       |
| Multi-language               | Hỗ trợ i18n (ja, en, vi)                                        |
| Omnify Schema-Driven         | Auto-generated models qua Omnify                                |

---

## Architecture

### ServiceInstance Model

```
Console (SSO Provider)          Your Service (SSO Client)
├─ Users (UUID)                 └─ users
├─ Organizations (UUID)            ├─ console_user_id (UUID)
├─ Teams (UUID)                    ├─ console_access_token (encrypted)
├─ Branches (UUID)                 ├─ console_refresh_token (encrypted)
└─ Service: "your-service"         │
   └─ ServiceInstance (per-org)   └─ teams, branches, role_permissions
      ├─ client_id                  (references to Console data)
      └─ client_secret
```

**Design Philosophy:** Package chỉ lưu Console reference IDs. Full user/team/branch data được fetch từ Console API khi cần, đảm bảo data consistency.

---

## Directory Structure

```
omnify-client-laravel-sso/
├── config/
│   └── sso-client.php              # Package configuration
├── database/
│   ├── factories/                  # Model factories for testing
│   │   ├── UserFactory.php
│   │   ├── RoleFactory.php
│   │   ├── PermissionFactory.php
│   │   ├── TeamFactory.php
│   │   ├── BranchFactory.php
│   │   ├── RolePermissionFactory.php
│   │   └── TeamPermissionFactory.php
│   ├── migrations/
│   │   └── 0001_01_01_000001_create_sso_tables.php
│   └── schemas/Sso/                # Omnify YAML schemas
│       ├── User.yaml
│       ├── Role.yaml
│       ├── Permission.yaml
│       ├── RolePermission.yaml
│       ├── Team.yaml
│       ├── Branch.yaml
│       └── TeamPermission.yaml
├── src/
│   ├── Cache/                      # Caching layer
│   │   ├── RolePermissionCache.php
│   │   ├── TeamPermissionCache.php
│   │   └── ConsoleTeamsCache.php
│   ├── Composer/
│   │   └── OmnifyDiscoveryPlugin.php
│   ├── Console/Commands/
│   │   ├── SsoInstallCommand.php
│   │   ├── SsoSyncPermissionsCommand.php
│   │   └── SsoCleanupOrphanTeamsCommand.php
│   ├── Exceptions/
│   │   ├── ConsoleApiException.php
│   │   ├── ConsoleAuthException.php
│   │   ├── ConsoleAccessDeniedException.php
│   │   ├── ConsoleNotFoundException.php
│   │   └── ConsoleServerException.php
│   ├── Facades/
│   │   └── SsoClient.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── SsoCallbackController.php
│   │   │   ├── SsoReadOnlyController.php
│   │   │   ├── SsoTokenController.php
│   │   │   ├── SsoBranchController.php
│   │   │   └── Admin/
│   │   │       ├── RoleAdminController.php
│   │   │       ├── PermissionAdminController.php
│   │   │       └── TeamPermissionAdminController.php
│   │   └── Middleware/
│   │       ├── SsoAuthenticate.php
│   │       ├── SsoOrganizationAccess.php
│   │       ├── SsoPermissionCheck.php
│   │       └── SsoRoleCheck.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Role.php
│   │   ├── Permission.php
│   │   ├── RolePermission.php
│   │   ├── Team.php
│   │   ├── TeamPermission.php
│   │   ├── Branch.php
│   │   ├── Traits/
│   │   │   ├── HasConsoleSso.php
│   │   │   └── HasTeamPermissions.php
│   │   └── OmnifyBase/             # Auto-generated
│   ├── Services/
│   │   ├── ConsoleApiService.php
│   │   ├── ConsoleTokenService.php
│   │   ├── JwksService.php
│   │   ├── JwtVerifier.php
│   │   └── OrgAccessService.php
│   ├── Support/
│   │   ├── RedirectUrlValidator.php
│   │   └── SsoLogger.php
│   └── Providers/
│       └── SsoClientServiceProvider.php
├── routes/
│   └── sso.php
└── tests/
```

---

## Database Schema

Tất cả tables sử dụng UUID primary keys để tương thích với Console.

### Tables

| Table              | Purpose                    | Key Fields                                                              |
| ------------------ | -------------------------- | ----------------------------------------------------------------------- |
| `users`            | SSO users                  | id (UUID), console_user_id, console_access_token, console_refresh_token |
| `roles`            | Local roles                | id (UUID), name, slug, level, description                               |
| `permissions`      | Local permissions          | id (UUID), name, slug, group                                            |
| `role_permissions` | Role-Permission pivot      | role_id, permission_id (composite PK)                                   |
| `role_user`        | User-Role pivot with scope | id (UUID), role_id, user_id, **console_organization_id**, **console_branch_id**  |
| `teams`            | Console team refs          | id (UUID), console_team_id, console_organization_id, name                        |
| `branches`         | Console branch refs        | id (UUID), console_branch_id, console_organization_id, code, name                |
| `team_permissions` | Team-level permissions     | id (UUID), console_team_id, console_organization_id, permission_id               |

> **Note:** `role_user` table now has `console_organization_id` and `console_branch_id` for scoped role assignments (Branch-Level Permissions).

### Entity Relationship Diagram

```
┌─────────┐       ┌──────────────────────────┐       ┌─────────────┐
│  User   │───M:M─│       role_user          │───M:M─│    Role     │
│  (UUID) │       │ + console_organization_id (scope) │       │   (UUID)    │
└─────────┘       │ + console_branch_id      │       └──────┬──────┘
     │            └──────────────────────────┘              │
     │                                                      │ M:M
     │                                                      │
     │            ┌──────────────────┐              ┌──────┴──────┐
     │            │ role_permissions │──────────────│ Permission  │
     │            │ (composite PK)   │              │   (UUID)    │
     │            └──────────────────┘              └──────┬──────┘
     │                                                     │
     │                                                     │ 1:M
     │                                                     │
┌────┴────┐       ┌──────────────────┐              ┌──────┴──────┐
│  Team   │───────│ team_permissions │──────────────│TeamPermission│
│  (UUID) │       │    (UUID)        │              │   (UUID)    │
└─────────┘       └──────────────────┘              └─────────────┘

┌─────────┐
│ Branch  │  (Console reference for branch-level permissions)
│  (UUID) │
└─────────┘
```

---

## Branch-Level Permissions (Scoped Role Assignments)

### Overview

Package này implement **Scoped Role Assignments** (Option B) theo industry standard được sử dụng bởi các enterprise SaaS platforms như Hubspot, Salesforce, và WorkOS.

**Tham khảo:**
- [NIST RBAC Standard (ANSI/INCITS 359-2012)](https://csrc.nist.gov/Projects/Role-Based-Access-Control)
- [WorkOS: Multi-Tenant RBAC Design](https://workos.com/blog/how-to-design-multi-tenant-rbac-saas)
- [Aserto: Multi-Tenant RBAC](https://www.aserto.com/blog/authorization-101-multi-tenant-rbac)
- [Permit.io: Multi-Tenant Authorization](https://www.permit.io/blog/best-practices-for-multi-tenant-authorization)

### Key Concept

> "Every authorization decision must be tenant-aware. You don't just check 'is user an admin?', you check 'is user an admin **in this tenant**?'"
> — WorkOS

Thay vì tạo multiple roles ("Admin (Tokyo)", "Admin (Osaka)"...), roles được define một lần (global templates) và **assignments** được scoped:

```
Role (template/definition) - GLOBAL
├─ "Admin" (level 100)
├─ "Manager" (level 50)
└─ "Staff" (level 10)

role_user (pivot) - SCOPED
├─ user=A, role=Admin, org=null, branch=null     → Global Admin
├─ user=B, role=Admin, org=X, branch=null        → Org-wide Admin (all branches)
├─ user=C, role=Admin, org=X, branch=tokyo       → Tokyo Admin only
├─ user=C, role=Staff, org=X, branch=osaka       → Same user: Staff at Osaka
└─ user=D, role=Manager, org=X, branch=null      → Manager everywhere in org X
```

### Scope Hierarchy

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              SCOPE HIERARCHY                                 │
└─────────────────────────────────────────────────────────────────────────────┘

                    ┌─────────────────────────┐
                    │      GLOBAL SCOPE       │
                    │   org_id = null         │
                    │   branch_id = null      │
                    │                         │
                    │   Example: System Admin │
                    │   Can access EVERYTHING │
                    └───────────┬─────────────┘
                                │
            ┌───────────────────┼───────────────────┐
            ▼                   ▼                   ▼
    ┌───────────────┐   ┌───────────────┐   ┌───────────────┐
    │   ORG SCOPE   │   │   ORG SCOPE   │   │   ORG SCOPE   │
    │   org_id = A  │   │   org_id = B  │   │   org_id = C  │
    │ branch_id=null│   │ branch_id=null│   │ branch_id=null│
    │               │   │               │   │               │
    │  Org Manager  │   │  Org Manager  │   │  Org Manager  │
    │  All branches │   │  All branches │   │  All branches │
    └───────┬───────┘   └───────────────┘   └───────────────┘
            │
    ┌───────┴───────────────────┐
    ▼                           ▼
┌───────────────┐       ┌───────────────┐
│ BRANCH SCOPE  │       │ BRANCH SCOPE  │
│  org_id = A   │       │  org_id = A   │
│ branch_id = 1 │       │ branch_id = 2 │
│               │       │               │
│  Tokyo Staff  │       │  Osaka Staff  │
│  Only Tokyo   │       │  Only Osaka   │
└───────────────┘       └───────────────┘
```

### Permission Resolution Flow

Khi kiểm tra permission, hệ thống aggregates permissions từ tất cả applicable roles:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        PERMISSION RESOLUTION FLOW                            │
└─────────────────────────────────────────────────────────────────────────────┘

                    ┌─────────────────┐
                    │  API Request    │
                    │  X-Organization-Id: A    │
                    │  X-Branch-Id: 1 │
                    └────────┬────────┘
                             │
                             ▼
              ┌──────────────────────────────┐
              │    Get User's Role           │
              │    Assignments               │
              └──────────────┬───────────────┘
                             │
         ┌───────────────────┼───────────────────┐
         ▼                   ▼                   ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│ Global Roles    │ │ Org-wide Roles  │ │ Branch Roles    │
│ org=null        │ │ org=A           │ │ org=A           │
│ branch=null     │ │ branch=null     │ │ branch=1        │
│                 │ │                 │ │                 │
│ [System Admin]  │ │ [Org Manager]   │ │ [Tokyo Staff]   │
└────────┬────────┘ └────────┬────────┘ └────────┬────────┘
         │                   │                   │
         └───────────────────┼───────────────────┘
                             │
                             ▼
              ┌──────────────────────────────┐
              │   Aggregate Permissions      │
              │   from all applicable roles  │
              │                              │
              │   + Team Permissions         │
              │   (unchanged from current)   │
              └──────────────┬───────────────┘
                             │
                             ▼
              ┌──────────────────────────────┐
              │   Final Permission Set       │
              │   ['orders.create',          │
              │    'orders.view',            │
              │    'reports.view', ...]      │
              └──────────────────────────────┘
```

### Database Schema

**role_user pivot table:**

```sql
CREATE TABLE role_user (
    id UUID PRIMARY KEY,
    role_id UUID REFERENCES roles(id) ON DELETE CASCADE,
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    console_organization_id VARCHAR(36) NULL,     -- null = global
    console_branch_id VARCHAR(36) NULL,  -- null = org-wide
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    INDEX (user_id, console_organization_id, console_branch_id),
    INDEX (role_id, user_id)
);
```

**Example Data:**

| id  | user_id | role_id | console_organization_id | console_branch_id | Meaning                  |
| --- | ------- | ------- | -------------- | ----------------- | ------------------------ |
| 1   | user-A  | admin   | null           | null              | Global Admin             |
| 2   | user-B  | manager | org-X          | null              | Org-wide Manager         |
| 3   | user-C  | admin   | org-X          | branch-tokyo      | Tokyo Admin              |
| 4   | user-C  | staff   | org-X          | branch-osaka      | Osaka Staff (same user!) |
| 5   | user-D  | staff   | org-X          | branch-tokyo      | Tokyo Staff              |

### API Usage

#### Headers

| Header              | Required | Description                                  |
| ------------------- | -------- | -------------------------------------------- |
| `Authorization`     | Yes      | Bearer token                                 |
| `X-Organization-Id` | Yes      | Organization slug                            |
| `X-Branch-Id`       | No       | Branch UUID (for branch-specific operations) |

#### Assign Role to User

```http
POST /api/admin/sso/users/{userId}/roles
Content-Type: application/json
Authorization: Bearer {token}
X-Organization-Id: my-org

{
  "role_id": "uuid-of-role",
  "console_organization_id": "uuid-of-org",        // null = global
  "console_branch_id": "uuid-of-branch"   // null = org-wide
}
```

**Scope Examples:**

```json
// Global Admin (can access everything)
{ "role_id": "admin-uuid", "console_organization_id": null, "console_branch_id": null }

// Org-wide Manager (all branches in org)
{ "role_id": "manager-uuid", "console_organization_id": "org-uuid", "console_branch_id": null }

// Branch-specific Staff (only Tokyo branch)
{ "role_id": "staff-uuid", "console_organization_id": "org-uuid", "console_branch_id": "tokyo-branch-uuid" }
```

#### List User's Role Assignments

```http
GET /api/admin/sso/users/{userId}/roles
Authorization: Bearer {token}
X-Organization-Id: my-org
```

**Response:**

```json
{
  "data": [
    {
      "id": "assignment-uuid",
      "role": {
        "id": "role-uuid",
        "name": "Admin",
        "slug": "admin",
        "level": 100
      },
      "console_organization_id": null,
      "console_branch_id": null,
      "scope": "global",
      "created_at": "2024-01-15T10:30:00.000000Z"
    },
    {
      "id": "assignment-uuid-2",
      "role": {
        "id": "role-uuid-2",
        "name": "Staff",
        "slug": "staff",
        "level": 10
      },
      "console_organization_id": "org-uuid",
      "console_branch_id": "branch-uuid",
      "scope": "branch",
      "created_at": "2024-01-15T10:35:00.000000Z"
    }
  ]
}
```

#### Remove Role Assignment

```http
DELETE /api/admin/sso/users/{userId}/roles/{roleId}
Content-Type: application/json
Authorization: Bearer {token}
X-Organization-Id: my-org

{
  "console_organization_id": "org-uuid",
  "console_branch_id": "branch-uuid"
}
```

#### Sync Roles in Scope

```http
PUT /api/admin/sso/users/{userId}/roles/sync
Content-Type: application/json
Authorization: Bearer {token}
X-Organization-Id: my-org

{
  "roles": ["manager", "viewer"],  // Role slugs or UUIDs
  "console_organization_id": "org-uuid",
  "console_branch_id": null        // Sync org-wide roles
}
```

### PHP Usage

#### Assign Roles with Scope

```php
$user = User::find($userId);
$adminRole = Role::where('slug', 'admin')->first();
$staffRole = Role::where('slug', 'staff')->first();

// Global admin (can do everything everywhere)
$user->assignRole($adminRole);  // org=null, branch=null

// Org-wide manager (all branches in org)
$user->assignRole($managerRole, $organizationId);  // branch=null

// Branch-specific staff (only Tokyo branch)
$user->assignRole($staffRole, $organizationId, $tokyoBranchId);

// Same user can have different roles in different branches
$user->assignRole('admin', $organizationId, $tokyoBranchId);
$user->assignRole('staff', $organizationId, $osakaBranchId);
```

#### Check Permissions

```php
// With explicit context
$user->hasPermission('orders.create', $organizationId, $branchId);

// With session context (set by middleware)
$user->hasPermission('orders.create');

// Check any/all permissions
$user->hasAnyPermission(['orders.create', 'orders.view'], $organizationId, $branchId);
$user->hasAllPermissions(['orders.create', 'orders.view'], $organizationId, $branchId);
```

#### Get Roles for Context

```php
// Get roles for branch context (includes global + org-wide + branch-specific)
$roles = $user->getRolesForContext($organizationId, $branchId);

// Get roles for org context (includes global + org-wide only)
$roles = $user->getRolesForContext($organizationId);

// Get global roles only
$roles = $user->getRolesForContext();

// Get all role assignments with scope info
$assignments = $user->getRoleAssignments();
foreach ($assignments as $role) {
    echo "{$role->name}: {$role->pivot->console_organization_id} / {$role->pivot->console_branch_id}";
}
```

#### Check Role Level

```php
// Get highest role level in context
$level = $user->getHighestRoleLevelInContext($organizationId, $branchId);

if ($level >= 50) {  // Manager level or above
    // Allow management actions
}

// Check specific role in context
if ($user->hasRoleInContext('admin', $organizationId, $branchId)) {
    // User is admin at this branch
}
```

### Middleware

Middleware tự động set context từ headers:

```php
// In routes/api.php
Route::middleware(['sso.auth', 'sso.organization'])->group(function () {
    // sso.organization middleware reads:
    // - X-Organization-Id header (required) → sets orgId in session/request
    // - X-Branch-Id header (optional) → sets branchId in session/request

    Route::middleware('sso.permission:orders.create')->group(function () {
        // Permission check considers branch context automatically
        Route::post('/orders', [OrderController::class, 'store']);
    });

    Route::middleware('sso.role:admin')->group(function () {
        // Role level check considers branch context automatically
        Route::delete('/orders/{order}', [OrderController::class, 'destroy']);
    });
});
```

### Backward Compatibility

Existing code tiếp tục hoạt động:

- Existing role assignments có `console_organization_id = null` và `console_branch_id = null`
- Chúng được coi là **global roles**
- Permission checks không có branch context vẫn work như trước

### Security Considerations

1. **Branch Validation**: Middleware validate branch thuộc về organization
2. **Scope Escalation Prevention**: Admin của branch không thể tạo org-wide assignments
3. **Header Validation**: X-Branch-Id phải là valid UUID
4. **Audit Logging**: All role assignment changes được logged

---

## Models & Relationships

### User Model

```php
class User extends UserBaseModel implements AuthenticatableContract, AuthorizableContract
{
    use HasConsoleSso, HasTeamPermissions, HasApiTokens, HasFactory;

    protected $hidden = [
        'console_access_token',
        'console_refresh_token',
    ];

    // Relations
    public function roles(): BelongsToMany;

    // Methods
    public function hasPermission(string $permission): bool;
    public function hasAnyPermission(array $permissions): bool;
    public function hasAllPermissions(array $permissions): bool;
}
```

### Role Model

```php
class Role extends RoleBaseModel
{
    // Relations
    public function permissions(): BelongsToMany;
    public function users(): BelongsToMany;

    // Methods
    public function hasPermission(string $permission): bool;
}
```

**Role Hierarchy Levels:**

| Role    | Level |
| ------- | ----- |
| admin   | 100   |
| manager | 50    |
| member  | 10    |

### Permission Model

```php
class Permission extends PermissionBaseModel
{
    // Fields: name, slug, group

    // Relations
    public function roles(): BelongsToMany;
    public function team_permissions(): HasMany;
}
```

### Team Model (Console Reference)

```php
class Team extends TeamBaseModel
{
    // Console Fields: console_team_id, console_organization_id

    // Relations
    public function permissions(): BelongsToMany;

    // Methods
    public function hasPermission(string $permission): bool;
}
```

### Branch Model (Console Reference)

```php
class Branch extends BranchBaseModel
{
    // Console Fields: console_branch_id, console_organization_id, code
    // No direct Laravel relations (reference only)
}
```

---

## Services

### ConsoleApiService

HTTP client cho Omnify Console API.

```php
class ConsoleApiService
{
    // Token Operations
    public function exchangeCode(string $code): array;
    public function refreshToken(string $refreshToken): array;
    public function revokeToken(string $refreshToken): void;

    // Access Operations
    public function getAccess(string $token, string $orgSlug): array;
    public function getOrganizations(string $token): array;
    public function getUserTeams(string $token, string $orgSlug): array;
    public function getUserBranches(string $token, string $orgSlug): array;

    // JWKS
    public function getJwks(): array;
}
```

### ConsoleTokenService

Token lifecycle management.

```php
class ConsoleTokenService
{
    public function getAccessToken(User $user): string;     // Auto-refresh if needed
    public function refreshIfNeeded(User $user): void;      // Refresh 5min before expiry
    public function refresh(User $user): void;              // Force refresh
    public function revokeTokens(User $user): void;         // Clear tokens on logout
    public function storeTokens(User $user, array $tokens): void;  // Store encrypted
}
```

### JwtVerifier

JWT signature verification.

```php
class JwtVerifier
{
    public function verify(string $token): array;  // Returns claims
    public function getClaims(string $token): array;  // Extract without verify
}
```

### JwksService

Fetch và cache JWKS.

```php
class JwksService
{
    public function getJwks(): array;            // 60-min cache
    public function getPublicKey(string $kid): string;  // Get by key ID
    public function clearCache(): void;
}
```

### OrgAccessService

Organization access checking.

```php
class OrgAccessService
{
    public function checkAccess(User $user, string $orgSlug): bool;  // Cached 300s
    public function getOrganizations(User $user): array;
    public function getUserTeams(User $user, string $orgSlug): array;  // Cached 300s
}
```

---

## Middleware

### SsoAuthenticate

```php
// Usage
Route::middleware('sso.auth')->group(function () {
    // Protected routes
});
// Returns 401 if not authenticated
```

### SsoOrganizationAccess

```php
// Usage - Requires X-Organization-Id header
Route::middleware('sso.organization')->group(function () {
    // Requires organization context
});
// Returns 400 if missing header, 403 if no access
```

### SsoPermissionCheck

```php
// Usage - OR logic (user needs ANY permission)
Route::middleware('sso.permission:users.create|users.update')->group(function () {
    // Protected routes
});
// Returns 403 if no permission
```

### SsoRoleCheck

```php
// Usage - Hierarchy-based check
Route::middleware('sso.role:admin')->group(function () {
    // Admin only routes
});
// Returns 403 if insufficient level
```

---

## Controllers

### SsoCallbackController

| Endpoint                     | Method | Description                            |
| ---------------------------- | ------ | -------------------------------------- |
| `/api/sso/callback`          | POST   | Handle SSO callback, exchange code     |
| `/api/sso/logout`            | POST   | Logout user, revoke tokens             |
| `/api/sso/user`              | GET    | Get authenticated user & organizations |
| `/api/sso/global-logout-url` | GET    | Get Console logout URL                 |

### SsoReadOnlyController

| Endpoint                     | Method | Description                       |
| ---------------------------- | ------ | --------------------------------- |
| `/api/sso/roles`             | GET    | List all roles                    |
| `/api/sso/roles/{id}`        | GET    | Get role with permissions         |
| `/api/sso/permissions`       | GET    | List permissions (with filtering) |
| `/api/sso/permission-matrix` | GET    | Role-permission matrix            |

### SsoTokenController

| Endpoint                        | Method | Description             |
| ------------------------------- | ------ | ----------------------- |
| `/api/sso/tokens`               | GET    | List user's API tokens  |
| `/api/sso/tokens/{tokenId}`     | DELETE | Revoke specific token   |
| `/api/sso/tokens/revoke-others` | POST   | Revoke all other tokens |

### SsoBranchController

| Endpoint            | Method | Description                         |
| ------------------- | ------ | ----------------------------------- |
| `/api/sso/branches` | GET    | Get user's branches in organization |

### Admin Controllers

Require middleware: `sso.auth`, `sso.organization`, `sso.role:admin`

**RoleAdminController:** CRUD for roles, sync role permissions

**PermissionAdminController:** CRUD for permissions

**TeamPermissionAdminController:** Manage team-level permissions

---

## API Routes

### Public Routes

```
POST   /api/sso/callback     # SSO callback
```

### Authenticated Routes (sso.auth)

```
POST   /api/sso/logout
GET    /api/sso/user
GET    /api/sso/global-logout-url
GET    /api/sso/tokens
DELETE /api/sso/tokens/{tokenId}
POST   /api/sso/tokens/revoke-others
GET    /api/sso/roles
GET    /api/sso/roles/{id}
GET    /api/sso/permissions
GET    /api/sso/permission-matrix
GET    /api/sso/branches
```

### Admin Routes (sso.auth + sso.organization + sso.role:admin)

```
# Roles
GET    /api/admin/sso/roles
POST   /api/admin/sso/roles
GET    /api/admin/sso/roles/{id}
PUT    /api/admin/sso/roles/{id}
DELETE /api/admin/sso/roles/{id}
GET    /api/admin/sso/roles/{role}/permissions
PUT    /api/admin/sso/roles/{role}/permissions

# Permissions
GET    /api/admin/sso/permissions
POST   /api/admin/sso/permissions
GET    /api/admin/sso/permissions/{id}
PUT    /api/admin/sso/permissions/{id}
DELETE /api/admin/sso/permissions/{id}
GET    /api/admin/sso/permission-matrix

# Team Permissions
GET    /api/admin/sso/team-permissions
POST   /api/admin/sso/team-permissions
DELETE /api/admin/sso/team-permissions/{id}

# User Role Assignments (NEW - Branch-Level Permissions)
GET    /api/admin/sso/users/{userId}/roles          # List user's role assignments
POST   /api/admin/sso/users/{userId}/roles          # Assign role with scope
PUT    /api/admin/sso/users/{userId}/roles/sync     # Sync roles in scope
DELETE /api/admin/sso/users/{userId}/roles/{roleId} # Remove role assignment
```

---

## SSO Authentication Flow

```
┌─────────────────────────────────────────────────────────────────────┐
│                        SSO AUTHENTICATION FLOW                       │
└─────────────────────────────────────────────────────────────────────┘

1. Frontend: Redirect to Console login
   → https://console.omnify.jp/sso/authorize?
     service={service_slug}&redirect_uri={callback_url}

2. Console: User authenticates

3. Console: Redirect back with code
   → https://your-app.com/sso/callback?code={authorization_code}

4. Frontend: Send code to API
   POST /api/sso/callback
   { "code": "authorization_code", "device_name": "optional" }

5. Backend: Exchange code for tokens
   → ConsoleApiService.exchangeCode(code)

6. Backend: Verify JWT signature
   → JwtVerifier.verify(access_token)

7. Backend: Extract user claims
   → { sub: user_id, email, name }

8. Backend: Create/update user
   → User.find_or_create(console_user_id)

9. Backend: Store Console tokens (encrypted)
   → ConsoleTokenService.storeTokens()

10. Backend: Return auth response
    {
      user: { id, console_user_id, email, name },
      organizations: [...],
      token: "api_token_for_mobile"  // optional
    }

11. Frontend: Store session (web) or API token (mobile)

12. Subsequent requests: Include auth header or session cookie
```

---

## Caching Strategy

| Cache               | Key Pattern                         | TTL   | Description               |
| ------------------- | ----------------------------------- | ----- | ------------------------- |
| Role Permissions    | `sso:role_permissions:{roleSlug}`   | 3600s | Permission slugs per role |
| Team Permissions    | `sso:team_permissions:{orgId}`      | 3600s | Permission slugs per team |
| User Teams          | `sso:user_teams:{userId}:{orgId}`   | 300s  | User's teams in org       |
| Organization Access | `sso:org_access:{userId}:{orgSlug}` | 300s  | Org access validation     |
| JWKS                | `sso:jwks`                          | 3600s | JSON Web Key Set          |

---

## Security Features

### 1. Token Encryption

```php
// HasConsoleSso trait - Tokens encrypted at rest
protected function getConsoleAccessTokenAttribute($value): ?string
{
    return $value ? Crypt::decryptString($value) : null;
}
```

### 2. Open Redirect Protection

```php
// RedirectUrlValidator validates against allowed_redirect_hosts config
// Enforces HTTPS if configured
```

### 3. JWT Verification

- RSA SHA-256 signature verification
- 5-minute clock skew tolerance
- Key ID (kid) header validation
- Automatic JWKS refresh on key rotation

### 4. Audit Logging

```php
sso_log('info', 'User authenticated', ['user_id' => $user->id]);
// Configurable channel and level
```

---

## Configuration

### Environment Variables

```env
# Required
SSO_CONSOLE_URL=https://console.omnify.jp
SSO_SERVICE_SLUG=your-service-slug

# Optional
SSO_CONSOLE_TIMEOUT=10
SSO_CONSOLE_RETRY=2
SSO_CALLBACK_URL=/sso/callback

# Cache TTLs (seconds)
SSO_JWKS_CACHE_TTL=3600
SSO_ORG_ACCESS_CACHE_TTL=300
SSO_USER_TEAMS_CACHE_TTL=300
SSO_ROLE_PERMISSIONS_CACHE_TTL=3600
SSO_TEAM_PERMISSIONS_CACHE_TTL=3600

# Security
SSO_ALLOWED_REDIRECT_HOSTS=your-app.com,*.your-domain.com
SSO_REQUIRE_HTTPS_REDIRECTS=true

# Logging
SSO_LOGGING_ENABLED=true
SSO_LOG_CHANNEL=sso
SSO_LOG_LEVEL=debug
```

---

## Artisan Commands

| Command                                | Description                                       |
| -------------------------------------- | ------------------------------------------------- |
| `php artisan sso:install`              | Setup wizard for initial installation             |
| `php artisan sso:sync-permissions`     | Sync permissions from config into database        |
| `php artisan sso:cleanup-orphan-teams` | Remove team permissions for deleted Console teams |

---

## Testing

### Available Factories

```php
User::factory()->create(['console_user_id' => Str::uuid()]);
Role::factory()->admin()->create();
Role::factory()->manager()->create();
Permission::factory()->create(['slug' => 'users.create', 'group' => 'users']);
Team::factory()->create(['console_team_id' => Str::uuid()]);
```

---

## Integration Example

### Frontend (SPA)

```typescript
// 1. Redirect to Console login
const loginUrl = `${CONSOLE_URL}/sso/authorize?` +
  `service=${SERVICE_SLUG}&` +
  `redirect_uri=${encodeURIComponent(window.location.origin + '/sso/callback')}`;
window.location.href = loginUrl;

// 2. Handle callback
const response = await fetch('/api/sso/callback', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ code }),
});
const { user, organizations } = await response.json();
```

### Backend (Laravel Routes)

```php
Route::middleware(['sso.auth', 'sso.organization'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);

    Route::middleware('sso.permission:users.create')->group(function () {
        Route::post('/users', [UserController::class, 'store']);
    });

    Route::middleware('sso.role:admin')->group(function () {
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
    });
});
```

---

## Exceptions

| Exception                      | HTTP Status | Description            |
| ------------------------------ | ----------- | ---------------------- |
| `ConsoleApiException`          | 500         | Generic API error      |
| `ConsoleAuthException`         | 401         | Authentication failure |
| `ConsoleAccessDeniedException` | 403         | Authorization failure  |
| `ConsoleNotFoundException`     | 404         | Resource not found     |
| `ConsoleServerException`       | 500+        | Server error           |

---

## Summary

Package này là giải pháp SSO client production-ready cho Laravel, tích hợp với Omnify Console:

- **Secure authentication** qua JWT tokens với JWKS verification
- **Flexible authorization** qua local RBAC và Console team permissions
- **Minimal data duplication** (chỉ lưu Console references)
- **Comprehensive caching** cho performance
- **Full audit logging** cho compliance
- **Multi-language support** cho global applications
- **Security hardened** với open redirect protection, encrypted tokens
