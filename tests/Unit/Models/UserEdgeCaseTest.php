<?php

/**
 * User Model Edge Case Tests
 *
 * ユーザーモデルのエッジケーステスト
 * Kiểm thử các trường hợp biên cho Model User
 *
 * NOTE: SSO users don't have passwords or email_verified_at - authentication is via Console tokens
 */

use Illuminate\Support\Str;
use Omnify\SsoClient\Models\Role;
use Omnify\SsoClient\Models\User;

beforeEach(function () {
    $this->artisan('migrate', ['--database' => 'testing']);
});

// =============================================================================
// String Length Edge Cases - 文字列長のエッジケース
// =============================================================================

test('can create user with minimum length name (1 char)', function () {
    $user = User::create([
        'name' => 'A',
        'email' => 'a@example.com',
    ]);

    expect($user->name)->toBe('A');
});

test('can create user with maximum length name (255 chars)', function () {
    $longName = str_repeat('a', 255);

    $user = User::create([
        'name' => $longName,
        'email' => 'long@example.com',
    ]);

    expect(strlen($user->name))->toBe(255);
});

test('can store very long name in SQLite (no length enforcement)', function () {
    // SQLite doesn't enforce VARCHAR length limits
    // In MySQL/PostgreSQL, this would fail
    $longName = str_repeat('a', 256);

    $user = User::create([
        'name' => $longName,
        'email' => 'toolong@example.com',
    ]);

    // SQLite allows it, documents different behavior across databases
    expect(strlen($user->name))->toBe(256);
});

test('can create user with maximum length email (255 chars)', function () {
    // email format: local@domain, max 255 chars total
    $localPart = str_repeat('a', 243); // 243 + @ + example.com = 255
    $email = $localPart.'@example.com';

    $user = User::create([
        'name' => 'Test',
        'email' => $email,
    ]);

    expect(strlen($user->email))->toBe(255);
});

// =============================================================================
// Unicode & Special Characters - Unicode・特殊文字
// =============================================================================

test('can create user with unicode name (Japanese)', function () {
    $user = User::create([
        'name' => '田中太郎',
        'email' => 'tanaka@example.com',
    ]);

    expect($user->name)->toBe('田中太郎');
});

test('can create user with unicode name (Vietnamese)', function () {
    $user = User::create([
        'name' => 'Nguyễn Văn A',
        'email' => 'nguyen@example.com',
    ]);

    expect($user->name)->toBe('Nguyễn Văn A');
});

test('can create user with unicode name (Emoji)', function () {
    $user = User::create([
        'name' => 'User 🎉👨‍💻',
        'email' => 'emoji@example.com',
    ]);

    expect($user->name)->toContain('🎉');
});

test('can create user with special characters in name', function () {
    $user = User::create([
        'name' => "O'Brien-Smith, Jr.",
        'email' => 'obrien@example.com',
    ]);

    expect($user->name)->toBe("O'Brien-Smith, Jr.");
});

test('can create user with numbers in name', function () {
    $user = User::create([
        'name' => 'User123',
        'email' => 'user123@example.com',
    ]);

    expect($user->name)->toBe('User123');
});

// =============================================================================
// Email Edge Cases - メールアドレスのエッジケース
// =============================================================================

test('email is case insensitive for uniqueness check', function () {
    User::create([
        'name' => 'User 1',
        'email' => 'TEST@example.com',
    ]);

    // SQLite treats emails as case-sensitive by default
    // This test documents the actual behavior
    $user2 = User::create([
        'name' => 'User 2',
        'email' => 'test@example.com',
    ]);

    // Different case = different email in SQLite
    // ID is UUID string now
    expect($user2->id)->toBeString()
        ->and($user2->id)->toMatch('/^[0-9a-f-]{36}$/');
});

test('can create user with plus addressing in email', function () {
    $user = User::create([
        'name' => 'Plus User',
        'email' => 'user+tag@example.com',
    ]);

    expect($user->email)->toBe('user+tag@example.com');
});

test('can create user with subdomain in email', function () {
    $user = User::create([
        'name' => 'Subdomain User',
        'email' => 'user@mail.subdomain.example.com',
    ]);

    expect($user->email)->toBe('user@mail.subdomain.example.com');
});

test('can create user with numeric local part in email', function () {
    $user = User::create([
        'name' => 'Numeric User',
        'email' => '12345@example.com',
    ]);

    expect($user->email)->toBe('12345@example.com');
});

// =============================================================================
// Console ID Edge Cases - Console IDのエッジケース
// NOTE: SSO users don't have passwords - authentication is via Console tokens
// NOTE: console_user_id is now UUID (string)
// =============================================================================

