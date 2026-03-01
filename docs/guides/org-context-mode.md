# Organization Context Mode

Core package hỗ trợ 2 mode để resolve organization context, cấu hình qua `OMNIFY_ORG_ROUTE_PREFIX`.

## Tổng quan

| | URL Mode | Cookie-Only Mode |
|---|---|---|
| **Config** | `OMNIFY_ORG_ROUTE_PREFIX=@{organization}` | `OMNIFY_ORG_ROUTE_PREFIX=` (empty) |
| **Route ví dụ** | `/@acme/dashboard`, `/@acme/settings/iam` | `/dashboard`, `/settings/iam` |
| **Middleware** | `core.org.url` (ResolveOrganizationFromUrl) | `core.standalone.org` (StandaloneOrganizationContext) |
| **Org switcher** | Navigate tới `/@{new-slug}/dashboard` | Set cookie + reload page |
| **Inertia prop** | `org_url_mode = true` | `org_url_mode = false` |

Cả 2 mode đều dùng cookie (`current_organization_id`) để lưu org state. URL mode tự set cookie khi user vào URL. Chuyển mode chỉ cần đổi env var — không cần sửa code.

## Cách hoạt động

### URL Mode (`@{organization}`)

```
User truy cập /@acme/dashboard
  → Route match: /@{organization}/dashboard
  → ResolveOrganizationFromUrl middleware:
      1. Đọc {organization} = "acme" từ route parameter
      2. Query DB: Organization::where('slug', 'acme')->where('is_active', true)
      3. Set request attribute: organizationId = org.id
      4. Set response cookies: current_organization_id, current_organization_slug
  → Controller nhận request với organizationId đã resolve
```

### Cookie-Only Mode (empty)

```
User truy cập /dashboard
  → Route match: /dashboard (không có prefix)
  → StandaloneOrganizationContext middleware:
      1. Priority 1: Đọc cookie current_organization_id → query DB
      2. Priority 2: Đọc user->console_organization_id → query DB
      3. Priority 3: Lấy first active org
      4. Set request attribute: organizationId = org.id
  → Controller nhận request với organizationId đã resolve
```

## Setup

### 1. Env configuration

```env
# URL mode (org slug trong URL)
OMNIFY_ORG_ROUTE_PREFIX=@{organization}

# Cookie-only mode (không có org slug trong URL)
OMNIFY_ORG_ROUTE_PREFIX=
```

### 2. Host app bootstrap (bootstrap/app.php)

Host app cần register org-scoped routes theo mode:

```php
$orgPrefix = env('OMNIFY_ORG_ROUTE_PREFIX', '');
$orgMiddleware = ['web', 'auth', '2fa'];

if ($orgPrefix !== '') {
    // URL mode: /@{slug}/dashboard, etc.
    $orgMiddleware[] = 'core.org.url';
    Route::middleware($orgMiddleware)
        ->prefix($orgPrefix)
        ->group(base_path('routes/user/org.php'));
} else {
    // Cookie-only mode: /dashboard, etc.
    $orgMiddleware[] = 'core.standalone.org';
    Route::middleware($orgMiddleware)
        ->group(base_path('routes/user/org.php'));
}
```

### 3. Home route redirect

```php
Route::get('/', function () {
    $orgPrefix = config('omnify-auth.routes.org_route_prefix', '');

    if ($orgPrefix !== '') {
        // URL mode: redirect tới /@{slug}/dashboard
        $slug = request()->cookie('current_organization_slug');
        if (! $slug) {
            $org = Organization::where('is_active', true)->first();
            $slug = $org?->slug ?? 'default';
        }
        return redirect("/@{$slug}/dashboard");
    }

    // Cookie-only mode: redirect thẳng /dashboard
    return redirect()->route('dashboard');
})->name('home');
```

## Frontend Integration

### Inertia shared prop: `org_url_mode`

Core's `CoreHandleInertiaRequests` tự động share `org_url_mode` (boolean):

```ts
const { org_url_mode } = usePage<{ org_url_mode: boolean }>().props;
```

### Hook: `useOrgRoute()` — tạo org-scoped URL

Trả về function tạo URL cho org-scoped routes. Tự động xử lý prefix theo mode:

