<?php

/**
 * SyncFromConsoleCommand is_standalone Tests
 *
 * Console同期コマンドのテスト
 * Tests that sync command sets is_standalone = false for all synced data.
 */

use Omnify\Core\Models\Branch;
use Omnify\Core\Models\User;
use Omnify\Core\Services\ConsoleApiService;

beforeEach(function () {
    config()->set('omnify-auth.mode', 'console');
    config()->set('omnify-auth.service.secret', 'test-secret');
});

// =============================================================================
// Sync Command creates console data
// =============================================================================

describe('sso:sync-from-console sets is_standalone = false', function () {
    test('synced branches have is_standalone = false', function () {
        $consoleApi = \Mockery::mock(ConsoleApiService::class);
        $consoleApi->shouldReceive('getServiceBranches')
            ->with('test-org')
            ->andReturn([
                [
                    'id' => 'branch-uuid-001',
                    'slug' => 'tokyo-hq',
                    'name' => 'Tokyo HQ',
                    'organization_id' => 'org-uuid-001',
                    'is_headquarters' => true,
                    'is_active' => true,
                ],
                [
                    'id' => 'branch-uuid-002',
                    'slug' => 'osaka',
                    'name' => 'Osaka Branch',
                    'organization_id' => 'org-uuid-001',
                    'is_headquarters' => false,
                    'is_active' => true,
                ],
            ]);
        $consoleApi->shouldReceive('getServiceUsers')
            ->andReturn([]);

        $this->app->instance(ConsoleApiService::class, $consoleApi);

        $this->artisan('sso:sync-from-console', ['--organization' => 'test-org'])
            ->assertSuccessful();

        $branches = Branch::all();

        expect($branches)->toHaveCount(2);
        $branches->each(fn ($b) => expect($b->is_standalone)->toBeFalse());
    });

    test('synced users have is_standalone = false', function () {
        $consoleApi = \Mockery::mock(ConsoleApiService::class);
        $consoleApi->shouldReceive('getServiceBranches')
            ->andReturn([]);
        $consoleApi->shouldReceive('getServiceUsers')
            ->with('test-org', 1, 100)
            ->andReturn([
                [
                    'id' => 'user-uuid-001',
                    'email' => 'sync-user@example.com',
                    'name' => 'Sync User',
                    'organization_id' => 'org-uuid-001',
                ],
            ]);

        $this->app->instance(ConsoleApiService::class, $consoleApi);

        $this->artisan('sso:sync-from-console', ['--organization' => 'test-org'])
            ->assertSuccessful();

        $user = User::where('console_user_id', 'user-uuid-001')->first();

        expect($user)->not->toBeNull()
            ->and($user->is_standalone)->toBeFalse()
            ->and($user->email)->toBe('sync-user@example.com');
    });

    test('synced data is invisible in standalone mode', function () {
        $consoleApi = \Mockery::mock(ConsoleApiService::class);
        $consoleApi->shouldReceive('getServiceBranches')
            ->andReturn([
                [
                    'id' => 'branch-uuid-001',
                    'slug' => 'branch-1',
                    'name' => 'Branch 1',
                    'organization_id' => 'org-uuid-001',
                    'is_headquarters' => false,
                    'is_active' => true,
                ],
            ]);
        $consoleApi->shouldReceive('getServiceUsers')
            ->andReturn([
                [
                    'id' => 'user-uuid-001',
                    'email' => 'sync@example.com',
                    'name' => 'Sync',
                    'organization_id' => 'org-uuid-001',
                ],
            ]);

        $this->app->instance(ConsoleApiService::class, $consoleApi);

        $this->artisan('sso:sync-from-console', ['--organization' => 'test-org'])
            ->assertSuccessful();

        // Console mode sees data
        config()->set('omnify-auth.mode', 'console');
        expect(Branch::currentMode()->count())->toBe(1)
            ->and(User::currentMode()->count())->toBe(1);

        // Standalone mode does NOT see data
        config()->set('omnify-auth.mode', 'standalone');
        expect(Branch::currentMode()->count())->toBe(0)
            ->and(User::currentMode()->count())->toBe(0);
    });

    test('re-sync preserves is_standalone = false on existing records', function () {
        $branchData = [
            [
                'id' => 'branch-uuid-001',
                'slug' => 'branch-1',
                'name' => 'Branch 1',
                'organization_id' => 'org-uuid-001',
                'is_headquarters' => false,
                'is_active' => true,
            ],
        ];

        $consoleApi = \Mockery::mock(ConsoleApiService::class);
        $consoleApi->shouldReceive('getServiceBranches')
            ->andReturn($branchData);
        $consoleApi->shouldReceive('getServiceUsers')
            ->andReturn([]);

        $this->app->instance(ConsoleApiService::class, $consoleApi);

        // First sync
        $this->artisan('sso:sync-from-console', ['--organization' => 'test-org'])
            ->assertSuccessful();

        // Second sync (same data)
        $consoleApi2 = \Mockery::mock(ConsoleApiService::class);
        $consoleApi2->shouldReceive('getServiceBranches')
            ->andReturn($branchData);
        $consoleApi2->shouldReceive('getServiceUsers')
            ->andReturn([]);

        $this->app->instance(ConsoleApiService::class, $consoleApi2);

        $this->artisan('sso:sync-from-console', ['--organization' => 'test-org'])
            ->assertSuccessful();

        $branches = Branch::all();
        expect($branches)->toHaveCount(1);
        expect($branches->first()->is_standalone)->toBeFalse();
    });
});

// =============================================================================
// Command validation
// =============================================================================

describe('command validation', function () {
    test('fails in standalone mode', function () {
        config()->set('omnify-auth.mode', 'standalone');

        $this->artisan('sso:sync-from-console', ['--organization' => 'test-org'])
            ->assertFailed();
    });

    test('fails without service secret', function () {
        config()->set('omnify-auth.service.secret', '');

        $this->artisan('sso:sync-from-console', ['--organization' => 'test-org'])
            ->assertFailed();
    });

    test('fails without organization option', function () {
        $this->artisan('sso:sync-from-console')
            ->assertFailed();
    });
});
