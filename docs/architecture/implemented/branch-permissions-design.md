# Branch-Level Permissions Design Document

## 1. Architecture Overview

### Current System (Before)

```
┌─────────────────────────────────────────────────────────────────┐
│                        CURRENT ARCHITECTURE                      │
└─────────────────────────────────────────────────────────────────┘

┌──────────┐      ┌───────────┐      ┌──────────────┐      ┌────────────┐
│   User   │──M:M─│ role_user │──M:M─│     Role     │──M:M─│ Permission │
│  (UUID)  │      │  (pivot)  │      │   (global)   │      │  (global)  │
└──────────┘      └───────────┘      └──────────────┘      └────────────┘
     │                                                            │
     │            ┌───────────────────┐                          │
     └────────────│  TeamPermission   │──────────────────────────┘
                  │ (org + team scope)│
                  └───────────────────┘

Permission Flow:
User Permissions = Role Permissions (global) + Team Permissions (org-scoped)
```

### New System (After)

```
┌─────────────────────────────────────────────────────────────────┐
│                         NEW ARCHITECTURE                         │
│              Scoped Role Assignments (Industry Standard)         │
└─────────────────────────────────────────────────────────────────┘

┌──────────┐      ┌─────────────────────┐      ┌──────────┐      ┌────────────┐
│   User   │──M:M─│      role_user      │──M:M─│   Role   │──M:M─│ Permission │
│  (UUID)  │      │       (pivot)       │      │ (global) │      │  (global)  │
└──────────┘      │  + console_organization_id   │      └──────────┘      └────────────┘
                  │  + console_branch_id│
                  └─────────────────────┘
                           │
              ┌────────────┼────────────┐
              ▼            ▼            ▼
        ┌─────────┐  ┌─────────┐  ┌─────────┐
        │ Global  │  │Org-wide │  │ Branch  │
        │  Scope  │  │  Scope  │  │ Scope   │
        │org=null │  │org=X    │  │org=X    │
        │branch=  │  │branch=  │  │branch=Y │
        │  null   │  │  null   │  │         │
        └─────────┘  └─────────┘  └─────────┘

Permission Flow:
User Permissions = Role Permissions (scoped) + Team Permissions (org-scoped)
                   ├── Global assignments
                   ├── Org-wide assignments
                   └── Branch-specific assignments
```

---

## 2. Data Model Diagrams

### Entity Relationship Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           ENTITY RELATIONSHIP DIAGRAM                        │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────┐                                              ┌─────────────┐
│    User     │                                              │    Role     │
├─────────────┤                                              ├─────────────┤
│ id (UUID)   │                                              │ id (UUID)   │
│ name        │         ┌──────────────────────┐            │ name        │
│ email       │─────────│      role_user       │────────────│ slug        │
│ console_    │   M:M   │    (pivot table)     │    M:M     │ level       │
│ user_id     │         ├──────────────────────┤            │ description │
└─────────────┘         │ user_id (FK)         │            └──────┬──────┘
                        │ role_id (FK)         │                   │
                        │ console_organization_id  ◄────┼── NEW             │ M:M
                        │ console_branch_id◄───┼── NEW             │
                        │ created_at           │            ┌──────┴──────┐
                        │ updated_at           │            │role_permissions│
                        └──────────────────────┘            ├─────────────┤
                                                            │ role_id     │