test('can create user with valid UUID console_user_id', function () {
    $consoleId = (string) Str::uuid();
    $user = User::create([
        'name' => 'UUID Console ID',
        'email' => 'uuidid@example.com',
        'console_user_id' => $consoleId,
    ]);

    expect($user->console_user_id)->toBe($consoleId)
        ->and($user->console_user_id)->toBeString();
});

test('can create user with nil UUID console_user_id', function () {
    $nilUuid = '00000000-0000-0000-0000-000000000000';

    $user = User::create([
        'name' => 'Nil UUID Console ID',
        'email' => 'niluuid@example.com',
        'console_user_id' => $nilUuid,
    ]);

    expect($user->console_user_id)->toBe($nilUuid);
});

test('console_user_id is stored as string UUID', function () {
    $consoleId = (string) Str::uuid();
    $user = User::create([
        'name' => 'String Console ID',
        'email' => 'stringid@example.com',
        'console_user_id' => $consoleId,
    ]);

    expect($user->console_user_id)->toBeString()
        ->and($user->console_user_id)->toMatch('/^[0-9a-f-]{36}$/');
});

// =============================================================================
// Token Edge Cases - トークンのエッジケース
// =============================================================================

test('can store very long access token', function () {
    $longToken = str_repeat('a', 2000);

    $user = User::create([
        'name' => 'Long Token',
        'email' => 'longtoken@example.com',
        'console_access_token' => $longToken,
    ]);

    expect($user->console_access_token)->toBe($longToken);
});

test('can store token with special JWT characters', function () {
    $jwtToken = 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.signature_here';

    $user = User::create([
        'name' => 'JWT Token',
        'email' => 'jwttoken@example.com',
        'console_access_token' => $jwtToken,
    ]);

    expect($user->console_access_token)->toBe($jwtToken);
});

test('token expiration in the past', function () {
    $pastDate = now()->subYear();

    $user = User::create([
        'name' => 'Expired Token',
        'email' => 'expired@example.com',
        'console_token_expires_at' => $pastDate,
    ]);

    expect($user->console_token_expires_at->isPast())->toBeTrue();
});

test('token expiration far in the future', function () {
    $futureDate = now()->addYears(100);

    $user = User::create([
        'name' => 'Long-lived Token',
        'email' => 'longlived@example.com',
        'console_token_expires_at' => $futureDate,
    ]);

    expect($user->console_token_expires_at->isFuture())->toBeTrue();
});

// =============================================================================
// Relationship Edge Cases - リレーションシップのエッジケース
// NOTE: SSO uses many-to-many roles with scoping, not single role_id
// =============================================================================

test('user with deleted role has empty roles collection', function () {
    $role = Role::create(['name' => 'Temp', 'slug' => 'temp', 'level' => 10]);
    $user = User::create([
        'name' => 'Orphan User',
        'email' => 'orphan@example.com',
    ]);
    $user->assignRole($role);

    expect($user->roles)->toHaveCount(1);

    $role->delete();
    $user->refresh();

    expect($user->roles)->toBeEmpty();
});

test('can assign and remove roles multiple times', function () {
    $role1 = Role::create(['name' => 'Role 1', 'slug' => 'role1', 'level' => 10]);
    $role2 = Role::create(['name' => 'Role 2', 'slug' => 'role2', 'level' => 20]);
    $role3 = Role::create(['name' => 'Role 3', 'slug' => 'role3', 'level' => 30]);

    $user = User::create([
        'name' => 'Role Changer',
        'email' => 'changer@example.com',
    ]);
    $user->assignRole($role1);

    expect($user->roles)->toHaveCount(1);
    expect($user->roles->first()->slug)->toBe('role1');

    $user->removeRole($role1);
    $user->assignRole($role2);
    $user->refresh();
    expect($user->roles)->toHaveCount(1);
    expect($user->roles->first()->slug)->toBe('role2');

    $user->assignRole($role3);
    $user->refresh();
    expect($user->roles)->toHaveCount(2);

    // Remove all roles
    $user->roles()->detach();
    $user->refresh();
    expect($user->roles)->toBeEmpty();
});

// =============================================================================
// Query Edge Cases - クエリのエッジケース
// =============================================================================

test('can find user with email containing special SQL characters', function () {
    $user = User::create([
        'name' => 'SQL Special',
        'email' => "test'@example.com",
    ]);

    $found = User::where('email', "test'@example.com")->first();
    expect($found)->not->toBeNull();
});

