<?php

/**
 * TwoFactorController Feature Tests
 *
 * Tests for TOTP-based two-factor authentication (omnify-auth.mode = 'standalone').
 * Covers: setup, enable, disable, showChallenge, verify, recoveryCodes, regenerateCodes.
 *
 * Routes (auth middleware):
 *   POST /2fa/setup                    → setup
 *   POST /2fa/enable                   → enable
 *   POST /2fa/disable                  → disable
 *   GET  /2fa/challenge                → showChallenge
 *   POST /2fa/challenge                → verify
 *   GET  /2fa/recovery-codes           → recoveryCodes
 *   POST /2fa/recovery-codes/regenerate → regenerateCodes
 *
 * Uses a Google2FA stub (tests/Fixtures/Stubs/PragmaRX/Google2FA/Google2FA.php)
 * because the real library is a host-app dependency, not bundled with this package.
 */

// Load the Google2FA stub before any test touches the controller.
require_once __DIR__.'/../../Fixtures/Stubs/PragmaRX/Google2FA/Google2FA.php';

use Omnify\Core\Models\User;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    config(['omnify-auth.mode' => 'standalone']);

    // Reset stub state before each test
    Google2FA::resetTestState();

    // Register a dashboard route for redirect after 2FA verify
    $this->app->make('router')
        ->get('/dashboard', fn () => response('Dashboard'))
        ->name('dashboard');
});

// =============================================================================
// Helper: create user with 2FA enabled
// =============================================================================

function createUserWith2FA(array $overrides = []): User
{
    $secret = 'JBSWY3DPEHPK3PXP1234567890ABCDEF';
    $recoveryCodes = [
        'AAAA-BBBB-CCCC',
        'DDDD-EEEE-FFFF',
        'GGGG-HHHH-IIII',
        'JJJJ-KKKK-LLLL',
        'MMMM-NNNN-OOOO',
        'PPPP-QQQQ-RRRR',
        'SSSS-TTTT-UUUU',
        'VVVV-WWWW-XXXX',
    ];

    return User::factory()->withPassword('password')->create(array_merge([
        'google2fa_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        'two_factor_confirmed_at' => now(),
    ], $overrides));
}

// =============================================================================
// Setup — POST /2fa/setup
// =============================================================================

describe('setup', function () {
    test('setup requires authentication', function () {
        $response = $this->post('/2fa/setup');

        $response->assertRedirect('/login');
    });

    test('setup generates secret and returns QR code', function () {
        $user = User::factory()->withPassword('password')->create();

        $response = $this->actingAs($user)
            ->post('/2fa/setup', [], ['X-Inertia' => 'true']);

        $response->assertStatus(200)
            ->assertJson([
                'props' => [
                    'secret' => Google2FA::$generatedSecret,
                    'step' => 'setup',
                ],
            ]);
    });

    test('setup stores pending secret in session', function () {
        $user = User::factory()->withPassword('password')->create();

        $this->actingAs($user)->post('/2fa/setup');

        expect(session('2fa_pending_secret'))->toBe(Google2FA::$generatedSecret);
    });
});

// =============================================================================
// Enable — POST /2fa/enable
// =============================================================================