┌─────────────┐         ┌──────────────────────┐            │ permission_id│
│   Branch    │         │   TeamPermission     │            └──────┬──────┘
├─────────────┤         ├──────────────────────┤                   │
│ id (UUID)   │         │ id (UUID)            │            ┌──────┴──────┐
│ console_    │         │ console_organization_id       │            │ Permission  │
│ branch_id   │         │ console_team_id      │────────────├─────────────┤
│ console_    │         │ permission_id (FK)   │    M:1     │ id (UUID)   │
│ org_id      │         └──────────────────────┘            │ name        │
│ code        │                                             │ slug        │
│ name        │                                             │ group       │
└─────────────┘                                             └─────────────┘
```

### Scope Hierarchy

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              SCOPE HIERARCHY                                 │
└─────────────────────────────────────────────────────────────────────────────┘

                    ┌─────────────────────────┐
                    │      GLOBAL SCOPE       │
                    │   organization_id = null         │
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
    │   organization_id = A  │   │   organization_id = B  │   │   organization_id = C  │
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
│  organization_id = A   │       │  organization_id = A   │
│ branch_id = 1 │       │ branch_id = 2 │
│               │       │               │
│  Tokyo Staff  │       │  Osaka Staff  │
│  Only Tokyo   │       │  Only Osaka   │
└───────────────┘       └───────────────┘
```

---

## 3. Permission Resolution Flow

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
                    ┌─────────────────┐
                    │   Middleware    │
                    │ Set organization_id = A  │
                    │ Set branch_id=1 │
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

---

## 4. Database Schema Changes

### role_user Pivot Table (Before vs After)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         role_user TABLE CHANGES                              │
└─────────────────────────────────────────────────────────────────────────────┘

BEFORE:                              AFTER:
┌──────────────────────┐             ┌──────────────────────────────┐
│      role_user       │             │         role_user            │
├──────────────────────┤             ├──────────────────────────────┤
│ id         (UUID)    │             │ id              (UUID)       │
│ user_id    (UUID,FK) │             │ user_id         (UUID,FK)    │
│ role_id    (UUID,FK) │             │ role_id         (UUID,FK)    │
│ created_at           │             │ console_organization_id  (UUID,null)◄─┼── NEW
│ updated_at           │             │ console_branch_id(UUID,null)◄┼── NEW
└──────────────────────┘             │ created_at                   │
                                     │ updated_at                   │
                                     └──────────────────────────────┘

INDEX: (console_organization_id, console_branch_id)
```

### Example Data

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           EXAMPLE DATA                                       │
└─────────────────────────────────────────────────────────────────────────────┘

roles table:
┌──────────┬───────────────┬───────────────┬───────┐
│    id    │     name      │     slug      │ level │
├──────────┼───────────────┼───────────────┼───────┤
│ role-001 │ System Admin  │ system-admin  │  100  │
│ role-002 │ Manager       │ manager       │   50  │
│ role-003 │ Staff         │ staff         │   10  │
└──────────┴───────────────┴───────────────┴───────┘

role_user table (with new columns):
┌──────────┬───────────┬───────────┬────────────────┬───────────────────┐
│    id    │  user_id  │  role_id  │ console_organization_id │ console_branch_id │
├──────────┼───────────┼───────────┼────────────────┼───────────────────┤
│ ru-001   │ user-A    │ role-001  │     null       │       null        │ ◄ Global Admin
│ ru-002   │ user-B    │ role-002  │     org-X      │       null        │ ◄ Org-wide Manager
│ ru-003   │ user-C    │ role-002  │     org-X      │    branch-tokyo   │ ◄ Tokyo Manager
│ ru-004   │ user-C    │ role-003  │     org-X      │    branch-osaka   │ ◄ Osaka Staff
│ ru-005   │ user-D    │ role-003  │     org-X      │    branch-tokyo   │ ◄ Tokyo Staff
└──────────┴───────────┴───────────┴────────────────┴───────────────────┘

Result:
• user-A: System Admin everywhere
• user-B: Manager in all branches of org-X
• user-C: Manager at Tokyo, Staff at Osaka
• user-D: Staff only at Tokyo
```

---

## 5. API Design

### Headers

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                            REQUEST HEADERS                                   │
└─────────────────────────────────────────────────────────────────────────────┘

Required:
┌────────────────┬──────────────────────────────────────────┐
│ Header         │ Description                              │
├────────────────┼──────────────────────────────────────────┤
│ Authorization  │ Bearer {token}                           │
│ X-Organization-Id       │ Organization slug (required for org ops) │
└────────────────┴──────────────────────────────────────────┘

