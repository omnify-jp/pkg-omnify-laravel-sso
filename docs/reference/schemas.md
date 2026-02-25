# SSO Schema Reference

> Tài liệu chi tiết về các schema trong SSO package.

## Tổng quan

Package SSO sử dụng Omnify để định nghĩa các schema cho hệ thống xác thực và phân quyền. Các schema này được đồng bộ từ Console (central authentication server) về local database.

```
schemas/Sso/
├── Cache Models (đồng bộ từ Console)
│   ├── Organization.yaml   # Tổ chức
│   ├── Branch.yaml         # Chi nhánh
│   ├── Team.yaml           # Nhóm/Team
│   └── User.yaml           # Người dùng
│
└── Permission Models (local)
    ├── Role.yaml                # Vai trò
    ├── Permission.yaml          # Quyền hạn
    ├── RolePermission.yaml      # Pivot: Role ↔ Permission
    └── TeamPermission.yaml      # Quyền theo Team
```

---

## 1. Organization (組織キャッシュ)

**Mục đích:** Lưu cache thông tin tổ chức từ Console API.

| Property | Type | Description |
|----------|------|-------------|
| `id` | UUID | Primary key (local) |
| `console_organization_id` | UUID | ID tổ chức từ Console (unique) |
| `name` | String(100) | Tên tổ chức |
| `code` | String(20) | Mã tổ chức (unique) |
| `is_active` | Boolean | Trạng thái hoạt động |

**Indexes:**
- `code` (unique)

**Relationships:**
- HasMany: `Branch`, `Team`, `User`

**Cách sử dụng:**
```php
// Lấy organization từ console_organization_id
$org = Organization::where('console_organization_id', $consoleOrgId)->first();

// Kiểm tra organization có active
if ($org->is_active) {
    // ...
}
```

---

## 2. Branch (支店キャッシュ)

**Mục đích:** Lưu cache thông tin chi nhánh từ Console API. Mỗi tổ chức có thể có nhiều chi nhánh.

| Property | Type | Description |
|----------|------|-------------|
| `id` | UUID | Primary key (local) |
| `console_branch_id` | UUID | ID chi nhánh từ Console (unique) |
| `console_organization_id` | UUID | ID tổ chức sở hữu |
| `code` | String(20) | Mã chi nhánh (unique trong org) |
| `name` | String(100) | Tên chi nhánh |
| `is_headquarters` | Boolean | Là trụ sở chính? |
| `is_active` | Boolean | Trạng thái hoạt động |

**Indexes:**
- `console_organization_id`
- `(console_organization_id, code)` unique - Mã chi nhánh unique trong tổ chức
- `(console_organization_id, is_active)`
- `(console_organization_id, is_headquarters)`

**Relationships:**
- BelongsTo: `Organization` (via console_organization_id)

**Ví dụ data:**
```
Organization: "ABC Corp" (console_organization_id: uuid-1)
├── Branch: "Trụ sở HN" (code: HN001, is_headquarters: true)
├── Branch: "Chi nhánh HCM" (code: HCM001)
└── Branch: "Chi nhánh ĐN" (code: DN001)
```

---

## 3. Team (チームキャッシュ)

**Mục đích:** Lưu cache thông tin team/nhóm từ Console API. Teams dùng để phân quyền theo nhóm.

| Property | Type | Description |
|----------|------|-------------|
| `id` | UUID | Primary key (local) |
| `console_team_id` | UUID | ID team từ Console (unique) |
| `console_organization_id` | UUID | ID tổ chức sở hữu |
| `name` | String(100) | Tên team |

**Indexes:**
- `console_organization_id`

**Relationships:**
- BelongsTo: `Organization` (via console_organization_id)
- HasMany: `TeamPermission`

**Use case:**
- Phân quyền theo team (vd: Team A có quyền xem report, Team B không)
- Group users theo chức năng/dự án

---

## 4. User (ユーザーキャッシュ)

**Mục đích:** Lưu cache thông tin user và SSO tokens. Đây là model chính cho authentication.

