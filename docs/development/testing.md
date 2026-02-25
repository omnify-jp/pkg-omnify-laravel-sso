# Testing Guide

Guide for testing applications using the SSO Client package.

## Package Test Suite

The package includes comprehensive tests:

```bash
cd packages/omnify-sso-client

# Run all tests
./vendor/bin/pest

# Run specific test file
./vendor/bin/pest tests/Feature/Security/SsoSecurityTest.php

# Run with coverage
./vendor/bin/pest --coverage

# Run specific test
./vendor/bin/pest --filter="authenticates user"
```

### Test Statistics

| Category       | Tests | Assertions |
| -------------- | ----- | ---------- |
| Security       | 47    | 100+       |
| Authentication | 35    | 70+        |
| Authorization  | 45    | 90+        |
| Models         | 80    | 200+       |
| Factories      | 28    | 82         |
| Middleware     | 25    | 50+        |
| **Total**      | 490+  | 947+       |

## Testing in Your Application

### Setup TestCase

```php
<?php
// tests/TestCase.php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Omnify\SsoClient\Models\User;
use Omnify\SsoClient\Models\Role;
use Omnify\SsoClient\Models\Permission;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function createUser(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    protected function createAdmin(): User
    {
        $role = Role::factory()->create([
            'slug' => 'admin',
            'level' => 100,
        ]);
        
        return User::factory()->create(['role_id' => $role->id]);
    }

    protected function createUserWithPermissions(array $permissions): User
    {
        $role = Role::factory()->create();
        
        foreach ($permissions as $slug) {
            $permission = Permission::factory()->create(['slug' => $slug]);
            $role->permissions()->attach($permission);
        }
        
        return User::factory()->create(['role_id' => $role->id]);
    }
}
```

### Authentication Tests

```php
<?php
// tests/Feature/AuthenticationTest.php

use Omnify\SsoClient\Models\User;
use Omnify\SsoClient\Services\ConsoleApiService;
use Omnify\SsoClient\Services\JwtVerifier;

test('user can authenticate via SSO', function () {
    // Mock Console API
    $consoleApi = Mockery::mock(ConsoleApiService::class);
    $consoleApi->shouldReceive('exchangeCode')
        ->with('valid-code')
        ->andReturn([
            'access_token' => 'jwt-token',
            'refresh_token' => 'refresh-token',
            'expires_in' => 3600,
        ]);
    
    // Mock JWT verifier
    $jwtVerifier = Mockery::mock(JwtVerifier::class);
    $jwtVerifier->shouldReceive('verify')
        ->andReturn([
            'sub' => 12345,
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);
    
    $this->app->instance(ConsoleApiService::class, $consoleApi);
    $this->app->instance(JwtVerifier::class, $jwtVerifier);

    $response = $this->postJson('/api/sso/callback', [
        'code' => 'valid-code',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['user', 'organizations']);
});

test('invalid code returns error', function () {
    $consoleApi = Mockery::mock(ConsoleApiService::class);
    $consoleApi->shouldReceive('exchangeCode')->andReturn(null);
    
    $this->app->instance(ConsoleApiService::class, $consoleApi);

    $response = $this->postJson('/api/sso/callback', [
        'code' => 'invalid-code',
    ]);

    $response->assertStatus(401)
        ->assertJson(['error' => 'INVALID_CODE']);
});

test('authenticated user can access protected routes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/sso/user')
        ->assertStatus(200);
});

test('unauthenticated user cannot access protected routes', function () {
    $this->getJson('/api/sso/user')
        ->assertStatus(401);
});
```

### Authorization Tests

```php
<?php
// tests/Feature/AuthorizationTest.php

use Omnify\SsoClient\Models\User;
use Omnify\SsoClient\Models\Role;
use Omnify\SsoClient\Models\Permission;

test('user with permission can access resource', function () {
    $user = $this->createUserWithPermissions(['users.view']);

    $this->actingAs($user)
        ->getJson('/api/users')
        ->assertStatus(200);
});

test('user without permission is denied', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/admin/users')
        ->assertStatus(403);
});

test('admin can access admin routes', function () {
    $admin = $this->createAdmin();

    $this->actingAs($admin)
        ->getJson('/api/admin/sso/roles')
        ->assertStatus(200);
});

test('role hierarchy works correctly', function () {
    $adminRole = Role::factory()->create(['level' => 100]);
    $managerRole = Role::factory()->create(['level' => 50]);
    
    $admin = User::factory()->create(['role_id' => $adminRole->id]);
    $manager = User::factory()->create(['role_id' => $managerRole->id]);

    // Admin can access manager routes
    $this->actingAs($admin)
        ->getJson('/api/manager/dashboard')
        ->assertStatus(200);
    
    // Manager can access manager routes
    $this->actingAs($manager)
        ->getJson('/api/manager/dashboard')
        ->assertStatus(200);
    
    // Manager cannot access admin routes
    $this->actingAs($manager)
        ->getJson('/api/admin/dashboard')
        ->assertStatus(403);
});
```

### Permission Tests

