# SSO Package Architecture Documentation

## Package Structure

```
packages/laravel/omnify-client-laravel-sso/
├── app/                      # ✅ Main code (autoloaded by composer)
│   ├── Models/              
│   │   ├── Traits/           # ✅ HasOrganizationScope, HasBranchScope, HasTeamScope
│   │   └── *.php             # User, Branch, Role, Permission, etc.
│   ├── Services/             # ✅ ContextService, PermissionService, etc.
│   ├── Facades/              # ✅ Context, SsoClient
│   ├── Http/
│   │   ├── Middleware/       # ✅ RequireOrganization, RequireBranch, WithBranch
│   │   ├── Controllers/
│   │   └── Resources/
│   └── Providers/            # ✅ SsoClientServiceProvider
│
├── database/
│   ├── migrations/
│   └── schemas/              # Omnify schemas
│
└── docs/                     # Documentation
```

## Implementation Status

### ✅ Đã triển khai (trong `app/`)

| Component | Location | Status |
|-----------|----------|--------|
| `HasOrganizationScope` trait | `app/Models/Traits/` | ✅ Done |
| `HasBranchScope` trait | `app/Models/Traits/` | ✅ Done |
| `HasTeamScope` trait | `app/Models/Traits/` | ✅ Done |
| `ContextService` | `app/Services/` | ✅ Done |
| `Context` facade | `app/Facades/` | ✅ Done |
| `RequireOrganization` middleware | `app/Http/Middleware/` | ✅ Done |
| `RequireBranch` middleware | `app/Http/Middleware/` | ✅ Done |
| `WithBranch` middleware | `app/Http/Middleware/` | ✅ Done |
| Models (User, Branch, etc.) | `app/Models/` | ✅ Done |
| Access Management (Roles/Permissions) | Multiple files | ✅ Done |

### 📋 Specs (chưa triển khai)

| Spec | Location | Priority |
|------|----------|----------|
| `branch-permissions-design.md` | `specs/` | 🟡 Medium |
| `event-bus-implementation.md` | `specs/` | 🟢 Low (Future) |

## Docs Structure

```
docs/architecture/
├── implemented/              # ✅ Đã triển khai
│   ├── access-control-flow-diagram.md
│   ├── access-management.md
│   ├── refactor-sso-cache-schemas.md
│   ├── sso-org-api-sync.md
│   └── sso-package-traits.md
│
└── specs/                    # 📋 Chưa triển khai
    ├── branch-permissions-design.md
    └── event-bus-implementation.md
```

## Usage in Main App

### Import Traits

```php
use Omnify\SsoClient\Models\Traits\HasOrganizationScope;
use Omnify\SsoClient\Models\Traits\HasBranchScope;

class Department extends Model
{
    use HasOrganizationScope;
}
```

### Use Context Facade

```php
use Omnify\SsoClient\Facades\Context;

// Get current organization
$organizationId = Context::organizationId();
$branchId = Context::branchId();

// Check context
if (Context::hasOrganization()) {
    // ...
}
```

### Middleware

```php
// routes/api.php
Route::middleware(['sso.require-organization'])->group(function () {
    Route::get('/departments', [DepartmentController::class, 'index']);
});

Route::middleware(['sso.require-branch'])->group(function () {
    Route::get('/devices', [DeviceController::class, 'index']);
});
```

## Notes

- `omnify.config.ts` points to `app/`, NOT `src/`
- All code is in `app/` folder (following Laravel package conventions)
- Composer autoload: `"Omnify\\SsoClient\\": "app/"`
