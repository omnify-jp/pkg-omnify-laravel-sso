# SSO Data Sync Architecture

> **Status: ✅ IMPLEMENTED** (2026-03-01)
>
> Data flow: Console (source of truth) → local DB (cache) → Inertia shared props (delivery) → React UI

## Overview

Khi host app (boilerplate/dxs-task/...) chạy ở **console mode** (`OMNIFY_AUTH_MODE=console`), toàn bộ dữ liệu tổ chức (organizations, branches, locations) được quản lý bởi **Omnify Console** — host app chỉ giữ bản cache trong local DB.

```
┌──────────────────────────────────────────────────────────────────┐
│                    Omnify Console (IDP)                           │
│                                                                  │
│   organizations ─┐                                               │
│   branches ──────┤  Source of truth                               │
│   locations ─────┘                                               │
│                                                                  │
│   API: /api/sso/organizations                                    │
│   API: /api/sso/branches?organization_slug=...                   │
│   API: /api/sso/locations?organization_slug=...                  │
└──────────────────────────┬───────────────────────────────────────┘
                           │ OAuth JWT + API calls
                           ▼
┌──────────────────────────────────────────────────────────────────┐
│                    Host App (e.g. dxs-task)                       │
│                                                                  │
│   ┌─────────────────────────────────────────────────────────┐    │
│   │ SsoCallbackController                                    │    │
│   │  → getOrganizations()  →  cache orgs to local DB         │    │
│   │  → syncBranches()      →  cache branches to local DB     │    │
│   └──────────────────────────────┬──────────────────────────┘    │
│                                  │                               │
│   ┌──────────────────────────────▼──────────────────────────┐    │
│   │ Local Database (SQLite/MySQL)                            │    │
│   │                                                          │    │
│   │  organizations  ← is_standalone=false (console-synced)   │    │
│   │  branches       ← is_standalone=false (console-synced)   │    │
│   │  locations      ← is_standalone=false (console-synced)   │    │
│   └──────────────────────────────┬──────────────────────────┘    │
│                                  │                               │
│   ┌──────────────────────────────▼──────────────────────────┐    │
│   │ CoreHandleInertiaRequests (middleware)                    │    │
│   │  → Query local DB                                        │    │
│   │  → Share via Inertia props: { organization: {...} }      │    │
│   └──────────────────────────────┬──────────────────────────┘    │
│                                  │                               │
│   ┌──────────────────────────────▼──────────────────────────┐    │
│   │ React UI (Org Selector Modal)                            │    │
│   │  → Reads Inertia props (NO separate API call)            │    │
│   │  → Shows org list + branch counts                        │    │
│   │  → User selects org + branch → cookies saved             │    │
│   └─────────────────────────────────────────────────────────┘    │
└──────────────────────────────────────────────────────────────────┘
```

---

## 1. SSO Login — Data Sync Trigger

Branch/org data sync xảy ra **1 lần duy nhất khi user login qua SSO**. Không có background sync hay polling.

### Sequence

```
Browser                    Host App                         Console
  │                          │                                 │
  │  click "SSO Login"       │                                 │
  │ ────────────────────────>│                                 │
  │                          │  redirect to Console /sso/auth  │
  │ <────────────────────────│────────────────────────────────>│
  │                          │                                 │
  │  (user logs in on Console, gets authorization code)        │
  │ ────────────────────────>│                                 │
  │  POST /api/sso/callback  │                                 │
  │  { code: "..." }         │                                 │
  │                          │                                 │
  │                          │  1. POST /api/sso/token         │
  │                          │     { code, service_slug }      │
  │                          │────────────────────────────────>│
  │                          │  ← { access_token, refresh }    │
  │                          │<────────────────────────────────│
  │                          │                                 │
  │                          │  2. Verify JWT claims            │
  │                          │  3. Find/create User             │
  │                          │  4. Store tokens on User model   │
  │                          │                                 │
  │                          │  5. GET /api/sso/organizations   │
  │                          │     Authorization: Bearer {jwt}  │
  │                          │────────────────────────────────>│
  │                          │  ← [{ org_id, slug, name }]     │
  │                          │<────────────────────────────────│
  │                          │  → cache orgs to local DB        │
  │                          │                                 │
  │                          │  6. GET /api/sso/branches (×N)   │
  │                          │     ?organization_slug={slug}    │
  │                          │────────────────────────────────>│
  │                          │  ← { branches: [...] }          │
  │                          │<────────────────────────────────│
  │                          │  → cache branches to local DB    │
  │                          │                                 │
  │  ← { user, organizations }                                 │
  │<─────────────────────────│                                 │
  │  Auth::login() + session │                                 │
```