Optional:
┌────────────────┬──────────────────────────────────────────┐
│ Header         │ Description                              │
├────────────────┼──────────────────────────────────────────┤
│ X-Branch-Id    │ Branch UUID (for branch-specific ops)    │
└────────────────┴──────────────────────────────────────────┘
```

### Endpoints

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              NEW ENDPOINTS                                   │
└─────────────────────────────────────────────────────────────────────────────┘

Role Assignment:
┌────────┬─────────────────────────────────┬──────────────────────────────────┐
│ Method │ Endpoint                        │ Description                      │
├────────┼─────────────────────────────────┼──────────────────────────────────┤
│ GET    │ /api/admin/sso/users/{id}/roles │ List user's role assignments     │
│ POST   │ /api/admin/sso/users/{id}/roles │ Assign role with scope           │
│ DELETE │ /api/admin/sso/users/{id}/roles │ Remove role assignment           │
└────────┴─────────────────────────────────┴──────────────────────────────────┘

Request Body (POST):
{
  "role_id": "uuid",
  "console_organization_id": "uuid | null",      // null = global
  "console_branch_id": "uuid | null"    // null = org-wide
}

Response (GET):
{
  "data": [
    {
      "role": { "id": "...", "name": "Manager", "slug": "manager" },
      "console_organization_id": "org-uuid",
      "console_branch_id": null,
      "scope": "org-wide"
    },
    {
      "role": { "id": "...", "name": "Staff", "slug": "staff" },
      "console_organization_id": "org-uuid",
      "console_branch_id": "branch-uuid",
      "scope": "branch"
    }
  ]
}
```

---

## 6. Implementation Checklist

### Phase 1: Schema & Database ✅ COMPLETED

- [x] Update `database/schemas/Sso/User.yaml`
  - [x] Add `pivotFields` to roles association
  - [x] Add `console_organization_id` (Uuid, nullable)
  - [x] Add `console_branch_id` (Uuid, nullable)

- [x] Run `npx omnify generate`
  - [x] Verify migration generated correctly
  - [x] Verify base model updated

- [x] Run `php artisan migrate`
  - [x] Verify columns added to role_user table
  - [x] Verify index created

### Phase 2: Models & Traits ✅ COMPLETED

- [x] Update `src/Models/User.php`
  - [x] Add `getRolesForContext(?orgId, ?branchId)` method
  - [x] Add `assignRole(Role, ?orgId, ?branchId)` method
  - [x] Add `removeRole(Role, ?orgId, ?branchId)` method
  - [x] Add `hasRoleInContext(string $slug, ?orgId, ?branchId)` method

- [x] Update `src/Models/Traits/HasTeamPermissions.php`
  - [x] Modify `getRolePermissions()` to accept orgId, branchId
  - [x] Modify `getAllPermissions()` to accept orgId, branchId
  - [x] Update `hasPermission()` signature
  - [x] Update `hasAnyPermission()` signature
  - [x] Update `hasAllPermissions()` signature

### Phase 3: Middleware ✅ COMPLETED

- [x] Update `src/Http/Middleware/SsoOrganizationAccess.php`
  - [x] Read `X-Branch-Id` header
  - [x] Validate branch belongs to organization
  - [x] Set `current_branch_id` in session
  - [x] Set `branchId` in request attributes

- [x] Update `src/Http/Middleware/SsoPermissionCheck.php`
  - [x] Pass branchId to permission check

- [x] Update `src/Http/Middleware/SsoRoleCheck.php`
  - [x] Consider branch context in role level check

### Phase 4: Controllers ✅ COMPLETED