```ts
import { useOrgRoute } from '@omnify-core/hooks/use-org-route';

function MyComponent() {
    const orgRoute = useOrgRoute();

    // URL mode:    orgRoute('/dashboard') → '/@acme/dashboard'
    // Cookie-only: orgRoute('/dashboard') → '/dashboard'

    return (
        <>
            <Link href={orgRoute('/dashboard')}>Dashboard</Link>
            <Link href={orgRoute('/settings/iam')}>IAM</Link>
        </>
    );
}
```

### Hook: `useOrgSwitch()` — switch organization

Trả về function để chuyển org. Tự động set cookies + navigate/reload theo mode:

```ts
import { useOrgSwitch } from '@omnify-core/hooks/use-org-switch';

function MyOrgSwitcher() {
    const switchOrg = useOrgSwitch();

    // URL mode:    set cookies → router.visit('/@{slug}/dashboard')
    // Cookie-only: set cookies → router.reload()
    const handleChange = (slug: string, consoleOrgId: string) => {
        switchOrg(slug, consoleOrgId);
        // Optional: switchOrg(slug, consoleOrgId, '/settings') — redirect tới path khác
    };
}
```

### Hook: `useOrgPrefix()` (low-level)

Trả về raw URL prefix. Dùng `useOrgRoute()` thay vì hook này trừ khi cần prefix trực tiếp:

```ts
import { useOrgPrefix } from '@omnify-core/hooks/use-org-prefix';

const prefix = useOrgPrefix();
// URL mode: "/@acme"
// Cookie-only: ""
```

## Middleware Aliases

| Alias | Class | Mode |
|---|---|---|
| `core.org.url` | `ResolveOrganizationFromUrl` | URL mode |
| `core.standalone.org` | `StandaloneOrganizationContext` | Cookie-only mode |

Core package tự động apply đúng middleware theo config trong `CoreServiceProvider::withOrgPrefix()`. Host app chỉ cần config trong `bootstrap/app.php`.

## Cookie Resolution Priority (Cookie-Only Mode)

`StandaloneOrganizationContext` resolve org theo thứ tự:

1. **Cookie** `current_organization_id` — set bởi org switcher hoặc URL mode middleware
2. **User default** — `$user->console_organization_id` từ users table
3. **First active org** — fallback khi user chưa có default

Nếu org từ cookie inactive hoặc không tồn tại → fallback xuống priority tiếp theo.

## Routes không bị ảnh hưởng

Một số routes LUÔN không có org prefix, bất kể mode:

- **Auth routes**: `/login`, `/register`, `/sso/login`, `/sso/callback`
- **API routes**: `/api/user/*`, `/api/admin/*`
- **Admin routes**: `/admin/*` (standalone mode only)
- **Settings route**: `/settings/*` (user-level, không org-scoped)

## Testing

### Feature test — middleware unit test

```php
use Omnify\Core\Http\Middleware\StandaloneOrganizationContext;

it('resolves org from cookie', function () {
    $org = Organization::factory()->create(['is_active' => true, 'is_standalone' => true]);
    $user = User::factory()->create();

    $middleware = new StandaloneOrganizationContext;
    $request = Request::create('/test', 'GET', [], [
        'current_organization_id' => $org->console_organization_id,
    ]);
    $request->setUserResolver(fn () => $user);

    $middleware->handle($request, function ($req) use ($org) {
        expect($req->attributes->get('organizationId'))->toBe($org->id);
        return new Response;
    });
});
```

### Browser test — URL mode

```php
it('dashboard loads at /@{slug}/dashboard', function () {
    $org = Organization::factory()->create(['slug' => 'test-org', 'is_active' => true]);
    $user = User::factory()->create(['console_organization_id' => $org->console_organization_id]);
    $this->actingAs($user);

    visit('/@test-org/dashboard')
        ->assertNoJavaScriptErrors()
        ->assertPathIs('/@test-org/dashboard');
});
```

### Browser test — Cookie-only mode

Cookie-only mode cần project cấu hình `OMNIFY_ORG_ROUTE_PREFIX=` (empty) vì routes được register lúc bootstrap bằng `env()` — thay đổi `config()` runtime không ảnh hưởng route registration.

```php
// Chạy trong project có OMNIFY_ORG_ROUTE_PREFIX= (e.g., dxs-task)
it('dashboard loads at /dashboard', function () {
    $org = Organization::factory()->create(['is_active' => true]);
    $user = User::factory()->create(['console_organization_id' => $org->console_organization_id]);
    $this->actingAs($user);

    visit('/dashboard')
        ->assertNoJavaScriptErrors()
        ->assertPathIs('/dashboard');
});
```
