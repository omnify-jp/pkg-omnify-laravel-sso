# Scoped RBAC — Database Design & Implementation Guide

> **Role-Based Access Control with Hierarchical Scope Inheritance**
>
> Một user có thể giữ role khác nhau ở các cấp khác nhau trong tổ chức.
> Quyền tự động chảy xuống (downward inheritance) — không cần gán lại thủ công.

---

## Mục lục

1. [Triết lý thiết kế](#1-triết-lý-thiết-kế)
2. [Mô hình phạm vi (Scope Hierarchy)](#2-mô-hình-phạm-vi)
3. [Database Schema](#3-database-schema)
4. [Downward Inheritance — cách hoạt động](#4-downward-inheritance)
5. [Query Patterns](#5-query-patterns)
6. [Index Strategy](#6-index-strategy)
7. [API Design](#7-api-design)
8. [Migration Guide](#8-migration-guide)
9. [Edge Cases & Pitfalls](#9-edge-cases--pitfalls)
10. [So sánh với các mô hình khác](#10-so-sánh-với-các-mô-hình-khác)

---

## 1. Triết lý thiết kế

### Tại sao cần Scoped RBAC?

Simple RBAC (Permission 2) gán role cho user **một lần, áp dụng toàn hệ thống**. Điều này không đủ khi:

| Tình huống | Simple RBAC | Scoped RBAC |
|---|---|---|
| An là Admin toàn hệ thống | ✅ `An → Admin` | ✅ `An → Admin @ Global` |
| Châu là Developer ở org-1, nhưng PM ở branch HQ | ❌ Phải chọn 1 | ✅ 2 assignments riêng biệt |
| Nhân viên mới chỉ xem được 1 location | ❌ Viewer toàn bộ hoặc không | ✅ `Em → Viewer @ loc-5` |
| PM org-1 tự động có quyền ở tất cả branches/locations của org-1 | ❌ Phải gán thủ công từng nơi | ✅ Tự động inherit xuống |

### Nguyên tắc cốt lõi

```
┌─────────────────────────────────────────────────────────┐
│  1. ASSIGNMENT = USER + ROLE + SCOPE                    │
│     Một bộ ba xác định quyền tại một điểm cụ thể       │
│                                                         │
│  2. DOWNWARD INHERITANCE                                │
│     Quyền ở scope cha tự động áp dụng cho scope con    │
│     Global → Org → Branch → Location                    │
│                                                         │
│  3. ADDITIVE ONLY                                       │
│     Không có "deny". Quyền chỉ cộng dồn, không trừ.    │
│     Nếu cần restrict → không gán, đừng deny.            │
│                                                         │
│  4. EXPLICIT OVER IMPLICIT                              │
│     Mọi quyền đều trace được nguồn gốc:                │
│     "Quyền này từ đâu? → từ assignment X ở scope Y"    │
│                                                         │
│  5. SEPARATION OF CONCERNS                              │
│     Role definition (role có permissions gì) tách biệt  │
│     với role assignment (ai giữ role ở đâu)             │
└─────────────────────────────────────────────────────────┘
```

### Khi nào KHÔNG cần Scoped RBAC?

- App đơn giản, không có khái niệm tổ chức/chi nhánh → Simple RBAC đủ
- Cần deny rules phức tạp (allow X nhưng deny Y) → Xem ABAC hoặc Policy-based
- Chỉ cần phân quyền theo resource cụ thể (task, file) → Resource-level ACL phù hợp hơn

---

## 2. Mô hình phạm vi

### Scope Hierarchy (cây 4 cấp)

```
Global (toàn hệ thống)
│
├── Organization (tổ chức)
│   │
│   ├── Branch (chi nhánh)
│   │   │
│   │   ├── Location (địa điểm)
│   │   └── Location
│   │
│   └── Branch
│       └── Location
│
└── Organization
    └── Branch
        └── Location
```

### Inheritance Flow

```
Assignment: Châu → Developer @ org-1

Effective permissions:
  ✅ org-1                  ← direct
  ✅ org-1 / branch-1 (HQ) ← inherited
  ✅ org-1 / branch-2      ← inherited
  ✅ org-1 / branch-3      ← inherited
  ✅ org-1 / branch-1 / loc-1  ← inherited
  ✅ org-1 / branch-1 / loc-2  ← inherited
  ...tất cả locations thuộc org-1

  ❌ org-2                  ← không liên quan
  ❌ org-2 / branch-4      ← không liên quan
```

### Scope Resolution Order

Khi kiểm tra quyền tại một scope, hệ thống collect assignments từ **scope đó đi lên root**:

```
Check permission at loc-3 (thuộc branch-2, thuộc org-1):

  1. Collect assignments at loc-3     (location level)
  2. Collect assignments at branch-2  (branch level)
  3. Collect assignments at org-1     (org level)
  4. Collect assignments at global    (global level)
  5. Union tất cả permissions → effective permission set
```

---

## 3. Database Schema

### ERD

```
┌──────────────┐     ┌──────────────────┐     ┌──────────────┐
│    users      │     │ scoped_role_     │     │    roles      │
│──────────────│     │ assignments      │     │──────────────│
│ id        PK │◄────│──────────────────│────►│ id        PK │
│ name         │     │ id            PK │     │ name         │
│ email        │     │ user_id       FK │     │ description  │
│ avatar       │     │ role_id       FK │     │ color        │
│ status       │     │ scope_type       │     │ is_system    │
│ created_at   │     │ scope_id         │     │ created_at   │
└──────────────┘     │ assigned_at      │     └──────┬───────┘
                     │ assigned_by   FK │            │
                     │ created_at      │            │ M:N
                     └──────────────────┘     ┌──────┴───────┐
                                              │ role_        │
┌──────────────┐                              │ permissions  │
│ organizations │                              │──────────────│
│──────────────│                              │ role_id   FK │
│ id        PK │                              │ perm_id   FK │
│ name         │                              └──────┬───────┘
│ ...          │                                     │
└──────────────┘                              ┌──────┴───────┐
                                              │ permissions  │
┌──────────────┐                              │──────────────│
│   branches   │                              │ id        PK │
│──────────────│                              │ module       │
│ id        PK │                              │ action       │
│ org_id    FK │                              │ label        │
│ name         │                              │ description  │
│ is_hq        │                              └──────────────┘
└──────────────┘

┌──────────────┐
│  locations   │
│──────────────│
│ id        PK │
│ branch_id FK │
│ name         │
│ is_default   │
└──────────────┘
```

### SQL — Core Table

```sql
-- =====================================================
-- Bảng trung tâm: scoped_role_assignments
-- Đây là bảng DUY NHẤT khác biệt so với Simple RBAC
-- =====================================================

CREATE TABLE scoped_role_assignments (
    id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id       UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role_id       UUID NOT NULL REFERENCES roles(id) ON DELETE CASCADE,

    -- Scope definition
    scope_type    VARCHAR(20) NOT NULL CHECK (scope_type IN ('global', 'organization', 'branch', 'location')),
    scope_id      UUID,  -- NULL khi scope_type = 'global'

    -- Metadata
    assigned_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    assigned_by   UUID REFERENCES users(id),
    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    -- Constraints
    CONSTRAINT chk_scope_id CHECK (
        (scope_type = 'global' AND scope_id IS NULL) OR
        (scope_type != 'global' AND scope_id IS NOT NULL)
    ),

    -- Prevent duplicate: same user, same role, same scope
    CONSTRAINT uq_user_role_scope UNIQUE (user_id, role_id, scope_type, scope_id)
);

COMMENT ON TABLE scoped_role_assignments IS
    'Gán role cho user tại một scope cụ thể. Quyền inherit xuống theo hierarchy.';
COMMENT ON COLUMN scoped_role_assignments.scope_type IS
    'Cấp phạm vi: global (toàn hệ thống), organization, branch, location';
COMMENT ON COLUMN scoped_role_assignments.scope_id IS
    'ID của entity tại scope đó. NULL khi scope_type = global.';
```

### SQL — Supporting Tables (tham khảo)

```sql
-- Roles (không thay đổi so với Simple RBAC)
CREATE TABLE roles (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name        VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    color       VARCHAR(7),       -- hex color
    is_system   BOOLEAN NOT NULL DEFAULT FALSE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Permissions (không thay đổi)
CREATE TABLE permissions (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    module      VARCHAR(50) NOT NULL,  -- 'projects', 'tasks', 'wiki', ...
    action      VARCHAR(50) NOT NULL,  -- 'view', 'create', 'edit', 'delete', 'manage'
    label       VARCHAR(200) NOT NULL,
    description TEXT,
    CONSTRAINT uq_module_action UNIQUE (module, action)
);

-- Role ↔ Permission (M:N, không thay đổi)
CREATE TABLE role_permissions (
    role_id UUID NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    perm_id UUID NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    PRIMARY KEY (role_id, perm_id)
);

-- Scope hierarchy tables
CREATE TABLE organizations (
    id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name       VARCHAR(200) NOT NULL,
    short_name VARCHAR(10),
    status     VARCHAR(20) NOT NULL DEFAULT 'active',
    plan       VARCHAR(20) NOT NULL DEFAULT 'free',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE branches (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    name            VARCHAR(200) NOT NULL,
    is_hq           BOOLEAN NOT NULL DEFAULT FALSE,
    status          VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE locations (
    id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    branch_id  UUID NOT NULL REFERENCES branches(id) ON DELETE CASCADE,
    name       VARCHAR(200) NOT NULL,
    is_default BOOLEAN NOT NULL DEFAULT FALSE,
    status     VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
```

---

## 4. Downward Inheritance

### Tại sao chọn "resolve at query time" thay vì "materialize"?

| Approach | Pros | Cons |
|---|---|---|
| **Query-time resolution** (chọn) | Dữ liệu luôn consistent, schema đơn giản, không cần sync | Query phức tạp hơn, cần optimize |
| **Materialized inheritance** | Query nhanh (đã flatten sẵn) | Phải sync khi org structure thay đổi, data duplication, race conditions |

**Quyết định:** Resolve at query time + cache. Lý do:

1. Org hierarchy ít thay đổi (thêm branch/location hiếm khi xảy ra)
2. Permission check cần chính xác hơn cần nhanh — cache 5 phút là đủ
3. Schema đơn giản = ít bug, dễ audit

### Ancestor Resolution Logic

```
Input:  scope_type = 'location', scope_id = 'loc-3'
Output: list of (scope_type, scope_id) pairs to check

Step 1: loc-3 thuộc branch-2 (lookup locations table)
Step 2: branch-2 thuộc org-1  (lookup branches table)
Step 3: Build ancestor chain:

  [
    ('location',     'loc-3'),      ← chính nó
    ('branch',       'branch-2'),   ← parent
    ('organization', 'org-1'),      ← grandparent
    ('global',       NULL),         ← root (luôn có)
  ]
```

---

## 5. Query Patterns

### 5.1 Kiểm tra quyền tại một scope cụ thể

> "User An có quyền `tasks.edit` tại `loc-3` không?"

```sql
-- Bước 1: Resolve ancestor chain cho loc-3
WITH scope_chain AS (
    -- Chính scope hiện tại
    SELECT 'location'::text AS scope_type, loc.id AS scope_id
    FROM locations loc WHERE loc.id = :scope_id

    UNION ALL

    -- Branch cha
    SELECT 'branch', b.id
    FROM locations loc
    JOIN branches b ON b.id = loc.branch_id
    WHERE loc.id = :scope_id

    UNION ALL

    -- Organization cha
    SELECT 'organization', o.id
    FROM locations loc
    JOIN branches b ON b.id = loc.branch_id
    JOIN organizations o ON o.id = b.organization_id
    WHERE loc.id = :scope_id

    UNION ALL

    -- Global (luôn có)
    SELECT 'global', NULL
)

-- Bước 2: Check assignment + permission
SELECT EXISTS (
    SELECT 1
    FROM scoped_role_assignments sra
    JOIN role_permissions rp ON rp.role_id = sra.role_id
    JOIN permissions p ON p.id = rp.perm_id
    JOIN scope_chain sc ON sc.scope_type = sra.scope_type
                       AND (sc.scope_id = sra.scope_id OR sra.scope_type = 'global')
    WHERE sra.user_id = :user_id
      AND p.module = 'tasks'
      AND p.action = 'edit'
) AS has_permission;
```

### 5.2 Lấy tất cả effective permissions của user tại một scope

```sql
-- Dùng CTE scope_chain tương tự trên, sau đó:

SELECT DISTINCT p.module, p.action, p.label,
       sra.scope_type AS granted_at_scope,
       sra.scope_id   AS granted_at_scope_id
FROM scoped_role_assignments sra
JOIN role_permissions rp ON rp.role_id = sra.role_id
JOIN permissions p ON p.id = rp.perm_id
JOIN scope_chain sc ON sc.scope_type = sra.scope_type
                   AND (sc.scope_id = sra.scope_id OR sra.scope_type = 'global')
WHERE sra.user_id = :user_id
ORDER BY p.module, p.action;
```

### 5.3 Lấy tất cả users có quyền tại một scope (ai có access?)

```sql
-- "Ai có access tại branch-2?"
-- → Collect users với assignment ở branch-2, org cha, hoặc global

WITH scope_chain AS (
    SELECT 'branch'::text AS scope_type, :branch_id::uuid AS scope_id
    UNION ALL
    SELECT 'organization', b.organization_id
    FROM branches b WHERE b.id = :branch_id
    UNION ALL
    SELECT 'global', NULL
)

SELECT DISTINCT u.id, u.name, u.email,
       r.name AS role_name, r.color,
       sra.scope_type AS assigned_scope,
       CASE
           WHEN sra.scope_type = 'branch' AND sra.scope_id = :branch_id
           THEN 'direct'
           ELSE 'inherited'
       END AS assignment_type
FROM scoped_role_assignments sra
JOIN users u ON u.id = sra.user_id
JOIN roles r ON r.id = sra.role_id
JOIN scope_chain sc ON sc.scope_type = sra.scope_type
                   AND (sc.scope_id = sra.scope_id OR sra.scope_type = 'global')
ORDER BY assignment_type, u.name;
```

### 5.4 Lấy tất cả scoped assignments của một user

```sql
-- Cho trang User Detail: hiển thị grouped by scope level

SELECT sra.id, sra.scope_type, sra.scope_id, sra.assigned_at,
       r.name AS role_name, r.color,
       COALESCE(o.name, b.name, l.name, 'Global') AS scope_name
FROM scoped_role_assignments sra
JOIN roles r ON r.id = sra.role_id
LEFT JOIN organizations o ON sra.scope_type = 'organization' AND o.id = sra.scope_id
LEFT JOIN branches b ON sra.scope_type = 'branch' AND b.id = sra.scope_id
LEFT JOIN locations l ON sra.scope_type = 'location' AND l.id = sra.scope_id
WHERE sra.user_id = :user_id
ORDER BY
    CASE sra.scope_type
        WHEN 'global' THEN 0
        WHEN 'organization' THEN 1
        WHEN 'branch' THEN 2
        WHEN 'location' THEN 3
    END,
    sra.assigned_at DESC;
```

---

## 6. Index Strategy

```sql
-- PRIMARY: lookup by user (most common query)
CREATE INDEX idx_sra_user_id ON scoped_role_assignments(user_id);

-- Scope-based lookup: "who has access at this scope?"
CREATE INDEX idx_sra_scope ON scoped_role_assignments(scope_type, scope_id);

-- Combined: "does this user have this role at this scope?"
CREATE INDEX idx_sra_user_scope ON scoped_role_assignments(user_id, scope_type, scope_id);

-- Role-based: "who has this role?" (for role deletion/audit)
CREATE INDEX idx_sra_role_id ON scoped_role_assignments(role_id);

-- Hierarchy lookups (branch→org, location→branch)
CREATE INDEX idx_branches_org ON branches(organization_id);
CREATE INDEX idx_locations_branch ON locations(branch_id);
```

### Khi nào cần thêm cache?

```
Requests/sec     Strategy
─────────────    ────────────────────────────
< 100            Query trực tiếp, không cache
100 - 1000       Application-level cache (Redis), TTL 5 phút
> 1000           Materialized view + trigger refresh
```

### Redis Cache Pattern

```
Key:    scoped_rbac:user:{user_id}:scope:{scope_type}:{scope_id}
Value:  Set<permission_string>  (e.g., "tasks.edit", "projects.view")
TTL:    300 seconds (5 minutes)

Invalidate on:
  - scoped_role_assignments INSERT/UPDATE/DELETE
  - role_permissions INSERT/UPDATE/DELETE
  - org hierarchy change (branch/location added/moved)
```

---

## 7. API Design

### Endpoints

```
GET    /api/scoped-rbac/assignments              List all (filterable)
POST   /api/scoped-rbac/assignments              Create assignment
DELETE /api/scoped-rbac/assignments/:id           Remove assignment

GET    /api/scoped-rbac/users/:id/assignments     User's scoped assignments
GET    /api/scoped-rbac/users/:id/permissions      User's effective permissions
       ?scope_type=branch&scope_id=xxx            (at specific scope)

GET    /api/scoped-rbac/scopes/:type/:id/users    Who has access at scope?
GET    /api/scoped-rbac/scopes/tree               Full scope tree

POST   /api/scoped-rbac/check                     Permission check
       { user_id, permission, scope_type, scope_id }
```

### Permission Check Response

```json
// POST /api/scoped-rbac/check
// Request:
{
    "user_id": "rbac-user-3",
    "permission": "tasks.edit",
    "scope_type": "location",
    "scope_id": "loc-3"
}

// Response:
{
    "allowed": true,
    "granted_via": [
        {
            "assignment_id": "sa-3",
            "role": "Developer",
            "scope_type": "organization",
            "scope_id": "org-1",
            "scope_name": "Công ty TNHH ABC",
            "relationship": "inherited"
        }
    ]
}
```

### Laravel Implementation Sketch

```php
// app/Models/ScopedRoleAssignment.php

class ScopedRoleAssignment extends Model
{
    protected $fillable = ['user_id', 'role_id', 'scope_type', 'scope_id', 'assigned_by'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function role(): BelongsTo { return $this->belongsTo(Role::class); }

    /**
     * Get the scope entity (polymorphic-like, manual resolution)
     */
    public function getScopeEntity(): Model|null
    {
        return match ($this->scope_type) {
            'organization' => Organization::find($this->scope_id),
            'branch'       => Branch::find($this->scope_id),
            'location'     => Location::find($this->scope_id),
            default        => null,
        };
    }
}
```

```php
// app/Services/ScopedPermissionService.php

class ScopedPermissionService
{
    /**
     * Resolve ancestor chain for a given scope.
     * Returns array of [scope_type, scope_id] pairs from leaf to root.
     */
    public function getAncestorChain(string $scopeType, ?string $scopeId): array
    {
        $chain = [['global', null]];

        if ($scopeType === 'global') return $chain;

        if ($scopeType === 'organization') {
            array_unshift($chain, ['organization', $scopeId]);
            return $chain;
        }

        if ($scopeType === 'branch') {
            $branch = Branch::findOrFail($scopeId);
            array_unshift($chain, ['branch', $scopeId]);
            array_unshift($chain, ['organization', $branch->organization_id]);
            // Wait — order should be leaf first, so:
            return [
                ['branch', $scopeId],
                ['organization', $branch->organization_id],
                ['global', null],
            ];
        }

        if ($scopeType === 'location') {
            $location = Location::findOrFail($scopeId);
            $branch = Branch::findOrFail($location->branch_id);
            return [
                ['location', $scopeId],
                ['branch', $branch->id],
                ['organization', $branch->organization_id],
                ['global', null],
            ];
        }

        return $chain;
    }

    /**
     * Check if user has a specific permission at a scope.
     */
    public function hasPermission(
        string $userId,
        string $module,
        string $action,
        string $scopeType,
        ?string $scopeId
    ): bool {
        $cacheKey = "scoped_rbac:{$userId}:{$scopeType}:{$scopeId}";

        return Cache::remember($cacheKey, 300, function () use ($userId, $module, $action, $scopeType, $scopeId) {
            $chain = $this->getAncestorChain($scopeType, $scopeId);

            return ScopedRoleAssignment::query()
                ->where('user_id', $userId)
                ->where(function ($q) use ($chain) {
                    foreach ($chain as [$type, $id]) {
                        $q->orWhere(function ($sub) use ($type, $id) {
                            $sub->where('scope_type', $type);
                            if ($id === null) {
                                $sub->whereNull('scope_id');
                            } else {
                                $sub->where('scope_id', $id);
                            }
                        });
                    }
                })
                ->whereHas('role.permissions', function ($q) use ($module, $action) {
                    $q->where('module', $module)->where('action', $action);
                })
                ->exists();
        });
    }

    /**
     * Get all effective permissions for user at a scope.
     */
    public function getEffectivePermissions(
        string $userId,
        string $scopeType,
        ?string $scopeId
    ): Collection {
        $chain = $this->getAncestorChain($scopeType, $scopeId);

        $roleIds = ScopedRoleAssignment::query()
            ->where('user_id', $userId)
            ->where(function ($q) use ($chain) {
                foreach ($chain as [$type, $id]) {
                    $q->orWhere(function ($sub) use ($type, $id) {
                        $sub->where('scope_type', $type);
                        if ($id === null) {
                            $sub->whereNull('scope_id');
                        } else {
                            $sub->where('scope_id', $id);
                        }
                    });
                }
            })
            ->pluck('role_id')
            ->unique();

        return Permission::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('roles.id', $roleIds))
            ->get();
    }
}
```

### Middleware

```php
// app/Http/Middleware/CheckScopedPermission.php

class CheckScopedPermission
{
    public function __construct(private ScopedPermissionService $permissions) {}

    /**
     * Usage in routes:
     *   Route::get('/branches/{branch}/tasks', ...)
     *       ->middleware('scoped.permission:tasks.view,branch');
     */
    public function handle(Request $request, Closure $next, string $permission, string $scopeParam): Response
    {
        [$module, $action] = explode('.', $permission);
        $scopeId = $request->route($scopeParam)?->id ?? $request->route($scopeParam);

        // Determine scope type from route parameter name
        $scopeType = match ($scopeParam) {
            'organization', 'org' => 'organization',
            'branch'              => 'branch',
            'location'            => 'location',
            default               => 'global',
        };

        if (!$this->permissions->hasPermission(
            $request->user()->id,
            $module,
            $action,
            $scopeType,
            $scopeId
        )) {
            abort(403, 'Insufficient scoped permissions');
        }

        return $next($request);
    }
}
```

---

## 8. Migration Guide

### Từ Simple RBAC sang Scoped RBAC

```
Phase 1: Schema migration (backwards compatible)
  ├── Create scoped_role_assignments table
  ├── Keep existing user_roles table (don't delete yet)
  └── Add migration script

Phase 2: Data migration
  ├── Convert existing role assignments to global scope
  │   INSERT INTO scoped_role_assignments (user_id, role_id, scope_type, scope_id)
  │   SELECT user_id, role_id, 'global', NULL
  │   FROM user_roles;
  └── Verify: count matches

Phase 3: Switch permission checks
  ├── Update middleware to use ScopedPermissionService
  ├── Update frontend to pass scope context
  └── Test thoroughly

Phase 4: Cleanup
  ├── Drop user_roles table
  └── Remove old permission check code
```

### Laravel Migration File

```php
// database/migrations/xxxx_create_scoped_role_assignments_table.php

return new class extends Migration {
    public function up(): void
    {
        Schema::create('scoped_role_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('role_id')->constrained()->cascadeOnDelete();
            $table->string('scope_type', 20);
            $table->uuid('scope_id')->nullable();
            $table->timestamp('assigned_at')->useCurrent();
            $table->foreignUuid('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index(['scope_type', 'scope_id']);
            $table->index(['user_id', 'scope_type', 'scope_id']);
            $table->index('role_id');

            // Unique constraint
            $table->unique(['user_id', 'role_id', 'scope_type', 'scope_id'], 'uq_user_role_scope');
        });

        // Migrate existing simple RBAC data
        DB::statement("
            INSERT INTO scoped_role_assignments (id, user_id, role_id, scope_type, scope_id, assigned_at)
            SELECT gen_random_uuid(), user_id, role_id, 'global', NULL, created_at
            FROM user_roles
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('scoped_role_assignments');
    }
};
```

---

## 9. Edge Cases & Pitfalls

### ⚠️ Pitfall 1: Orphaned Assignments

Khi xóa branch hoặc location, phải xóa assignments liên quan.

```sql
-- CASCADE đã handle nếu dùng foreign key
-- Nhưng scope_id là UUID không có FK (vì polymorphic) → cần trigger hoặc application logic

-- Option A: Application-level cleanup
-- Khi xóa branch:
DELETE FROM scoped_role_assignments
WHERE scope_type = 'branch' AND scope_id = :deleted_branch_id;

-- Option B: Scheduled cleanup job
DELETE FROM scoped_role_assignments
WHERE scope_type = 'organization' AND scope_id NOT IN (SELECT id FROM organizations)
   OR scope_type = 'branch' AND scope_id NOT IN (SELECT id FROM branches)
   OR scope_type = 'location' AND scope_id NOT IN (SELECT id FROM locations);
```

### ⚠️ Pitfall 2: Scope ID chưa FK constraint

`scope_id` trỏ đến organization, branch, hoặc location tùy `scope_type`. Không thể dùng single FK.

**Giải pháp:**
- Application-level validation khi INSERT
- Check constraint hoặc trigger nếu DB support
- Scheduled integrity check

```sql
-- Trigger-based validation (PostgreSQL)
CREATE OR REPLACE FUNCTION validate_scope_id()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.scope_type = 'organization' THEN
        IF NOT EXISTS (SELECT 1 FROM organizations WHERE id = NEW.scope_id) THEN
            RAISE EXCEPTION 'Invalid organization scope_id: %', NEW.scope_id;
        END IF;
    ELSIF NEW.scope_type = 'branch' THEN
        IF NOT EXISTS (SELECT 1 FROM branches WHERE id = NEW.scope_id) THEN
            RAISE EXCEPTION 'Invalid branch scope_id: %', NEW.scope_id;
        END IF;
    ELSIF NEW.scope_type = 'location' THEN
        IF NOT EXISTS (SELECT 1 FROM locations WHERE id = NEW.scope_id) THEN
            RAISE EXCEPTION 'Invalid location scope_id: %', NEW.scope_id;
        END IF;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_validate_scope
    BEFORE INSERT OR UPDATE ON scoped_role_assignments
    FOR EACH ROW EXECUTE FUNCTION validate_scope_id();
```

### ⚠️ Pitfall 3: Role thay đổi permissions

Khi sửa permissions của role → tất cả assignments dùng role đó bị ảnh hưởng. Cần invalidate cache.

```php
// Role observer
class RoleObserver
{
    public function updated(Role $role): void
    {
        // Invalidate cache cho tất cả users có role này
        $userIds = ScopedRoleAssignment::where('role_id', $role->id)->pluck('user_id');
        foreach ($userIds as $userId) {
            Cache::tags(["scoped_rbac:user:{$userId}"])->flush();
        }
    }
}
```

### ⚠️ Pitfall 4: Conflicting roles at different scopes

User có `Viewer @ org-1` và `Admin @ branch-1`. Tại branch-1, effective = union(Viewer + Admin).

Đây là **by design** (additive). Nếu cần "Admin ở branch-1 override Viewer ở org-1", đó là khác pattern — cần priority-based hoặc deny rules, tức phức tạp hơn nhiều.

### ⚠️ Pitfall 5: Performance với deep hierarchy

4 cấp (global/org/branch/location) → maximum 4 scope lookups per permission check. Rất nhanh.

Nếu tương lai mở rộng thành N cấp (departments, teams, sub-teams...) → cần recursive CTE hoặc materialized path.

```sql
-- Materialized path approach (future-proof, nếu cần > 4 levels)
ALTER TABLE scopes ADD COLUMN path LTREE;
-- org-1.branch-2.loc-3
-- Query: WHERE 'org-1.branch-2.loc-3' <@ scope.path
```

---

## 10. So sánh với các mô hình khác

```
┌─────────────────┬────────────────┬───────────────┬──────────────────┐
│                  │ Simple RBAC    │ Scoped RBAC   │ ABAC             │
│                  │ (Permission 2) │ (Permission 3)│ (Attribute-Based)│
├─────────────────┼────────────────┼───────────────┼──────────────────┤
│ Assignment      │ User → Role    │ User → Role   │ User attrs +     │
│                 │                │   @ Scope     │ Resource attrs + │
│                 │                │               │ Environment      │
├─────────────────┼────────────────┼───────────────┼──────────────────┤
│ Granularity     │ System-wide    │ Per org unit  │ Per attribute    │
│                 │                │               │ combination      │
├─────────────────┼────────────────┼───────────────┼──────────────────┤
│ Inheritance     │ None           │ Downward      │ Via policy rules │
├─────────────────┼────────────────┼───────────────┼──────────────────┤
│ Deny rules      │ No             │ No (additive) │ Yes              │
├─────────────────┼────────────────┼───────────────┼──────────────────┤
│ Complexity      │ Low            │ Medium        │ High             │
├─────────────────┼────────────────┼───────────────┼──────────────────┤
│ Best for        │ Single-tenant  │ Multi-tenant  │ Complex policies │
│                 │ apps           │ with org      │ with conditions  │
│                 │                │ hierarchy     │ (time, IP, etc.) │
├─────────────────┼────────────────┼───────────────┼──────────────────┤
│ Query cost      │ O(1) lookup    │ O(depth)      │ O(policies)      │
│                 │                │ depth ≤ 4     │                  │
├─────────────────┼────────────────┼───────────────┼──────────────────┤
│ Tables needed   │ 4              │ 5 (+1 table)  │ 3+ policy tables │
└─────────────────┴────────────────┴───────────────┴──────────────────┘
```

### Khi nào chuyển lên ABAC?

Scoped RBAC không đủ khi bạn cần:
- **Time-based rules**: "Chỉ access trong giờ hành chính"
- **Condition-based**: "Chỉ edit task mà mình được assign"
- **Cross-cutting**: "Deny access từ IP ngoài VPN"
- **Dynamic attributes**: "Chỉ users có certification X"

Khi đó, kết hợp Scoped RBAC + Policy layer:

```
Check permission:
  1. Scoped RBAC: User có quyền "tasks.edit" tại scope này?  → Yes/No
  2. Policy layer: Có condition nào block không?               → Allow/Deny
  3. Final = RBAC(Yes) AND Policy(Allow)
```

---

## Tham khảo thêm

- **Demo UI**: `/scoped-rbac` trong demo app — xem trực quan cách scope tree và inheritance hoạt động
- **Source code**: `apps/demo/src/components/scoped-rbac/scopeUtils.ts` — hàm `buildScopeTree()`, `getInheritedAssignments()`
- **Simple RBAC docs**: `/rbac` trong demo app — so sánh trực tiếp với mô hình không có scope
