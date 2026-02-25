# IAM Admin Pages

Pre-built Inertia page routes for Identity & Access Management (IAM).

## Overview

The SSO package provides `AccessPageController` which defines routes for IAM admin pages. These routes render Inertia pages.

**Option 1: Use pre-built pages from `@famgia/omnify-react-sso`** (recommended)

**Option 2: Create custom pages** (if you need full control)

## Routes

| Route | Controller Method | Inertia Page | Props |
|-------|-------------------|--------------|-------|
| `GET /iam/users` | `users()` | `admin/iam/users` | - |
| `GET /iam/users/{userId}` | `userShow()` | `admin/iam/user-detail` | `userId` |
| `GET /iam/roles` | `roles()` | `admin/iam/roles` | - |
| `GET /iam/roles/{roleId}` | `roleShow()` | `admin/iam/role-detail` | `roleId` |
| `GET /iam/teams` | `teams()` | `admin/iam/teams` | - |
| `GET /iam/permissions` | `permissions()` | `admin/iam/permissions` | - |

## Configuration

### Page Path

Configure the Inertia page path prefix in `config/sso-client.php`:

```php
'routes' => [
    // Inertia page path prefix
    // Pages will be rendered from: resources/js/pages/{access_pages_path}/
    'access_pages_path' => 'admin/iam',
],
```

### Route Registration

Routes are registered in `routes/sso.php`. You can customize the prefix:

```php
// Default: /iam/*
Route::prefix('iam')
    ->middleware(['web', 'sso.auth', 'sso.organization'])
    ->group(function () {
        Route::get('/users', [AccessPageController::class, 'users']);
        Route::get('/users/{userId}', [AccessPageController::class, 'userShow']);
        // ...
    });
```

## Option 1: Use Pre-built Pages (Recommended)

`@famgia/omnify-react-sso` provides complete, ready-to-use IAM pages:

```tsx
// pages/admin/iam/users.tsx
import { usePage } from '@inertiajs/react';
import { useQuery } from '@tanstack/react-query';
import { UsersPage, createUserService } from '@famgia/omnify-react-sso';

const userService = createUserService({ apiUrl: '/api' });

export default function Users() {
  const { data, isLoading, refetch } = useQuery({
    queryKey: ['users'],
    queryFn: () => userService.list(),
  });

  return (
    <UsersPage
      users={data?.data ?? []}
      loading={isLoading}
      onRefresh={refetch}
      onUserClick={(userId) => router.visit(`/iam/users/${userId}`)}
    />
  );
}
```

```tsx
// pages/admin/iam/user-detail.tsx
import { usePage, router } from '@inertiajs/react';
import { useQuery } from '@tanstack/react-query';
import { UserDetailPage, createUserService, createRoleService } from '@famgia/omnify-react-sso';

const userService = createUserService({ apiUrl: '/api' });
const roleService = createRoleService({ apiUrl: '/api' });

export default function UserDetail() {
  const { userId } = usePage().props;

  const { data: userData, isLoading, refetch } = useQuery({
    queryKey: ['user-permissions', userId],
    queryFn: () => userService.getPermissions(userId as string),
  });

  const { data: rolesData } = useQuery({
    queryKey: ['roles'],
    queryFn: () => roleService.list(),
  });

  return (
    <UserDetailPage
      userId={userId as string}
      userData={userData}
      loading={isLoading}
      availableRoles={rolesData?.data}
      onBack={() => router.visit('/iam/users')}
      onRefresh={refetch}
    />
  );
}
```

### Available Pre-built Pages

| Page | Import | Description |
|------|--------|-------------|
| `UsersPage` | `@famgia/omnify-react-sso` | Users list with search |
| `UserDetailPage` | `@famgia/omnify-react-sso` | User detail with roles/permissions |
| `RolesPage` | `@famgia/omnify-react-sso` | Roles list with CRUD |
| `RoleDetailPage` | `@famgia/omnify-react-sso` | Role detail with permission editing |
| `PermissionsPage` | `@famgia/omnify-react-sso` | Permission matrix |
| `TeamsPage` | `@famgia/omnify-react-sso` | Teams with permissions |

## Option 2: Create Custom Pages

If you need full control, create pages using the low-level components:

```
resources/js/pages/
└── admin/
    └── iam/
        ├── users.tsx           # Users list
        ├── user-detail.tsx     # User detail + permissions
        ├── roles.tsx           # Roles list
        ├── role-detail.tsx     # Role detail + permissions
        ├── teams.tsx           # Teams list
        └── permissions.tsx     # Permissions matrix
```

### Using Low-level Components

```tsx
// Custom roles page using RolesListCard component
import { useQuery } from '@tanstack/react-query';
import { createRoleService, RolesListCard, RoleCreateModal } from '@famgia/omnify-react-sso';

const roleService = createRoleService({ apiUrl: '/api' });

export default function CustomRolesPage() {
  const { data: roles, refetch } = useQuery({
    queryKey: ['roles'],
    queryFn: () => roleService.list(),
  });

  return (
    <RolesListCard
      roles={roles?.data ?? []}
      scopeFilter="all"
      onScopeFilterChange={() => {}}
      onCreateClick={() => {}}
      onViewClick={(role) => {}}
      onDeleteClick={(role) => {}}
    />
  );
}
```

## Pre-built Components

`@famgia/omnify-react-sso` provides ready-to-use Ant Design components:

| Component | Description |
|-----------|-------------|
| `UserDetailCard` | Display user info |
| `UserPermissionsModal` | Show user's permissions breakdown |
| `UserRoleAssignModal` | Assign roles with scope (global/org/branch) |
| `RolesListCard` | Roles table with CRUD |
| `PermissionsListCard` | Permissions table |
| `TeamsListCard` | Teams with permissions |
| `OrganizationGate` | Show content only if organization selected |
| `BranchGate` | Show content only if branch selected |

## Services Reference

| Service | Methods |
|---------|---------|
| `userService` | `list()`, `get()`, `getPermissions()` |
| `userRoleService` | `list()`, `assign()`, `remove()`, `sync()` |
| `roleService` | `list()`, `get()`, `create()`, `update()`, `delete()`, `syncPermissions()` |
| `permissionService` | `list()`, `getMatrix()`, `create()`, `update()`, `delete()` |
| `teamService` | `list()`, `sync()`, `cleanup()` |

## Access Control

IAM pages require:

1. **Authentication** - `sso.auth` middleware
2. **Organization context** - `sso.organization` middleware  
3. **Admin role** - Check user has admin role/permission

```php
// Middleware stack
Route::middleware([
    'web',
    'sso.auth',           // Must be logged in
    'sso.organization',   // Must have org selected
    'sso.role:admin',     // Must have admin role (optional)
])->group(function () {
    // IAM routes
});
```

## See Also

- [API Reference](../reference/api.md) - API endpoints used by these pages
- [Authorization Guide](./authorization.md) - RBAC, roles, permissions
- [@famgia/omnify-react-sso](https://github.com/famgia/omnify-react-sso) - React package
