# Omnify Generator Bug Report

**Package**: `pkg-omnify-laravel-core`
**Date**: 2026-02-24
**Context**: Laravel package with `omnify.yaml` configured for custom namespace `Omnify\Core`

---

## Bug 1 — Resource namespace not respected for Team-related files

### Severity: HIGH (causes class-not-found errors at runtime)

### Config (`omnify.yaml`)
```yaml
codegen:
  laravel:
    resource:
      path: app/Http/Resources
      namespace: Omnify\Core\Http\Resources
```

### Expected
All generated `*ResourceBase.php` files under `app/Http/Resources/OmnifyBase/` should have:
```php
namespace Omnify\Core\Http\Resources\OmnifyBase;
```
And all internal cross-references should use the same namespace:
```php
new \Omnify\Core\Http\Resources\PermissionResource($this->permission)
```

### Actual
Two files are generated with the wrong `App\` namespace:

**`app/Http/Resources/OmnifyBase/TeamResourceBase.php`**:
```php
namespace App\Http\Resources\OmnifyBase;  // ❌ should be Omnify\Core\Http\Resources\OmnifyBase
```

**`app/Http/Resources/OmnifyBase/TeamPermissionResourceBase.php`**:
```php
namespace App\Http\Resources\OmnifyBase;  // ❌ should be Omnify\Core\Http\Resources\OmnifyBase
// ...
'permission' => $this->whenLoaded('permission', fn () => new \App\Http\Resources\PermissionResource($this->permission)),  // ❌
```

All other `*ResourceBase.php` files (Permission, Role, RolePermission, User, Branch, etc.) are generated correctly. The bug is **specific to Team and TeamPermission** resources.

### Impact
- `HTTP 500` on any API endpoint that serializes a Team or TeamPermission resource
- `Class "App\Http\Resources\OmnifyBase\TeamResourceBase" not found` if the resource is used

---

## Bug 2 — `BaseModel` does not include `HasUuids` trait

### Severity: HIGH (causes NOT NULL constraint failures on all UUID primary keys)

### Expected
When the schema defines models with UUID primary keys (all models in this package use `id` as UUID), the generated `BaseModel.php` should include `HasUuids`:
```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;

abstract class BaseModel extends Model
{
    use HasUuids;
    // ...
}
```

### Actual
Generated `app/Models/Base/BaseModel.php`:
```php
abstract class BaseModel extends Model
{
    // No HasUuids — UUID is never auto-generated
}
```

### Impact
- `SQLSTATE[23000]: Integrity constraint violation: 19 NOT NULL constraint failed: roles.id` on every `Model::create()`
- All factories fail because `id` is never populated
- Every test that creates model instances fails

### Workaround (user-editable models)
Users must manually add `HasUuids` to each individual model, or add it to `BaseModel.php` knowing it will be overwritten on every `omnify generate`.

---

## Bug 3 — `RolePermission` model has composite primary key `'role,permission'` but `BaseModel` includes `HasUuids`

### Severity: MEDIUM (causes incorrect SQL when Bug 2 workaround is applied)

### Context
`RolePermissionBaseModel` is generated with:
```php
protected $primaryKey = 'role,permission';
```
This is a composite key (not a UUID column). However, when users add `HasUuids` to `BaseModel` to fix Bug 2, the `HasUuids` trait tries to treat `'role,permission'` as the UUID column name, causing:
```
SQLSTATE: column 'role,permission' not found
```

### Expected
The generator should detect composite primary keys and:
1. Either generate `usesUniqueIds(): bool { return false; }` override in the model
2. Or generate `$primaryKey` without triggering UUID behavior

### Fix Needed
In generated `RolePermission.php` (user-editable), add:
```php
public function usesUniqueIds(): bool
{
    return false;
}
```
Or better: the generator should detect composite PK schemas and add this method automatically.

---

## Bug 4 — `role_user` pivot table schema prevents Scoped RBAC

### Severity: HIGH (core RBAC functionality broken by design)

### Full File Path
```
packages/pkg-omnify-laravel-core/database/migrations/omnify/2026_02_21_122428_create_role_user_table.php
```

### Generated Migration (lines 30-40 of the file above)
```php
Schema::create('role_user', function (Blueprint $table) {
    $table->uuid('user_id');
    $table->uuid('role_id');
    $table->string('console_organization_id', 36)->nullable();
    $table->string('console_branch_id', 36)->nullable();
    // ...
    $table->unique(['user_id', 'role_id']);   // ❌ prevents scoped assignments
    $table->primary(['user_id', 'role_id']);  // ❌ composite PK locks to one row per user+role
});
```

### Problem
The schema stores `console_organization_id` and `console_branch_id` as pivot columns, implying that the **same role can be assigned in different scopes** (global, org-wide, branch-specific). However, the `PRIMARY KEY (user_id, role_id)` constraint allows **only ONE assignment per user per role**, making the scope columns useless.

### Use Case That Is Broken
```php
// User is admin globally AND manager in Tokyo branch
$user->assignRole('admin', null, null);            // global
$user->assignRole('admin', 'tokyo-org', 'branch'); // branch-specific