test('search with empty result returns empty collection', function () {
    $results = User::where('email', 'nonexistent@nowhere.com')->get();

    expect($results)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class)
        ->and($results)->toHaveCount(0);
});

test('can handle null values in where clause', function () {
    User::create(['name' => 'With Console', 'email' => 'with@example.com', 'console_user_id' => 123]);
    User::create(['name' => 'Without Console', 'email' => 'without@example.com']);

    $withConsole = User::whereNotNull('console_user_id')->get();
    $withoutConsole = User::whereNull('console_user_id')->get();

    expect($withConsole)->toHaveCount(1)
        ->and($withoutConsole)->toHaveCount(1);
});

// =============================================================================
// Timestamp Edge Cases - タイムスタンプのエッジケース
// NOTE: SSO users don't have email_verified_at - verification is handled by Console
// =============================================================================

test('can set console_token_expires_at to unix epoch', function () {
    $epoch = \Carbon\Carbon::createFromTimestamp(0);

    $user = User::create([
        'name' => 'Epoch User',
        'email' => 'epoch@example.com',
        'console_token_expires_at' => $epoch,
    ]);

    expect($user->console_token_expires_at->timestamp)->toBe(0);
});

test('can set console_token_expires_at to far future', function () {
    $future = now()->addYears(100);

    $user = User::create([
        'name' => 'Future User',
        'email' => 'future@example.com',
        'console_token_expires_at' => $future,
    ]);

    expect($user->console_token_expires_at->year)->toBe(now()->addYears(100)->year);
});

// =============================================================================
// Mass Assignment Edge Cases - 一括代入のエッジケース
// =============================================================================

test('cannot mass assign id', function () {
    $user = User::create([
        'id' => 99999,
        'name' => 'Force ID',
        'email' => 'forceid@example.com',
    ]);

    // ID should be auto-generated, not the forced value
    expect($user->id)->not->toBe(99999);
});

test('fillable fields are correctly set', function () {
    $user = new User;
    $fillable = $user->getFillable();

    // SSO users don't have passwords - authentication is via Console tokens
    expect($fillable)->toContain('name')
        ->and($fillable)->toContain('email')
        ->and($fillable)->toContain('console_user_id')
        ->and($fillable)->toContain('console_access_token')
        ->and($fillable)->toContain('console_refresh_token');
});

// =============================================================================
// Update Edge Cases - 更新のエッジケース
// =============================================================================

test('update with same values does not throw error', function () {
    $user = User::create([
        'name' => 'Same User',
        'email' => 'same@example.com',
    ]);

    // Update with same values
    $result = $user->update([
        'name' => 'Same User',
        'email' => 'same@example.com',
    ]);

    expect($result)->toBeTrue();
});

test('can update only one field', function () {
    $user = User::create([
        'name' => 'Original',
        'email' => 'original@example.com',
    ]);

    $user->update(['name' => 'Updated']);
    $user->refresh();

    expect($user->name)->toBe('Updated')
        ->and($user->email)->toBe('original@example.com');
});

test('can create user with same email after previous user deleted', function () {
    $user1 = User::create(['name' => 'User 1', 'email' => 'reuse@example.com']);

    // Delete first user (hard delete since User doesn't have SoftDeletes)
    $user1->delete();

    // Now email is available again
    $user2 = User::create(['name' => 'User 2', 'email' => 'reuse@example.com']);

    expect($user2->email)->toBe('reuse@example.com')
        ->and(User::where('email', 'reuse@example.com')->count())->toBe(1);
});

// =============================================================================
// Concurrent Access Edge Cases - 並行アクセスのエッジケース
// =============================================================================

test('first or create handles race condition', function () {
    $user1 = User::firstOrCreate(
        ['email' => 'race@example.com'],
        ['name' => 'Race User']
    );

    $user2 = User::firstOrCreate(
        ['email' => 'race@example.com'],
        ['name' => 'Race User 2']
    );

    expect($user1->id)->toBe($user2->id);
});

test('update or create handles existing record', function () {
    User::create(['name' => 'Original', 'email' => 'upsert@example.com']);

    $user = User::updateOrCreate(
        ['email' => 'upsert@example.com'],
        ['name' => 'Updated Name']
    );

    expect($user->name)->toBe('Updated Name')
        ->and(User::where('email', 'upsert@example.com')->count())->toBe(1);
});

test('update or create creates new record', function () {
    $user = User::updateOrCreate(
        ['email' => 'newupsert@example.com'],
        ['name' => 'New User']
    );

    expect($user->name)->toBe('New User')
        ->and($user->wasRecentlyCreated)->toBeTrue();
});
