<?php

/**
 * AdminAdminController Feature Tests (Standalone Mode)
 *
 * 管理者（スーパー管理者）管理コントローラーのフィーチャーテスト
 * Tests full CRUD for admin account management in standalone mode.
 *
 * Routes:
 *   GET    /admin/admins           → index   (Inertia page)
 *   POST   /admin/admins           → store   (JSON 201)
 *   PUT    /admin/admins/{admin}   → update  (JSON 200)
 *   DELETE /admin/admins/{admin}   → destroy (JSON 200)
 */

use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Omnify\Core\Models\Admin;
use Omnify\Core\Models\User;

beforeEach(function () {
    config()->set('omnify-auth.mode', 'standalone');
});

// =============================================================================
// Auth Guard — 認証ガード
// =============================================================================

describe('auth guard', function () {
    test('unauthenticated GET /admin/admins redirects to admin login', function () {
        $response = $this->get('/admin/admins');

        $response->assertRedirect('/admin/login');
    });

    test('unauthenticated POST /admin/admins returns 401', function () {
        $response = $this->postJson('/admin/admins', [
            'name' => 'New Admin',
            'email' => 'new@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401);
    });

    test('unauthenticated PUT /admin/admins/{id} returns 401', function () {
        $admin = Admin::factory()->create();

        $response = $this->putJson("/admin/admins/{$admin->id}", [
            'name' => 'Updated',
        ]);

        $response->assertStatus(401);
    });

    test('unauthenticated DELETE /admin/admins/{id} redirects', function () {
        $admin = Admin::factory()->create();

        $response = $this->delete("/admin/admins/{$admin->id}");

        $response->assertRedirect('/admin/login');
    });
});

// =============================================================================
// Index — 一覧表示
// =============================================================================

describe('index', function () {
    beforeEach(function () {
        // Use a name that sorts predictably (ASCII) for sort tests
        $this->admin = Admin::factory()->create(['name' => 'AAA Auth Admin', 'email' => 'auth@test.com']);
    });

    test('returns admins index Inertia page with correct component name', function () {
        $response = $this->actingAs($this->admin, 'admin')
            ->get('/admin/admins');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/admins/index')
            );
    });

    test('returns paginated admins with 15 per page', function () {
        Admin::factory()->count(20)->create();

        $response = $this->actingAs($this->admin, 'admin')
            ->get('/admin/admins');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/admins/index')
                ->has('admins.data', 15)
                ->where('admins.meta.per_page', 15)
                ->where('admins.meta.total', 21)
                ->where('admins.meta.last_page', 2)
            );
    });

    test('supports search by name via q param', function () {
        Admin::factory()->create(['name' => 'Alice Smith', 'email' => 'alice@test.com']);
        Admin::factory()->create(['name' => 'Bob Jones', 'email' => 'bob@test.com']);
        Admin::factory()->create(['name' => 'Charlie Brown', 'email' => 'charlie@test.com']);

        $response = $this->actingAs($this->admin, 'admin')
            ->get('/admin/admins?q=Alice');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('admins.data', 1)
                ->where('admins.data.0.name', 'Alice Smith')
            );
    });

    test('supports search by email via q param', function () {
        Admin::factory()->create(['name' => 'Admin One', 'email' => 'findme@example.com']);
        Admin::factory()->create(['name' => 'Admin Two', 'email' => 'other@example.com']);

        $response = $this->actingAs($this->admin, 'admin')
            ->get('/admin/admins?q=findme');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('admins.data', 1)
                ->where('admins.data.0.email', 'findme@example.com')
            );
    });

    test('supports sort ascending by name', function () {
        Admin::factory()->create(['name' => 'Charlie Admin', 'email' => 'charlie@test.com']);
        Admin::factory()->create(['name' => 'Alpha Admin', 'email' => 'alpha@test.com']);
        Admin::factory()->create(['name' => 'Bravo Admin', 'email' => 'bravo@test.com']);

        // beforeEach creates 'AAA Auth Admin' which sorts first; so Alpha is at index 1
        $response = $this->actingAs($this->admin, 'admin')
            ->get('/admin/admins?sort=name');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('admins.data.0.name', 'AAA Auth Admin')
                ->where('admins.data.1.name', 'Alpha Admin')
                ->where('admins.data.2.name', 'Bravo Admin')
                ->where('admins.data.3.name', 'Charlie Admin')
            );
    });

    test('supports sort descending by name with - prefix', function () {
        Admin::factory()->create(['name' => 'Charlie Admin', 'email' => 'charlie@test.com']);
        Admin::factory()->create(['name' => 'Alpha Admin', 'email' => 'alpha@test.com']);
        Admin::factory()->create(['name' => 'Bravo Admin', 'email' => 'bravo@test.com']);

        // beforeEach creates 'AAA Auth Admin' which sorts last descending
        $response = $this->actingAs($this->admin, 'admin')
            ->get('/admin/admins?sort=-name');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('admins.data.0.name', 'Charlie Admin')
                ->where('admins.data.1.name', 'Bravo Admin')
                ->where('admins.data.2.name', 'Alpha Admin')
                ->where('admins.data.3.name', 'AAA Auth Admin')
            );
    });

    test('returns correct meta structure with current_page, last_page, per_page, total', function () {
        Admin::factory()->count(5)->create();

        $response = $this->actingAs($this->admin, 'admin')
            ->get('/admin/admins');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('admins.meta.current_page')
                ->has('admins.meta.last_page')
                ->has('admins.meta.per_page')
                ->has('admins.meta.total')
            );
    });
});