### Files involved

| Step | File | Method |
|------|------|--------|
| 1-4 | `core/Http/Controllers/Api/SsoCallbackController.php` | `callback()` |
| 5 | `core/Services/OrganizationAccessService.php` | `getOrganizations()` → `cacheOrganizations()` |
| 6 | `core/Services/OrganizationAccessService.php` | `syncBranches()` → `cacheBranches()` |

---

## 2. ID Mapping — Console vs Host App

Mỗi model có **2 ID**: local primary key (`id`, auto-generated UUID) và Console reference (`console_*_id`).

```
Console DB                              Host App DB
──────────                              ───────────

organizations                           organizations
  id: 019caa4a-...  ──────────────────>  console_organization_id: 019caa4a-...
  slug: "abc-tech"                       id: 019cab59-... (auto-generated)
  name: "ABC Corp"                       slug: "abc-tech"

branches                                branches
  id: 01e8f1c0-...  ──────────────────>  console_branch_id: 01e8f1c0-...
  console_organization_id: 019e8a3b-...  console_organization_id: 019caa4a-... ← Console org.id
  slug: "ha-noi"                         id: 019cab60-... (auto-generated)
  name: "Hà Nội"                         slug: "ha-noi"
```

### Quan trọng: `console_organization_id` có nghĩa khác nhau

| Vị trí | `console_organization_id` chứa gì |
|--------|-----------------------------------|
| **Console** org | Seeder constant UUID (e.g. `019e8a3b-...`) — dùng nội bộ |
| **Console** branch | Cùng seeder constant UUID — link branch ↔ org trong Console |
| **Host app** org | Console org's `id` (auto UUID, e.g. `019caa4a-...`) — từ API response |
| **Host app** branch | Console org's `id` (auto UUID) — từ API response `organization.id` |