| Property | Type | Description |
|----------|------|-------------|
| `id` | UUID | Primary key (local) |
| `name` | String | Tên hiển thị |
| `email` | String | Email (unique trong org) |
| `console_user_id` | UUID | ID user từ Console SSO |
| `console_organization_id` | UUID | ID tổ chức user thuộc về |
| `console_access_token` | Text | Access token (encrypted) |
| `console_refresh_token` | Text | Refresh token (encrypted) |
| `console_token_expires_at` | Timestamp | Thời điểm token hết hạn |

**Indexes:**
- `console_organization_id`
- `(email, console_organization_id)` unique - Email unique trong tổ chức
- `(console_user_id, console_organization_id)` unique

**Relationships:**
- BelongsTo: `Organization` (via console_organization_id)
- ManyToMany: `Role` (via pivot `role_user`)

**Pivot fields (role_user):**

| Field | Type | Description |
|-------|------|-------------|
| `console_organization_id` | String(36) | Scope tổ chức (null = global) |
| `console_branch_id` | String(36) | Scope chi nhánh (null = org-wide) |

**Scoped Role Assignment Pattern:**
```
User "Tanaka" có thể có:
├── Role "Admin" @ Global (null, null) → Admin toàn hệ thống
├── Role "Manager" @ Org A (org-a, null) → Manager trong Org A
└── Role "Staff" @ Branch HN (org-a, branch-hn) → Staff chỉ ở chi nhánh HN
```

**Cách sử dụng:**
```php
// Lấy user với roles
$user = User::with('roles')->find($id);

// Lấy roles của user trong org cụ thể
$roles = $user->roles()
    ->wherePivot('console_organization_id', $organizationId)
    ->get();

// Kiểm tra role với scope
$isOrgAdmin = $user->roles()
    ->where('slug', 'admin')
    ->wherePivot('console_organization_id', $organizationId)
    ->exists();
```

---

## 5. Role (ロール)

**Mục đích:** Định nghĩa vai trò trong hệ thống. Roles có thể là global hoặc scoped theo organization.

| Property | Type | Description |
|----------|------|-------------|
| `id` | UUID | Primary key |
| `console_organization_id` | String(36) | null = global role |
| `name` | String(100) | Tên role |
| `slug` | String(100) | Slug (unique trong org) |
| `description` | Text | Mô tả chi tiết |
| `level` | Int | Mức độ ưu tiên (cao = quyền hạn cao) |

**Indexes:**
- `console_organization_id`
- `(console_organization_id, name)` unique
- `(console_organization_id, slug)` unique

**Relationships:**
- ManyToMany: `Permission` (via `role_permissions`)
- ManyToMany: `User` (via `role_user`)

**Role Types:**

| console_organization_id | Type | Example |
|----------------|------|---------|
| `null` | Global Role | Super Admin, System |
| `uuid-xxx` | Organization Role | Org Admin, Manager, Staff |

**Ví dụ:**
```yaml
# Global roles (shared across all orgs)
- name: Super Admin, slug: super-admin, console_organization_id: null, level: 100
- name: System, slug: system, console_organization_id: null, level: 90

# Organization roles (specific to each org)
- name: Admin, slug: admin, console_organization_id: org-1, level: 50
- name: Manager, slug: manager, console_organization_id: org-1, level: 30
- name: Staff, slug: staff, console_organization_id: org-1, level: 10
```

---

## 6. Permission (権限)

**Mục đích:** Định nghĩa quyền hạn cụ thể. Permissions là global (không scoped theo org).

| Property | Type | Description |
|----------|------|-------------|
| `id` | UUID | Primary key |
| `name` | String(100) | Tên quyền (unique) |
| `slug` | String(100) | Slug (unique) |
| `group` | String(50) | Nhóm quyền (nullable) |

**Relationships:**
- ManyToMany: `Role` (via `role_permissions`)

**Naming Convention:**
```
{resource}.{action}

Examples:
- users.view
- users.create
- users.update
- users.delete
- reports.export
- settings.manage
```

**Groups:**
```yaml
- group: users
  permissions: [users.view, users.create, users.update, users.delete]

- group: reports
  permissions: [reports.view, reports.export]

- group: settings
  permissions: [settings.view, settings.manage]
```

---

## 7. RolePermission (ロール権限)