// =============================================================================
// Store — 作成
// =============================================================================

describe('store', function () {
    beforeEach(function () {
        $this->admin = Admin::factory()->create();
    });

    test('creates admin with valid data and returns 201', function () {
        $response = $this->actingAs($this->admin, 'admin')
            ->postJson('/admin/admins', [
                'name' => 'New Admin',
                'email' => 'newadmin@test.com',
                'password' => 'password123',
            ]);

        $response->assertStatus(201)
            ->assertJson(['message' => 'Admin created.']);

        $this->assertDatabaseHas('admins', [
            'name' => 'New Admin',
            'email' => 'newadmin@test.com',
        ]);
    });

    test('validates required fields name, email, password', function () {
        $response = $this->actingAs($this->admin, 'admin')
            ->postJson('/admin/admins', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    });

    test('validates email uniqueness', function () {
        Admin::factory()->create(['email' => 'taken@test.com']);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson('/admin/admins', [
                'name' => 'Duplicate Admin',
                'email' => 'taken@test.com',
                'password' => 'password123',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    test('validates password minimum length of 8 characters', function () {
        $response = $this->actingAs($this->admin, 'admin')
            ->postJson('/admin/admins', [
                'name' => 'Short Pass Admin',
                'email' => 'shortpass@test.com',
                'password' => 'short',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    });

    test('created admin has is_active true by default', function () {
        $this->actingAs($this->admin, 'admin')
            ->postJson('/admin/admins', [
                'name' => 'Default Active Admin',
                'email' => 'defaultactive@test.com',
                'password' => 'password123',
            ]);

        $created = Admin::where('email', 'defaultactive@test.com')->first();

        expect($created)->not->toBeNull()
            ->and($created->is_active)->toBeTrue();
    });
});

// =============================================================================
// Update — 更新
// =============================================================================

describe('update', function () {
    beforeEach(function () {
        $this->authAdmin = Admin::factory()->create();
        $this->targetAdmin = Admin::factory()->withPassword('original-password')->create([
            'name' => 'Original Name',
            'email' => 'original@test.com',
            'is_active' => true,
        ]);
    });

    test('updates admin fields and returns 200', function () {
        $response = $this->actingAs($this->authAdmin, 'admin')
            ->putJson("/admin/admins/{$this->targetAdmin->id}", [
                'name' => 'Updated Name',
                'email' => 'updated@test.com',
            ]);

        $response->assertOk()
            ->assertJson(['message' => 'Admin updated.']);

        $this->targetAdmin->refresh();
        expect($this->targetAdmin->name)->toBe('Updated Name')
            ->and($this->targetAdmin->email)->toBe('updated@test.com');
    });

    test('updates email while ignoring self for uniqueness check', function () {
        $response = $this->actingAs($this->authAdmin, 'admin')
            ->putJson("/admin/admins/{$this->targetAdmin->id}", [
                'name' => 'Original Name',
                'email' => 'original@test.com',
            ]);

        $response->assertOk();
    });

    test('validates email uniqueness against other admins', function () {
        Admin::factory()->create(['email' => 'other@test.com']);

        $response = $this->actingAs($this->authAdmin, 'admin')
            ->putJson("/admin/admins/{$this->targetAdmin->id}", [
                'name' => 'Original Name',
                'email' => 'other@test.com',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    test('updates password when provided', function () {
        $this->actingAs($this->authAdmin, 'admin')
            ->putJson("/admin/admins/{$this->targetAdmin->id}", [
                'password' => 'new-password-123',
            ]);

        $this->targetAdmin->refresh();

        expect(Hash::check('new-password-123', $this->targetAdmin->password))->toBeTrue();
    });

    test('skips password update when empty string sent', function () {
        $originalPasswordHash = $this->targetAdmin->password;

        $this->actingAs($this->authAdmin, 'admin')
            ->putJson("/admin/admins/{$this->targetAdmin->id}", [
                'name' => 'Updated Name',
                'password' => '',
            ]);

        $this->targetAdmin->refresh();

        expect($this->targetAdmin->password)->toBe($originalPasswordHash);
    });

    test('updates is_active field', function () {
        $this->actingAs($this->authAdmin, 'admin')
            ->putJson("/admin/admins/{$this->targetAdmin->id}", [
                'is_active' => false,
            ]);

        $this->targetAdmin->refresh();

        expect($this->targetAdmin->is_active)->toBeFalse();
    });
});

// =============================================================================
// Destroy — 削除
// =============================================================================

describe('destroy', function () {
    beforeEach(function () {
        $this->authAdmin = Admin::factory()->create();
    });

    test('deletes other admin and returns 200', function () {
        $target = Admin::factory()->create();

        $response = $this->actingAs($this->authAdmin, 'admin')
            ->deleteJson("/admin/admins/{$target->id}");

        $response->assertOk()
            ->assertJson(['message' => 'Admin deleted.']);
    });

    test('cannot delete self and returns 403', function () {
        $response = $this->actingAs($this->authAdmin, 'admin')
            ->deleteJson("/admin/admins/{$this->authAdmin->id}");

        $response->assertStatus(403)
            ->assertJson(['message' => 'Cannot delete your own account.']);
    });

    test('returns 404 for non-existent admin', function () {
        $nonExistentId = '00000000-0000-0000-0000-000000000000';

        $response = $this->actingAs($this->authAdmin, 'admin')
            ->deleteJson("/admin/admins/{$nonExistentId}");

        $response->assertStatus(404);
    });

    test('database record is actually deleted after destroy', function () {
        $target = Admin::factory()->create();
        $targetId = $target->id;

        $this->actingAs($this->authAdmin, 'admin')
            ->deleteJson("/admin/admins/{$targetId}");

        $this->assertDatabaseMissing('admins', ['id' => $targetId]);
    });
});

// =============================================================================
// Security — セキュリティ
// =============================================================================

describe('security', function () {
    test('regular web User cannot access admin admins routes', function () {
        $user = User::factory()->standalone()->withPassword('password')->create();

        $response = $this->actingAs($user, 'web')
            ->get('/admin/admins');

        $response->assertRedirect('/admin/login');
    });

    test('password field is not returned in index response data', function () {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')
            ->get('/admin/admins');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/admins/index')
                ->where('admins.data', fn ($data) => collect($data)->every(
                    fn ($item) => ! array_key_exists('password', $item)
                ))
            );
    });
});