describe('enable', function () {
    test('enable requires authentication', function () {
        $response = $this->post('/2fa/enable', ['code' => '123456']);

        $response->assertRedirect('/login');
    });

    test('enable requires code field', function () {
        $user = User::factory()->withPassword('password')->create();

        $response = $this->actingAs($user)->post('/2fa/enable', []);

        $response->assertSessionHasErrors(['code']);
    });

    test('enable fails with wrong code', function () {
        Google2FA::$verifyKeyResult = false;

        $user = User::factory()->withPassword('password')->create();
        $pendingSecret = 'JBSWY3DPEHPK3PXP1234567890ABCDEF';

        $response = $this->actingAs($user)
            ->withSession(['2fa_pending_secret' => $pendingSecret])
            ->post('/2fa/enable', ['code' => '000000']);

        $response->assertSessionHasErrors(['code']);
    });

    test('enable fails when no pending secret in session', function () {
        $user = User::factory()->withPassword('password')->create();

        $response = $this->actingAs($user)
            ->post('/2fa/enable', ['code' => '123456']);

        $response->assertSessionHasErrors(['code']);
    });

    test('enable succeeds with valid TOTP code', function () {
        Google2FA::$verifyKeyResult = true;

        $user = User::factory()->withPassword('password')->create();
        $pendingSecret = 'JBSWY3DPEHPK3PXP1234567890ABCDEF';

        $response = $this->actingAs($user)
            ->withSession(['2fa_pending_secret' => $pendingSecret])
            ->post('/2fa/enable', ['code' => '123456']);

        $response->assertSessionHasNoErrors();

        $user->refresh();
        expect($user->google2fa_secret)->not->toBeNull()
            ->and($user->two_factor_confirmed_at)->not->toBeNull();
    });

    test('enable generates 8 recovery codes', function () {
        Google2FA::$verifyKeyResult = true;

        $user = User::factory()->withPassword('password')->create();
        $pendingSecret = 'JBSWY3DPEHPK3PXP1234567890ABCDEF';

        $this->actingAs($user)
            ->withSession(['2fa_pending_secret' => $pendingSecret])
            ->post('/2fa/enable', ['code' => '123456']);

        $user->refresh();
        $codes = json_decode(decrypt($user->two_factor_recovery_codes), true);

        expect($codes)->toBeArray()
            ->and($codes)->toHaveCount(8);

        // Verify format: XXXX-XXXX-XXXX
        foreach ($codes as $code) {
            expect($code)->toMatch('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/');
        }
    });

    test('enable sets two_factor_confirmed_at', function () {
        Google2FA::$verifyKeyResult = true;

        $user = User::factory()->withPassword('password')->create();
        $pendingSecret = 'JBSWY3DPEHPK3PXP1234567890ABCDEF';

        $this->actingAs($user)
            ->withSession(['2fa_pending_secret' => $pendingSecret])
            ->post('/2fa/enable', ['code' => '123456']);

        $user->refresh();
        expect($user->two_factor_confirmed_at)->not->toBeNull()
            ->and($user->two_factor_confirmed_at)->toBeInstanceOf(\Carbon\Carbon::class);
    });
});

// =============================================================================
// Disable — POST /2fa/disable
// =============================================================================

describe('disable', function () {
    test('disable requires code', function () {
        $user = createUserWith2FA();

        $response = $this->actingAs($user)->post('/2fa/disable', []);

        $response->assertSessionHasErrors(['code']);
    });

    test('disable removes 2FA when valid code provided', function () {
        Google2FA::$verifyKeyResult = true;

        $user = createUserWith2FA();

        $response = $this->actingAs($user)->post('/2fa/disable', ['code' => '123456']);

        $response->assertSessionHasNoErrors();

        $user->refresh();
        expect($user->google2fa_secret)->toBeNull()
            ->and($user->two_factor_recovery_codes)->toBeNull()
            ->and($user->two_factor_confirmed_at)->toBeNull();
    });

    test('disable fails with wrong code', function () {
        Google2FA::$verifyKeyResult = false;

        $user = createUserWith2FA();

        $response = $this->actingAs($user)->post('/2fa/disable', ['code' => '000000']);

        $response->assertSessionHasErrors(['code']);

        $user->refresh();
        expect($user->google2fa_secret)->not->toBeNull();
    });
});

// =============================================================================
// Show Challenge — GET /2fa/challenge
// =============================================================================

describe('show challenge', function () {
    test('show challenge requires authentication', function () {
        $response = $this->get('/2fa/challenge');

        $response->assertRedirect('/login');
    });

    test('show challenge renders Inertia page', function () {
        $user = User::factory()->withPassword('password')->create();

        $response = $this->actingAs($user)
            ->get('/2fa/challenge', ['X-Inertia' => 'true']);

        $response->assertStatus(200)
            ->assertJson(['component' => 'auth/two-factor-challenge']);
    });
});

// =============================================================================
// Verify — POST /2fa/challenge
// =============================================================================

