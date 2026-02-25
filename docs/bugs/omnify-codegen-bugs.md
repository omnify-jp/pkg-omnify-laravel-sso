# Omnify Code Generation Bugs

## Bug 1: `primaryKey` with Association names not resolved to column names in migrations

**Schema:**
```yaml
# RolePermission.yaml
options:
  id: false
  primaryKey: [role, permission]

properties:
  role:
    type: Association
    relation: ManyToOne
    target: Role
  permission:
    type: Association
    relation: ManyToOne
    target: Permission
```

**Generated migration (WRONG):**
```php
$table->uuid('role_id');
$table->uuid('permission_id');
$table->primary(['role', 'permission']); // BUG: uses property names, not column names
```

**Expected:**
```php
$table->primary(['role_id', 'permission_id']);
```

**Error:**
```
SQLSTATE[HY000]: General error: 1 expressions prohibited in PRIMARY KEY and UNIQUE constraints
(SQL: ... primary key ("role", "permission"))
```

**Note:** Using `primaryKey: [role_id, permission_id]` in the schema fails Omnify validation with "unknown property".

---

## Bug 2: ManyToMany pivot table name is empty string when `joinTable` is omitted

**Schema:**
```yaml
# User.yaml
properties:
  roles:
    type: Association
    relation: ManyToMany
    target: Role
    owning: true
```

**Generated base model (WRONG):**
```php
return $this->belongsToMany(Role::class, '') // BUG: empty string
    ->withPivot('console_branch_id', 'console_organization_id')
    ->withTimestamps();
```

**Expected:**
```php
return $this->belongsToMany(Role::class, 'role_user') // auto-derived from table names
```

**Note:** The migration correctly creates `role_user` table, but the model has empty pivot name.

---

## Workaround

Both bugs require manual fixes after each `omnify generate`:
1. Edit `database/migrations/omnify/*_create_role_permissions_table.php` line 36: change `['role', 'permission']` to `['role_id', 'permission_id']`
2. Edit `app/Models/Base/UserBaseModel.php` line ~126: change `''` to `'role_user'`