// Should have 2 rows, but constraint only allows 1
```

### Expected Schema
```php
Schema::create('role_user', function (Blueprint $table) {
    $table->id(); // auto-increment as PK to allow multiple scoped rows
    $table->uuid('user_id');
    $table->uuid('role_id');
    $table->string('console_organization_id', 36)->nullable();
    $table->string('console_branch_id', 36)->nullable();
    // ...
    // Index for performance (not unique constraint)
    $table->index(['user_id', 'role_id', 'console_organization_id', 'console_branch_id']);
});
```

### Impact
- Scoped RBAC (which the schema YAML implies via the nullable pivot columns) is **non-functional**
- `User::getRolesForContext()` always returns at most 1 role per user per role-slug
- Tests for multi-scope assignments all fail

---

## Bug 5 — `newFactory()` method not generated in user-editable Model files

### Severity: HIGH (all `Model::factory()` calls fail in non-standard namespaces)

### Problem
Laravel's default factory resolution uses `Database\Factories\{ModelName}Factory`. When the package uses a custom namespace (`Omnify\Core\Models`), Laravel resolves factory class as `Database\Factories\Omnify\Core\Models\UserFactory` — which doesn't exist.

### Generated `User.php` (user-editable)
```php
class User extends UserBaseModel
{
    use HasFactory;
    // No newFactory() override
}
```

### Expected
```php
class User extends UserBaseModel
{
    use HasFactory;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
```

### Workaround
Users must manually add `newFactory()` to every generated model. This affects:
- `User`, `Role`, `Permission`, `Branch`, `Organization`
- `RolePermission`, `RefreshToken`, `Team`, `TeamPermission`, `Location`

---

## Bug 6 — `User` model not generated with `Authenticatable` interface/traits

### Severity: HIGH (Laravel auth and testing infrastructure broken)

### Problem
The generated `User.php` extends `UserBaseModel` (which extends `BaseModel` which extends `Model`) but does NOT implement Laravel's `Authenticatable` contract. This means:

1. **`Auth::guard()->login($user)` fails** — user cannot be logged in
2. **`$this->actingAs($user)` in tests fails** — `actingAs()` requires `Authenticatable`
3. **Sanctum `createToken()` fails** — requires `HasApiTokens`

### Generated `User.php`
```php
class User extends UserBaseModel
{
    use HasFactory;
    // Missing: implements AuthenticatableContract, AuthorizableContract
    // Missing: use Authenticatable, Authorizable, HasApiTokens, Notifiable
}
```

### Expected
```php
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends UserBaseModel implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable;
    use Authorizable;
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
}
```

---

## Bug 7 — `--force` flag overwrites ALL files including user-editable ones

### Severity: CRITICAL (destroys all user customizations on every regeneration)

### Problem
Running `omnify generate --force` overwrites **user-editable files** (marked as "SAFE TO EDIT - This file is never overwritten by Omnify") with fresh generated content, destroying all user customizations.

These files are labeled with the comment `SAFE TO EDIT - This file is never overwritten by Omnify` but ARE overwritten by `--force`.

### Full Paths of All Files Overwritten by `omnify generate --force` (confirmed 2026-02-24)

**Models (user-editable, should never be overwritten):**
```
packages/pkg-omnify-laravel-core/app/Models/User.php
packages/pkg-omnify-laravel-core/app/Models/Branch.php
packages/pkg-omnify-laravel-core/app/Models/Location.php
packages/pkg-omnify-laravel-core/app/Models/Organization.php
packages/pkg-omnify-laravel-core/app/Models/PasswordResetToken.php
packages/pkg-omnify-laravel-core/app/Models/Permission.php
packages/pkg-omnify-laravel-core/app/Models/RefreshToken.php
packages/pkg-omnify-laravel-core/app/Models/Role.php
packages/pkg-omnify-laravel-core/app/Models/RolePermission.php
packages/pkg-omnify-laravel-core/app/Models/Team.php
packages/pkg-omnify-laravel-core/app/Models/TeamPermission.php
```

**Factories (user-editable, should never be overwritten):**
```
packages/pkg-omnify-laravel-core/database/factories/BranchFactory.php
packages/pkg-omnify-laravel-core/database/factories/LocationFactory.php
packages/pkg-omnify-laravel-core/database/factories/OrganizationFactory.php
packages/pkg-omnify-laravel-core/database/factories/PermissionFactory.php
packages/pkg-omnify-laravel-core/database/factories/RefreshTokenFactory.php
packages/pkg-omnify-laravel-core/database/factories/RoleFactory.php
packages/pkg-omnify-laravel-core/database/factories/RolePermissionFactory.php
packages/pkg-omnify-laravel-core/database/factories/TeamFactory.php
packages/pkg-omnify-laravel-core/database/factories/TeamPermissionFactory.php
packages/pkg-omnify-laravel-core/database/factories/UserFactory.php
```

**Resources (user-editable, should never be overwritten):**
```
packages/pkg-omnify-laravel-core/app/Http/Resources/PermissionResource.php
packages/pkg-omnify-laravel-core/app/Http/Resources/UserResource.php
```

### Impact
- All user customizations lost on every `omnify generate --force`
- 115 test failures after each regeneration
- Teams must maintain a separate git patch or restore script to recover customizations

### Expected Behavior
- `--force` should ONLY overwrite **auto-generated** files (those in `app/Models/Base/`, `app/Http/Resources/OmnifyBase/`, `database/migrations/omnify/`)
- User-editable files (those marked "SAFE TO EDIT") should NEVER be overwritten, even with `--force`
- If deletion of stale files is needed, add a separate `--prune` flag with confirmation prompt

---

## Summary Table

| # | Bug | Severity | Affected Files | Reproducible |
|---|-----|----------|----------------|--------------|
| 1 | Wrong `App\` namespace in Team/TeamPermission resources | HIGH | `TeamResourceBase.php`, `TeamPermissionResourceBase.php` | ✅ Always |
| 2 | `BaseModel` missing `HasUuids` | HIGH | `BaseModel.php` | ✅ Always |
| 3 | `RolePermission` composite PK conflicts with HasUuids workaround | MEDIUM | `RolePermission.php` | ✅ When Bug 2 workaround applied |
| 4 | `role_user` pivot table prevents scoped RBAC | HIGH | `create_role_user_table.php` | ✅ Always |
| 5 | `newFactory()` not generated | HIGH | All user-editable `*Model.php` | ✅ Always (custom namespace) |
| 6 | `User` not generated with `Authenticatable` | HIGH | `User.php` | ✅ Always |
| 7 | `--force` deletes user-editable files | MEDIUM | All user-editable files | ✅ With `--force` |

---

## Reproduction Steps

```bash
# 1. Create new package with omnify.yaml namespace = "Omnify\Core"
# 2. Run:
omnify generate --force