```php
<?php
// tests/Feature/PermissionTest.php

test('user has permission returns true', function () {
    $permission = Permission::factory()->create(['slug' => 'posts.create']);
    $role = Role::factory()->create();
    $role->permissions()->attach($permission);
    
    $user = User::factory()->create(['role_id' => $role->id]);

    expect($user->hasPermission('posts.create'))->toBeTrue();
    expect($user->hasPermission('posts.delete'))->toBeFalse();
});

test('user has any permission', function () {
    $user = $this->createUserWithPermissions(['posts.view']);

    expect($user->hasAnyPermission(['posts.view', 'posts.create']))->toBeTrue();
    expect($user->hasAnyPermission(['posts.delete', 'posts.update']))->toBeFalse();
});

test('user has all permissions', function () {
    $user = $this->createUserWithPermissions(['posts.view', 'posts.create']);

    expect($user->hasAllPermissions(['posts.view', 'posts.create']))->toBeTrue();
    expect($user->hasAllPermissions(['posts.view', 'posts.delete']))->toBeFalse();
});
```

### Factory Tests

```php
<?php
// tests/Unit/FactoryTest.php

use Omnify\SsoClient\Models\User;
use Omnify\SsoClient\Models\Role;
use Omnify\SsoClient\Models\Permission;

test('user factory creates valid user', function () {
    $user = User::factory()->create();

    expect($user->id)->toBeInt();
    expect($user->email)->toContain('@');
    expect($user->console_user_id)->not->toBeNull();
});

test('user factory without SSO data', function () {
    $user = User::factory()->withoutConsoleUserId()->create();

    expect($user->console_user_id)->toBeNull();
});

test('role factory creates valid role', function () {
    $role = Role::factory()->create();

    expect($role->name)->not->toBeEmpty();
    expect($role->slug)->not->toBeEmpty();
    expect($role->level)->toBeInt();
});

test('permission factory creates valid permission', function () {
    $permission = Permission::factory()->create();

    expect($permission->name)->not->toBeEmpty();
    expect($permission->slug)->not->toBeEmpty();
});
```

### Middleware Tests

```php
<?php
// tests/Feature/MiddlewareTest.php

use Omnify\SsoClient\Models\User;

test('sso.auth middleware rejects unauthenticated', function () {
    $this->getJson('/api/protected-route')
        ->assertStatus(401);
});

test('sso.role middleware checks role level', function () {
    $memberRole = Role::factory()->create(['level' => 10]);
    $user = User::factory()->create(['role_id' => $memberRole->id]);

    $this->actingAs($user)
        ->getJson('/api/admin/route') // Requires admin (level 100)
        ->assertStatus(403);
});

test('sso.permission middleware checks permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/route-requiring-permission')
        ->assertStatus(403);
});
```

### Security Tests

```php
<?php
// tests/Feature/SecurityTest.php

test('open redirect is blocked', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->getJson('/api/sso/global-logout-url?redirect_uri=https://evil.com');

    $data = $response->json();
    expect($data['logout_url'])->not->toContain('evil.com');
});

test('invalid jwt is rejected', function () {
    $jwtVerifier = Mockery::mock(JwtVerifier::class);
    $jwtVerifier->shouldReceive('verify')->andReturn(null);
    
    $consoleApi = Mockery::mock(ConsoleApiService::class);
    $consoleApi->shouldReceive('exchangeCode')
        ->andReturn(['access_token' => 'invalid']);
    
    $this->app->instance(JwtVerifier::class, $jwtVerifier);
    $this->app->instance(ConsoleApiService::class, $consoleApi);

    $this->postJson('/api/sso/callback', ['code' => 'code'])
        ->assertStatus(401)
        ->assertJson(['error' => 'INVALID_TOKEN']);
});
```

## Mocking SSO Services

### Mock Console API

```php
$mock = Mockery::mock(ConsoleApiService::class);
$mock->shouldReceive('exchangeCode')->andReturn([...]);
$mock->shouldReceive('getConsoleUrl')->andReturn('https://console.test');

$this->app->instance(ConsoleApiService::class, $mock);
```

### Mock JWT Verifier

```php
$mock = Mockery::mock(JwtVerifier::class);
$mock->shouldReceive('verify')->andReturn([
    'sub' => 123,
    'email' => 'test@example.com',
    'name' => 'Test User',
]);

$this->app->instance(JwtVerifier::class, $mock);
```

### Mock Organization Service

```php
$mock = Mockery::mock(OrgAccessService::class);
$mock->shouldReceive('getOrganizations')->andReturn([
    ['id' => 'org-1', 'name' => 'Test Org'],
]);

$this->app->instance(OrgAccessService::class, $mock);
```

## Database Testing

Use `RefreshDatabase` trait:

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class MyTest extends TestCase
{
    use RefreshDatabase;
    
    // Tests...
}
```

## CI/CD Integration

### GitHub Actions

```yaml
# .github/workflows/tests.yml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          
      - name: Install dependencies
        run: composer install
        
      - name: Run tests
        run: ./vendor/bin/pest
```