describe('verify', function () {
    test('verify requires code', function () {
        $user = createUserWith2FA();

        $response = $this->actingAs($user)->post('/2fa/challenge', []);

        $response->assertSessionHasErrors(['code']);
    });

    test('verify succeeds with valid TOTP code', function () {
        Google2FA::$verifyKeyResult = true;

        $user = createUserWith2FA();

        $response = $this->actingAs($user)->post('/2fa/challenge', ['code' => '123456']);

        $response->assertRedirect(route('dashboard'));
    });

    test('verify sets session 2fa_verified_at flag', function () {
        Google2FA::$verifyKeyResult = true;

        $user = createUserWith2FA();

        $this->actingAs($user)->post('/2fa/challenge', ['code' => '123456']);

        expect(session('2fa_verified_at'))->not->toBeNull()
            ->and(session('2fa_verified_at'))->toBeInt();
    });

    test('verify accepts recovery code as fallback', function () {
        // TOTP verification fails, but recovery code should work
        Google2FA::$verifyKeyResult = false;

        $user = createUserWith2FA();

        $response = $this->actingAs($user)
            ->post('/2fa/challenge', ['code' => 'AAAA-BBBB-CCCC']);

        $response->assertRedirect(route('dashboard'));
    });

    test('verify consumes used recovery code', function () {
        Google2FA::$verifyKeyResult = false;

        $user = createUserWith2FA();

        $this->actingAs($user)
            ->post('/2fa/challenge', ['code' => 'AAAA-BBBB-CCCC']);

        $user->refresh();
        $codes = json_decode(decrypt($user->two_factor_recovery_codes), true);

        expect($codes)->toHaveCount(7)
            ->and($codes)->not->toContain('AAAA-BBBB-CCCC');
    });

    test('verify fails with wrong code', function () {
        Google2FA::$verifyKeyResult = false;

        $user = createUserWith2FA();

        $response = $this->actingAs($user)
            ->post('/2fa/challenge', ['code' => '999999']);

        $response->assertSessionHasErrors(['code']);
    });

    test('verify fails with already used recovery code', function () {
        Google2FA::$verifyKeyResult = false;

        $user = createUserWith2FA();

        // Use the recovery code once
        $this->actingAs($user)
            ->post('/2fa/challenge', ['code' => 'AAAA-BBBB-CCCC']);

        // Attempt to use the same recovery code again
        $response = $this->actingAs($user)
            ->post('/2fa/challenge', ['code' => 'AAAA-BBBB-CCCC']);

        $response->assertSessionHasErrors(['code']);
    });
});

// =============================================================================
// Recovery Codes — GET /2fa/recovery-codes
// =============================================================================

describe('recovery codes', function () {
    test('recovery codes requires authentication', function () {
        $response = $this->getJson('/2fa/recovery-codes');

        $response->assertStatus(401);
    });

    test('recovery codes returns current codes', function () {
        $user = createUserWith2FA();

        $response = $this->actingAs($user)->getJson('/2fa/recovery-codes');

        $response->assertOk()
            ->assertJsonCount(8, 'codes')
            ->assertJson(['codes' => [
                'AAAA-BBBB-CCCC',
                'DDDD-EEEE-FFFF',
                'GGGG-HHHH-IIII',
                'JJJJ-KKKK-LLLL',
                'MMMM-NNNN-OOOO',
                'PPPP-QQQQ-RRRR',
                'SSSS-TTTT-UUUU',
                'VVVV-WWWW-XXXX',
            ]]);
    });

    test('recovery codes returns empty array when 2FA not enabled', function () {
        $user = User::factory()->withPassword('password')->create();

        $response = $this->actingAs($user)->getJson('/2fa/recovery-codes');

        $response->assertOk()
            ->assertJson(['codes' => []]);
    });
});

// =============================================================================
// Regenerate Codes — POST /2fa/recovery-codes/regenerate
// =============================================================================

describe('regenerate codes', function () {
    test('regenerate requires authentication', function () {
        $response = $this->post('/2fa/recovery-codes/regenerate');

        $response->assertRedirect('/login');
    });

    test('regenerate creates new recovery codes', function () {
        $user = createUserWith2FA();

        $originalCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);

        $response = $this->actingAs($user)->post('/2fa/recovery-codes/regenerate');

        $response->assertSessionHasNoErrors();

        $user->refresh();
        $newCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);

        expect($newCodes)->toBeArray()
            ->and($newCodes)->toHaveCount(8)
            ->and($newCodes)->not->toBe($originalCodes);

        // Verify format: XXXX-XXXX-XXXX
        foreach ($newCodes as $code) {
            expect($code)->toMatch('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/');
        }
    });
});
