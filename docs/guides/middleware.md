# Middleware Reference

The SSO Client provides four middleware for route protection.

## Available Middleware

| Alias            | Class                   | Description                          |
| ---------------- | ----------------------- | ------------------------------------ |
| `sso.auth`       | `SsoAuthenticate`       | Requires authenticated user with SSO |
| `sso.organization`        | `SsoOrganizationAccess` | Requires organization access         |
| `sso.role`       | `SsoRoleCheck`          | Requires specific role level         |
| `sso.permission` | `SsoPermissionCheck`    | Requires specific permission         |

## sso.auth

Ensures the user is authenticated via SSO (has `console_user_id`).

```php
Route::middleware('sso.auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::get('/settings', [SettingsController::class, 'index']);
});
```

### Behavior

- Returns 401 if user is not authenticated
- Returns 403 if user doesn't have `console_user_id` (not SSO user)

### Response (Unauthenticated)

```json
{
    "error": "UNAUTHENTICATED",
    "message": "Authentication required"
}
```

### Response (Not SSO User)

```json
{
    "error": "SSO_REQUIRED",
    "message": "SSO authentication required"
}
```

## sso.organization

Requires user to have access to the specified organization.

```php
// With organization ID from route parameter
Route::middleware(['sso.auth', 'sso.organization'])->group(function () {
    Route::get('/orgs/{organization_id}/projects', [ProjectController::class, 'index']);
});

// Organization ID is extracted from:
// 1. Route parameter: org_id, organization_id, orgId
// 2. Request header: X-Organization-Id
// 3. Request body: org_id, organization_id
```

### Response (No Access)

```json
{
    "error": "ORG_ACCESS_DENIED",
    "message": "You do not have access to this organization"
}
```

## sso.role

Requires user to have a role with minimum level.

```php
// Single role
Route::middleware(['sso.auth', 'sso.role:admin'])->group(function () {
    Route::resource('/admin/users', AdminUserController::class);
});

// Multiple roles (any of them)
Route::middleware(['sso.auth', 'sso.role:admin,manager'])->group(function () {
    Route::get('/reports', [ReportController::class, 'index']);
});
```

### How It Works

The middleware checks if user's role level meets the required level:

```php
// config/sso-client.php
'role_levels' => [
    'admin' => 100,
    'manager' => 50,
    'member' => 10,
],
```

- `sso.role:admin` requires level >= 100
- `sso.role:manager` requires level >= 50
- `sso.role:admin,manager` requires level >= 50 (lowest of specified)

### Response (Insufficient Role)

```json
{
    "error": "ROLE_REQUIRED",
    "message": "Insufficient role level"
}
```

## sso.permission

Requires user to have specific permission(s).

```php
// Single permission
Route::middleware(['sso.auth', 'sso.permission:users.create'])->group(function () {
    Route::post('/users', [UserController::class, 'store']);
});

// Multiple permissions (all required)
Route::middleware(['sso.auth', 'sso.permission:users.create,users.update'])->group(function () {
    Route::resource('/users', UserController::class);
});

// Multiple permissions (any)
Route::middleware(['sso.auth', 'sso.permission:users.create|users.update'])->group(function () {
    // | means OR, , means AND
});
```

### Permission Format

```php
// Require ALL permissions (AND)
'sso.permission:users.create,users.update,users.delete'

// Require ANY permission (OR)
'sso.permission:users.create|users.update|users.delete'

// Combined
'sso.permission:users.view,posts.view|posts.create'
// Means: users.view AND (posts.view OR posts.create)
```

### Response (Permission Denied)

```json
{
    "error": "PERMISSION_DENIED",
    "message": "You do not have the required permission"
}
```

## Combining Middleware

```php
// Full protection chain
Route::middleware([
    'sso.auth',           // Must be authenticated via SSO
    'sso.organization',            // Must have org access
    'sso.role:manager',   // Must be manager or higher
    'sso.permission:projects.create' // Must have permission
])->group(function () {
    Route::post('/orgs/{organization_id}/projects', [ProjectController::class, 'store']);
});
```

## Global Middleware

Apply to all routes in a group:

```php
// routes/api.php
Route::prefix('api/v1')
    ->middleware(['api', 'sso.auth'])
    ->group(function () {
        // All routes require SSO authentication
        Route::get('/me', [UserController::class, 'me']);
        
        // Additional protection
        Route::middleware('sso.role:admin')->group(function () {
            Route::resource('/users', AdminUserController::class);
        });
    });
```

## Custom Middleware

Extend the base middleware:

```php
<?php

namespace App\Http\Middleware;

use Omnify\SsoClient\Http\Middleware\SsoAuthenticate;

class CustomSsoAuth extends SsoAuthenticate
{
    protected function handleUnauthenticated($request)
    {
        // Custom handling
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'AUTH_REQUIRED',
                'login_url' => route('login'),
            ], 401);
        }

        return redirect()->route('login');
    }
}
```

Register in `app/Http/Kernel.php`:

```php
protected $middlewareAliases = [
    'sso.auth' => \App\Http\Middleware\CustomSsoAuth::class,
    // ...
];
```

## Testing with Middleware

```php
// In tests
use Omnify\SsoClient\Models\User;
use Omnify\SsoClient\Models\Role;
use Omnify\SsoClient\Models\Permission;

test('admin can access admin routes', function () {
    $role = Role::factory()->create(['slug' => 'admin', 'level' => 100]);
    $user = User::factory()->create(['role_id' => $role->id]);
    
    $this->actingAs($user)
        ->getJson('/api/admin/users')
        ->assertStatus(200);
});

test('member cannot access admin routes', function () {
    $role = Role::factory()->create(['slug' => 'member', 'level' => 10]);
    $user = User::factory()->create(['role_id' => $role->id]);
    
    $this->actingAs($user)
        ->getJson('/api/admin/users')
        ->assertStatus(403);
});
```