- [x] Update `src/Http/Controllers/Admin/RoleAdminController.php`
  - [x] Add `getUserRoles(User $user)` endpoint
  - [x] Add `assignRoleToUser(User $user)` endpoint
  - [x] Add `removeRoleFromUser(User $user, Role $role)` endpoint
  - [x] Update existing endpoints to handle scope
  - [x] Fix `syncPermissions` to handle UUID IDs (use `Str::isUuid()` instead of `is_numeric()`)

- [x] Create Request classes
  - [x] `AssignRoleRequest` (validate role_id, org_id, branch_id)

### Phase 5: Caching ✅ COMPLETED

- [x] Review `src/Cache/RolePermissionCache.php`
  - [x] Cache key may need to include scope context
  - [x] Consider cache invalidation strategy

### Phase 6: Testing ✅ COMPLETED (493 passed, 5 skipped)

- [x] Update `database/factories/UserFactory.php`
  - [x] Add `withRole(Role, ?orgId, ?branchId)` helper
  - [x] Add `withoutConsoleUserId()` helper
  - [x] Add `unverified()` helper

- [x] Create/Update tests
  - [x] Test global role assignment
  - [x] Test org-wide role assignment
  - [x] Test branch-specific role assignment
  - [x] Test permission aggregation across scopes
  - [x] Test same user with different roles per branch
  - [x] Test middleware with X-Branch-Id header
  - [x] Fix all UUID-related test failures (was 21 → 0 failed)

### Phase 7: Documentation ✅ COMPLETED

- [x] Update `DOCUMENTATION.md`
  - [x] Document new headers
  - [x] Document new endpoints
  - [x] Document permission resolution flow
  - [x] Add usage examples

- [x] Update OpenAPI annotations
  - [x] Add X-Branch-Id header to schemas
  - [x] Document new endpoints

### Phase 8: HQ Fallback ✅ COMPLETED

- [x] HQ Fallback - Auto-fallback to `is_headquarters` branch when no X-Branch-Id header
  - [x] Add `fallback_to_hq` config option in `config/sso-client.php`
  - [x] Update `SsoOrganizationAccess` middleware to detect HQ branch
  - [ ] Add tests for HQ fallback (optional)

---

## 7. Migration Strategy

### Backward Compatibility

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        BACKWARD COMPATIBILITY                                │
└─────────────────────────────────────────────────────────────────────────────┘

Existing Data:
• All existing role_user records will have:
  - console_organization_id = null
  - console_branch_id = null
• This means all existing roles become GLOBAL roles
• No breaking change for existing functionality

New Behavior:
• New role assignments can specify scope
• Old assignments continue to work as global
• Permission checks without branch context work as before
```

### Rollback Plan

```
If issues arise:
1. Remove new columns from role_user (migration rollback)
2. Revert model changes
3. Revert middleware changes
4. All data preserved (null columns have no effect)
```

---

## 8. Security Considerations

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        SECURITY CONSIDERATIONS                               │
└─────────────────────────────────────────────────────────────────────────────┘

1. Branch Validation
   ✓ Always verify branch belongs to specified organization
   ✓ Prevent cross-org branch access

2. Scope Escalation Prevention
   ✓ User cannot assign broader scope than they have
   ✓ Branch admin cannot create org-wide assignments
   ✓ Org admin cannot create global assignments

3. Header Validation
   ✓ Validate X-Branch-Id format (UUID)
   ✓ Reject invalid branch IDs with 400 error

4. Audit Logging
   ✓ Log all role assignment changes
   ✓ Include scope information in logs
```

---

## 9. References

- [NIST RBAC Standard (ANSI/INCITS 359-2012)](https://csrc.nist.gov/Projects/Role-Based-Access-Control)
- [WorkOS: Multi-Tenant RBAC Design](https://workos.com/blog/how-to-design-multi-tenant-rbac-saas)
- [Aserto: Multi-Tenant RBAC](https://www.aserto.com/blog/authorization-101-multi-tenant-rbac)
- [Permit.io: Multi-Tenant Authorization](https://www.permit.io/blog/best-practices-for-multi-tenant-authorization)
