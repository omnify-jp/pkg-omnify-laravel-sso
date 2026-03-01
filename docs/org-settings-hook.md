# Org Settings Hub — Hook Architecture

## Tổng quan

Trang **Organization Settings** (`/@{org}/settings`) là hub page do Core package quản lý. Các package khác (Workflow, v.v.) đăng ký section vào hub thông qua cơ chế **config hook** — không cần sửa code Core.

---

## Flow tổng thể

```
┌─ PHP BOOT ──────────────────────────────────────────────┐
│                                                          │
│  1. Core mergeConfig: omnify-auth.org_settings           │
│     └─ extra_sections = []  (rỗng mặc định)             │
│                                                          │
│  2. Workflow ServiceProvider::boot()                     │
│     └─ registerOrgSettingsSection()                      │
│        └─ Đọc config → push section → ghi lại config    │
│                                                          │
│  3. Request /@{org}/settings                             │
│     └─ AccessPageController::orgSettingsIndex()          │
│        ├─ $sections = [IAM built-in]                     │
│        ├─ $extra = config('omnify-auth.org_settings...') │
│        ├─ array_merge($sections, $extra)                 │
│        └─ Inertia::render('org-settings', $sections)     │
│                                                          │
└──────────────────────────────────────────────────────────┘
                        │
                        │ Inertia Props
                        ▼
┌─ REACT FRONTEND ────────────────────────────────────────┐
│                                                          │
│  4. org-settings.tsx nhận sections[]                     │
│     └─ Render cards: icon + title + description          │
│        └─ Link → /@{org}/{section.path_suffix}           │
│                                                          │
│  5. Click card → navigate đến section page               │
│     └─ resolve-page.tsx match prefix → inject layout     │
│        └─ OrgSettingsLayout wrap page với tabs            │
│                                                          │
└──────────────────────────────────────────────────────────┘
```

---

## Chi tiết từng bước

### 1. Config hook point

```php
// config/omnify-auth.php
'org_settings' => [
    'extra_sections' => [],   // ← Hook point: packages push vào đây
],
```

### 2. Package đăng ký section (ví dụ Workflow)

```php
// WorkflowServiceProvider.php
private function registerOrgSettingsSection(): void
{
    if (! config('workflow.admin.enabled', true)) {
        return;
    }

    $sections = config('omnify-auth.org_settings.extra_sections', []);
    $sections[] = [
        'key'               => 'workflow',
        'icon'              => 'git-pull-request',     // Lucide icon name
        'title_key'         => 'workflow.title',       // i18n key
        'title_default'     => 'Workflow',             // fallback
        'description_key'   => 'workflow.subtitle',
        'description_default' => 'Manage approval workflows and definitions.',
        'path_suffix'       => config('workflow.admin.prefix', 'settings/workflow'),
    ];
    config(['omnify-auth.org_settings.extra_sections' => $sections]);
}
```

**Quan trọng:**
- Gọi trong `boot()` (không phải `register()`) — config Core đã merge xong
- Dùng `title_key`/`title_default` thay vì `__()` — tránh lỗi `Translator::get()` nhận string thay vì array
- `path_suffix` không có leading `/` — sẽ ghép thành `/@{org}/{path_suffix}`

### 3. Controller merge sections

```php
// AccessPageController::orgSettingsIndex()
public function orgSettingsIndex(): Response
{
    // IAM luôn có (built-in Core)
    $sections = [
        [
            'key'               => 'iam',
            'icon'              => 'shield',
            'title_key'         => 'iam.title',
            'title_default'     => 'Identity & Access Management',
            'description_key'   => 'iam.subtitle',
            'description_default' => 'Manage users, roles, and permissions.',
            'path_suffix'       => config('omnify-auth.routes.access_prefix', 'settings/iam'),
        ],
    ];

    // Merge sections từ packages khác
    $extraSections = config('omnify-auth.org_settings.extra_sections', []);
    $sections = array_merge($sections, $extraSections);

    return Inertia::render('org-settings', ['sections' => $sections]);
}
```

### 4. Route registration với org prefix

Core wraps routes dưới org prefix:

```php
// CoreServiceProvider.php
$this->withOrgPrefix(function () {
    $this->loadRoutesFrom(__DIR__.'/../routes/access.php');
});

protected function withOrgPrefix(callable $callback): void
{
    $orgPrefix = config('omnify-auth.routes.org_route_prefix', '');

    if ($orgPrefix !== '') {
        Route::prefix($orgPrefix)          // @{organization}
            ->middleware(['core.org.url'])  // Resolve org from URL
            ->group($callback);
    } else {
        $callback();                       // Cookie-only mode
    }
}
```

**Packages cũng wrap tương tự:**

```php
// WorkflowServiceProvider::registerAdminRoutes()
$register = function () {
    Route::middleware(config('workflow.admin.middleware', ['web', 'auth']))
        ->prefix(config('workflow.admin.prefix', 'settings/workflow'))
        ->group(__DIR__.'/../routes/admin.php');
};

$orgPrefix = config('omnify-auth.routes.org_route_prefix', '');
if ($orgPrefix !== '') {
    Route::prefix($orgPrefix)
        ->middleware(['core.org.url'])
        ->group($register);
} else {
    $register();
}
```