# 3. Check:
grep -n "namespace" app/Http/Resources/OmnifyBase/TeamResourceBase.php
# → namespace App\Http\Resources\OmnifyBase   ← BUG 1

grep -n "HasUuids" app/Models/Base/BaseModel.php
# → (empty)  ← BUG 2

# 4. Run tests:
vendor/bin/pest
# → "NOT NULL constraint failed: roles.id"  ← BUG 2 impact
# → "Class 'Database\Factories\...\UserFactory' not found"  ← BUG 5 impact
# → actingAs() type error  ← BUG 6 impact
```

---

## Recommended Fixes for Omnify Team

1. **Bug 1**: Fix template rendering for `TeamResourceBase` and `TeamPermissionResourceBase` — these seem to have a different code path than other resource templates.

2. **Bug 2**: Add `HasUuids` to the generated `BaseModel.php` template when the schema uses UUID primary keys (all schemas currently use UUIDs).

3. **Bug 3**: When generating models with composite primary keys, add `usesUniqueIds(): bool { return false; }` to the generated user-editable model.

4. **Bug 4**: Change `role_user` schema to support scoped assignments. Proposed YAML schema change:
   ```yaml
   # In the role_user pivot schema:
   primaryKey: id  # auto-increment, not composite
   uniqueConstraints:
     - [user_id, role_id, console_organization_id, console_branch_id]
   ```

5. **Bug 5**: Generate `newFactory()` override in all user-editable model files when a custom factory namespace is configured.

6. **Bug 6**: Add Authenticatable-related traits and interface to generated `User.php` template.

7. **Bug 7**: Never delete user-editable files during `--force` regeneration. Add a `--prune` flag for explicitly opting into deletion.