**Mục đích:** Pivot table liên kết Role với Permission.

| Property | Type | Description |
|----------|------|-------------|
| `role_id` | UUID | FK → roles |
| `permission_id` | UUID | FK → permissions |

**Options:**
- `id: false` - Không có auto-increment ID
- `unique: [role, permission]` - Composite unique constraint

**Ví dụ:**
```
Role "Admin" → Permissions:
├── users.view
├── users.create
├── users.update
├── users.delete
└── reports.view

Role "Staff" → Permissions:
├── users.view
└── reports.view
```

---

## 8. TeamPermission (チーム権限)

**Mục đích:** Gán quyền trực tiếp cho team (không qua role). Cho phép flexible permission assignment.

| Property | Type | Description |
|----------|------|-------------|
| `id` | UUID | Primary key |
| `console_organization_id` | UUID | ID tổ chức |
| `console_team_id` | UUID | ID team |
| `permission_id` | UUID | FK → permissions |

**Indexes:**
- `(console_organization_id, console_team_id)`

**Relationships:**
- BelongsTo: `Permission`

**Use case:**
```
Team "Sales HN" có quyền đặc biệt:
├── customers.view (từ Role)
├── customers.export (team-specific, không có trong role)
└── reports.sales (team-specific)
```

---

## Entity Relationship Diagram

```
┌─────────────────────┐
│ Organization   │
│ (console_organization_id)    │
└─────────┬───────────┘
          │
          │ 1:N
          │
    ┌─────┴─────┬─────────────┐
    │           │             │
    ▼           ▼             ▼
┌────────┐ ┌────────┐   ┌──────────┐
│ Branch │ │ Team   │   │ User│
│ Cache  │ │ Cache  │   │          │
└────────┘ └───┬────┘   └────┬─────┘
               │             │
               │             │ N:M (pivot: role_user)
               │             │ with: console_organization_id, console_branch_id
               ▼             ▼
         ┌─────────┐    ┌─────────┐
         │ Team    │    │  Role   │
         │Permission│   │         │
         └────┬────┘    └────┬────┘
              │              │
              │              │ N:M (pivot: role_permissions)
              │              │
              └──────┬───────┘
                     ▼
               ┌──────────┐
               │Permission│
               └──────────┘
```

---

## Permission Check Flow

```
User Request → Check Permission
    │
    ├─── 1. Get User's Roles (scoped by org/branch)
    │         └── role_user pivot
    │
    ├─── 2. Get Permissions from Roles
    │         └── role_permissions pivot
    │
    ├─── 3. Get Team Permissions (if user in team)
    │         └── team_permissions table
    │
    └─── 4. Merge all permissions → Final permission set
```

**Code example:**
```php
public function hasPermission(User $user, string $permission, ?string $organizationId = null): bool
{
    // 1. Check role-based permissions
    $hasRolePermission = $user->roles()
        ->when($organizationId, fn($q) => $q->wherePivot('console_organization_id', $organizationId))
        ->whereHas('permissions', fn($q) => $q->where('slug', $permission))
        ->exists();

    if ($hasRolePermission) return true;

    // 2. Check team-based permissions
    $userTeams = $user->teams()->pluck('console_team_id');
    return TeamPermission::whereIn('console_team_id', $userTeams)
        ->whereHas('permission', fn($q) => $q->where('slug', $permission))
        ->exists();
}
```

---

## Sync từ Console

Các Cache models được đồng bộ từ Console API:

```php
// Event listener for Console webhook
class ConsoleWebhookHandler
{
    public function handleOrganizationUpdated(array $data): void
    {
        Organization::updateOrCreate(
            ['console_organization_id' => $data['id']],
            [
                'name' => $data['name'],
                'code' => $data['code'],
                'is_active' => $data['is_active'],
            ]
        );
    }
}
```

**Sync frequency:**
- Real-time via webhooks (preferred)
- Fallback: Scheduled job every 15 minutes

---

## Related Documentation

- [Authentication Guide](../guides/authentication.md)
- [Authorization Guide](../guides/authorization.md)
- [Branch Permissions Design](../architecture/branch-permissions-design.md)