### 5. React page render

```tsx
// org-settings.tsx (Core package)
type Section = {
    key: string;
    icon: string;
    title_key: string;
    title_default: string;
    description_key: string;
    description_default: string;
    path_suffix: string;
};

const ICON_MAP: Record<string, LucideIcon> = {
    shield: Shield,
    'git-pull-request': GitPullRequest,
};

export default function OrgSettings({ sections }: { sections: Section[] }) {
    const { t } = useTranslation();
    const orgPrefix = /* extract from usePage() */;

    return sections.map(section => {
        const Icon = ICON_MAP[section.icon] ?? Shield;
        return (
            <Link href={`${orgPrefix}/${section.path_suffix}`}>
                <Card>
                    <Icon />
                    <Text strong>{t(section.title_key, section.title_default)}</Text>
                    <Text type="secondary">{t(section.description_key, section.description_default)}</Text>
                </Card>
            </Link>
        );
    });
}
```

### 6. Layout context injection

```tsx
// resolve-page.tsx (boilerplate)
const contexts: CtxEntry[] = [
    ['settings/',          PageLayoutContext,     SettingsAppLayout],
    ['settings/iam/',      PageLayoutContext,     OrgSettingsLayout],
    ['settings/workflow/', PageLayoutContext,     OrgSettingsLayout],
    ['settings/workflow/', WorkflowLayoutContext, OrgSettingsLayout],
];
```

Khi page name bắt đầu với `settings/workflow/`:
1. Match `settings/` → SettingsAppLayout (bị override bởi entry sau)
2. Match `settings/workflow/` → OrgSettingsLayout (override)
3. `PageContainer` trong page dùng `usePageLayout()` → lấy OrgSettingsLayout

### 7. OrgSettingsLayout (boilerplate)

Layout phân biệt IAM vs Workflow qua URL:

```tsx
const isWorkflow = url.startsWith(workflowPath);

// Tabs khác nhau theo section
if (isWorkflow) {
    tabs = [Overview, Definitions, Instances];
    breadcrumb = 'Organization Settings / Workflow';
} else {
    tabs = [Overview, Users, Roles, Permissions];
    breadcrumb = 'Organization Settings / IAM';
}
```

---

## Thêm package mới (step-by-step)

### Ví dụ: thêm package `pkg-omnify-laravel-notification`

**1. ServiceProvider — đăng ký section:**

```php
private function registerOrgSettingsSection(): void
{
    $sections = config('omnify-auth.org_settings.extra_sections', []);
    $sections[] = [
        'key'                => 'notification',
        'icon'               => 'bell',
        'title_key'          => 'notification.title',
        'title_default'      => 'Notifications',
        'description_key'    => 'notification.subtitle',
        'description_default'=> 'Configure notification channels and templates.',
        'path_suffix'        => 'settings/notification',
    ];
    config(['omnify-auth.org_settings.extra_sections' => $sections]);
}
```

**2. ServiceProvider — đăng ký routes với org prefix:**

```php
private function registerAdminRoutes(): void
{
    $register = fn() => Route::middleware(['web', 'auth'])
        ->prefix('settings/notification')
        ->group(__DIR__.'/../routes/admin.php');

    $orgPrefix = config('omnify-auth.routes.org_route_prefix', '');
    if ($orgPrefix !== '') {
        Route::prefix($orgPrefix)->middleware(['core.org.url'])->group($register);
    } else {
        $register();
    }
}
```

**3. Tạo React pages:**

```
resources/js/pages/settings/notification/
├── overview.tsx
├── channels.tsx
└── templates.tsx
```

**4. org-settings.tsx — thêm icon mapping:**

```tsx
// Hoặc host app có thể override org-settings.tsx
const ICON_MAP: Record<string, LucideIcon> = {
    shield: Shield,
    'git-pull-request': GitPullRequest,
    bell: Bell,   // ← thêm icon mới
};
```

**5. resolve-page.tsx (boilerplate) — thêm context:**

```tsx
const contexts: CtxEntry[] = [
    // ... existing
    ['settings/notification/', PageLayoutContext, OrgSettingsLayout],
];
```

**6. OrgSettingsLayout — thêm tabs cho notification:**

```tsx
const isNotification = url.startsWith(notificationPath);
if (isNotification) {
    tabs = [Overview, Channels, Templates];
    breadcrumb = 'Organization Settings / Notifications';
}
```

**Done!** Card mới sẽ xuất hiện trên hub page, navigate đến section có tabs riêng.

---

## Section schema

| Field               | Type   | Description                              |
|---------------------|--------|------------------------------------------|
| `key`               | string | Unique identifier (slug)                 |
| `icon`              | string | Lucide icon name (kebab-case)            |
| `title_key`         | string | i18n translation key cho title           |
| `title_default`     | string | Fallback title (English)                 |
| `description_key`   | string | i18n translation key cho description     |
| `description_default` | string | Fallback description (English)         |
| `path_suffix`       | string | URL suffix (e.g. `settings/workflow`)    |

**Lưu ý:** KHÔNG dùng `__()` helper cho title/description trong PHP — dùng `title_key`/`title_default` và để React `t()` xử lý i18n.
