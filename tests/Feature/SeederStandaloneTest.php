<?php

/**
 * SsoStandaloneSeeder is_standalone Tests
 *
 * スタンドアローンシーダーのテスト
 * Tests that seeder sets is_standalone = true for all seeded data.
 *
 * NOTE: Manually instantiates the seeder since Orchestra Testbench
 * may not resolve package seeders via $this->seed().
 */

use Omnify\Core\Database\Seeders\SsoStandaloneSeeder;
use Omnify\Core\Models\Branch;
use Omnify\Core\Models\Location;
use Omnify\Core\Models\User;

beforeEach(function () {
    config()->set('omnify-auth.mode', 'standalone');
});

/**
 * Run the seeder — instantiate and call run() directly.
 */
function runStandaloneSeeder(): void
{
    $seeder = new SsoStandaloneSeeder;
    $seeder->run();
}

// =============================================================================
// Seeder creates standalone data
// =============================================================================

describe('SsoStandaloneSeeder', function () {
    test('seeded branches have is_standalone = true', function () {
        runStandaloneSeeder();

        $branches = Branch::all();

        expect($branches->count())->toBeGreaterThan(0);
        $branches->each(fn ($branch) => expect($branch->is_standalone)->toBeTrue());
    });

    test('seeded locations have is_standalone = true', function () {
        runStandaloneSeeder();

        $locations = Location::all();

        expect($locations->count())->toBeGreaterThan(0);
        $locations->each(fn ($loc) => expect($loc->is_standalone)->toBeTrue());
    });

    test('seeded users have is_standalone = true', function () {
        runStandaloneSeeder();

        $users = User::all();

        expect($users->count())->toBeGreaterThan(0);
        $users->each(fn ($user) => expect($user->is_standalone)->toBeTrue());
    });

    test('seeded data is visible in standalone mode', function () {
        runStandaloneSeeder();

        config()->set('omnify-auth.mode', 'standalone');

        expect(Branch::currentMode()->count())->toBeGreaterThan(0)
            ->and(Location::currentMode()->count())->toBeGreaterThan(0)
            ->and(User::currentMode()->count())->toBeGreaterThan(0);
    });

    test('seeded data is invisible in console mode', function () {
        runStandaloneSeeder();

        config()->set('omnify-auth.mode', 'console');

        expect(Branch::currentMode()->count())->toBe(0)
            ->and(Location::currentMode()->count())->toBe(0)
            ->and(User::currentMode()->count())->toBe(0);
    });

    test('seeder is idempotent — re-seeding preserves is_standalone', function () {
        runStandaloneSeeder();
        $countBefore = Branch::standalone()->count();

        runStandaloneSeeder();
        $countAfter = Branch::standalone()->count();

        expect($countAfter)->toBe($countBefore);
        Branch::all()->each(fn ($b) => expect($b->is_standalone)->toBeTrue());
    });
});