**Rule**: Host app branches link tới host app orgs qua `console_organization_id` — cả hai lưu cùng giá trị (Console org's primary key `id`).

---

## 3. Inertia Shared Props — Page Load Delivery

Mỗi page load, middleware share org/branch data từ local DB qua Inertia props.

### Backend: `CoreHandleInertiaRequests`

```php
// core/Http/Middleware/CoreHandleInertiaRequests.php

protected function buildOrganizationData(Request $request): array
{
    // 1. Get ALL active organizations from local DB
    $organizations = Organization::where('is_active', true)
        ->select(['id', 'console_organization_id', 'name', 'slug'])
        ->get();

    // 2. Get ALL active branches matching those orgs
    $branches = Branch::whereIn(
            'console_organization_id',
            $organizations->pluck('console_organization_id')
        )
        ->where('is_active', true)
        ->select([...])
        ->get();

    // 3. Resolve current org/branch from cookies
    $currentOrg = /* from cookie current_organization_id */;
    $currentBranch = /* from cookie current_branch_id */;

    return [
        'current' => $currentOrg,
        'slug' => $currentOrg?->slug,
        'list' => $organizations,
        'currentBranch' => $currentBranch,
        'branches' => $branches,     // ALL branches across ALL orgs
    ];
}
```

### Frontend: `app-layout.tsx`

```tsx
// boilerplate/resources/js/layouts/app-layout.tsx

// Inertia props → OrganizationProvider
const orgData = useMemo(() => ({
    current: organization.current,
    branch: organization.currentBranch,
    organizations: organization.list,
    branches: (organization.branches ?? []).map((b) => ({
        ...b,
        // Map console_organization_id → org local id (cho filtering)
        organization_id: orgLookup.get(b.console_organization_id)
            ?? b.console_organization_id,
    })),
}), [organization, orgLookup]);

<OrganizationProvider
    data={orgData}
    requireBranch
    onOrganizationChange={handleOrganizationChange}
    onBranchChange={handleBranchChange}
/>
```

### Org Selector Modal Logic

```
organization-selection-modal.tsx

Step 1: Org List
  → organizations.map(org => ({
      ...org,
      branchCount: branches.filter(b => b.organization_id === org.id).length
    }))
  → User chọn org → tempOrgId = org.id

Step 2: Branch List
  → availableBranches = branches.filter(b => b.organization_id === tempOrgId)
  → User chọn branch → onBranchChange(branch)

Selection Saved:
  → Cookie: current_organization_id = org.console_organization_id
  → Cookie: current_organization_slug = org.slug
  → Cookie: current_branch_id = branch.id
  → router.reload() (cookie-only mode) hoặc router.visit('/@{slug}/dashboard') (URL mode)
```

---

## 4. Standalone Mode vs Console Mode

`HasStandaloneScope` trait tự động filter data theo mode.

| | Standalone Mode | Console Mode |
|---|---|---|
| `OMNIFY_AUTH_MODE` | `standalone` | `console` |
| Global scope | Không filter | Auto-filter `is_standalone=false` |
| Tạo record mới | `is_standalone=true` | `is_standalone=false` |
| Data source | Local (seeders, admin CRUD) | Console API → local cache |
| Branch sync | Không (managed locally) | SSO callback → Console API |

### Global Scope Implementation

```php
// core/Models/Traits/HasStandaloneScope.php

public static function bootHasStandaloneScope(): void
{
    // Console mode: chỉ show data synced từ Console
    if (config('omnify-auth.mode') === 'console') {
        static::addGlobalScope('standalone_mode', function (Builder $builder) {
            $table = (new static)->getTable();
            $builder->where("{$table}.is_standalone", false);
        });
    }

    // Auto-set is_standalone on creation
    static::creating(function ($model) {
        if (! isset($model->is_standalone)) {
            $model->is_standalone = config('omnify-auth.mode') === 'standalone';
        }
    });
}
```

### Bypass scope khi sync

Khi cache data từ Console, PHẢI bypass global scope để tìm existing records:

```php
// ✅ Đúng — bypass scope + set is_standalone=false
Organization::withoutGlobalScope('standalone_mode')
    ->withTrashed()
    ->updateOrCreate(
        ['console_organization_id' => $consoleOrgId],
        ['is_standalone' => false, ...]
    );

// ❌ Sai — global scope filter ẩn existing records → tạo duplicate
Organization::updateOrCreate(
    ['console_organization_id' => $consoleOrgId],
    [...]
);
```

---

## 5. On-Demand Branch API (Backup Path)

Ngoài SSO callback sync, có API endpoint để fetch branches on-demand:

```
GET /api/sso/branches
Headers: X-Organization-Id: {org_id} (hoặc query param)
Auth: Sanctum session

Flow:
1. Resolve org từ header/query/cookie
2. Get user's Console access token (auto-refresh nếu sắp hết hạn)
3. Gọi Console API: GET /api/sso/branches?organization_slug={slug}
4. Cache kết quả vào local DB
5. Return branches to client

Fallback: Nếu Console không available → trả về data từ local DB cache
```

### File: `core/Http/Controllers/Api/SsoBranchController.php`

Endpoint này **KHÔNG được gọi bởi org selector modal**. Nó tồn tại cho:
- Mobile apps (API token auth)
- Manual refresh triggers
- Future: periodic sync jobs

---

## 6. Console API Endpoints (Server-side)

Console expose các endpoint sau cho host apps:

| Endpoint | Auth | Purpose |
|----------|------|---------|
| `POST /api/sso/token` | Service slug + code | Exchange auth code → JWT + refresh token |
| `POST /api/sso/refresh` | Refresh token | Refresh access token |
| `GET /api/sso/organizations` | Bearer JWT | List user's orgs for this service |
| `GET /api/sso/branches` | Bearer JWT | List branches in org |
| `GET /api/sso/locations` | Bearer JWT | List locations in org/branch |
| `GET /api/sso/access` | Bearer JWT | Get user's role/permissions in org |
| `GET /api/sso/teams` | Bearer JWT | Get user's teams in org |
| `GET /api/sso/.well-known/jwks.json` | Public | JWKS for JWT verification |

### Branch Response Format

```json
{
  "all_branches_access": true,
  "branches": [
    {
      "id": "019caa4a-...",
      "slug": "ha-noi",
      "code": "HAN",
      "name": "Hà Nội (Trụ sở chính)",
      "is_headquarters": true,
      "is_primary": false,
      "is_assigned": true,
      "access_type": "explicit",
      "timezone": "Asia/Ho_Chi_Minh",
      "currency": null,
      "locale": null
    }
  ],
  "primary_branch_id": "019caa4a-...",
  "organization": {
    "id": "019caa4a-...",
    "slug": "abc-tech",
    "name": "Công ty CP Giải Pháp Công Nghệ ABC"
  }
}
```

---

## 7. Token Management

Console tokens được lưu encrypted trên User model:

| Column | Type | Purpose |
|--------|------|---------|
| `console_access_token` | encrypted string | JWT access token (15 min TTL) |
| `console_refresh_token` | encrypted string | Refresh token (30 day TTL) |
| `console_token_expires_at` | datetime | Access token expiry |

### Auto-refresh

```php
// core/Services/ConsoleTokenService.php

public function getAccessToken(Model $user): ?string
{
    // Nếu token còn hạn > 5 min → return ngay
    if ($this->isTokenValid($user)) {
        return decrypt($user->console_access_token);
    }

    // Nếu sắp hết hạn hoặc đã hết → refresh
    return $this->refreshIfNeeded($user);
}
```

---

## 8. File Reference

### Console (IDP)

| File | Purpose |
|------|---------|
| `console/app/Http/Controllers/Api/External/Sso/AccessController.php` | branches/orgs/access API |
| `console/app/Http/Controllers/Api/External/Sso/TokenController.php` | Token exchange/refresh |
| `console/app/Services/Sso/AccessService.php` | Resolve user authorization |
| `console/app/Services/Sso/JwtService.php` | JWT creation/verification |

### Core Package (Host App side)

| File | Purpose |
|------|---------|
| `core/Http/Controllers/Api/SsoCallbackController.php` | SSO login callback — sync orgs + branches |
| `core/Http/Controllers/Api/SsoBranchController.php` | On-demand branch API (backup) |
| `core/Services/OrganizationAccessService.php` | Org fetch + cache + branch sync |
| `core/Services/ConsoleApiService.php` | HTTP client for Console API |
| `core/Services/ConsoleTokenService.php` | Token storage + auto-refresh |
| `core/Http/Middleware/CoreHandleInertiaRequests.php` | Share org/branch data via Inertia props |
| `core/Models/Traits/HasStandaloneScope.php` | Global scope: console mode filter |
| `core/Models/Traits/HasConsoleSso.php` | User model: token storage columns |

### Frontend

| File | Purpose |
|------|---------|
| `core/resources/js/contexts/organization-context.tsx` | React context provider |
| `core/resources/js/components/organization-selection-modal.tsx` | 2-step org+branch selector |
| `boilerplate/resources/js/layouts/app-layout.tsx` | Inertia props → OrganizationProvider |

---

## 9. Troubleshooting

### Org selector shows "0拠点" (0 branches)

**Nguyên nhân phổ biến:**
1. SSO callback chưa sync branches (check `OrganizationAccessService::syncBranches()`)
2. Stale data từ Console cũ (sau `migrate:fresh`) — branches có `console_organization_id` cũ
3. Global scope ẩn data — branches có `is_standalone=true` nhưng đang ở console mode

**Debug:**
```bash
# Check local branches
php artisan tinker --execute="
\$orgs = \Omnify\Core\Models\Organization::all();
foreach (\$orgs as \$o) {
    \$count = \Omnify\Core\Models\Branch
        ::where('console_organization_id', \$o->console_organization_id)
        ->count();
    echo \$o->name . ': ' . \$count . ' branches' . PHP_EOL;
}
"
```

### SSO login tạo duplicate user

**Nguyên nhân:** Console `migrate:fresh` đổi user UUID → `console_user_id` không match → tạo user mới.

**Fix:** Xóa user cũ hoặc reset host app DB:
```bash
php artisan migrate:fresh --seed
```

### Token expired, refresh fails

**Nguyên nhân:** Console `migrate:fresh` invalidate tất cả refresh tokens.

**Fix:** Re-login via SSO để nhận tokens mới.
