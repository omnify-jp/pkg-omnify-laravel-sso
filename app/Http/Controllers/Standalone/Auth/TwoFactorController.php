<?php

declare(strict_types=1);

namespace Omnify\Core\Http\Controllers\Standalone\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

/**
 * Handles TOTP-based two-factor authentication.
 *
 * Setup flow: setup() → enable() → (show recovery codes once)
 * Enforcement flow: showChallenge() → verify()
 * Management: disable(), recoveryCodes(), regenerateCodes()
 *
 * Secrets and recovery codes are encrypted at rest using Laravel's encrypt()/decrypt().
 */
class TwoFactorController extends Controller
{
    /**
     * Initiate 2FA setup: generate a new TOTP secret, store in session, return QR code URL.
     *
     * The secret is stored in session as '2fa_pending_secret' until the user confirms
     * a valid TOTP code via enable(). This prevents partially-enabled 2FA.
     */
    public function setup(Request $request): Response
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey(32);

        $request->session()->put('2fa_pending_secret', $secret);

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $request->user()->email,
            $secret
        );

        $pagePath = config('omnify-auth.standalone.pages.security', 'security/index');

        return Inertia::render($pagePath, [
            'qrCodeUrl' => $qrCodeUrl,
            'secret' => $secret,
            'step' => 'setup',
        ]);
    }

    /**
     * Enable 2FA after verifying the user's first TOTP code.
     *
     * Requires '2fa_pending_secret' in session from setup(). Validates the provided
     * TOTP code, generates recovery codes, and persists the encrypted secret.
     */
    public function enable(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $pendingSecret = $request->session()->get('2fa_pending_secret');

        if (! $pendingSecret) {
            return back()->withErrors(['code' => 'No pending 2FA setup found. Please start setup again.']);
        }

        $google2fa = new Google2FA;

        if (! $google2fa->verifyKey($pendingSecret, $request->input('code'))) {
            return back()->withErrors(['code' => 'Invalid verification code. Please try again.']);
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $request->user()->update([
            'google2fa_secret' => encrypt($pendingSecret),
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
            'two_factor_confirmed_at' => now(),
        ]);

        $request->session()->forget('2fa_pending_secret');
        $request->session()->put('2fa_verified_at', now()->timestamp);

        return back()->with([
            'recoveryCodes' => $recoveryCodes,
            'twoFactorEnabled' => true,
        ]);
    }

    /**
     * Disable 2FA after verifying the current TOTP code.
     *
     * Clears the secret, recovery codes, and confirmation timestamp.
     */
    public function disable(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! $user->google2fa_secret) {
            return back()->withErrors(['code' => '2FA is not enabled.']);
        }

        $google2fa = new Google2FA;

        try {
            $secret = decrypt($user->google2fa_secret);
        } catch (\Throwable) {
            return back()->withErrors(['code' => 'Failed to read 2FA secret. Please contact support.']);
        }

        if (! $google2fa->verifyKey($secret, $request->input('code'))) {
            return back()->withErrors(['code' => 'Invalid verification code.']);
        }

        $user->update([
            'google2fa_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        $request->session()->forget('2fa_verified_at');

        return back()->with(['twoFactorDisabled' => true]);
    }

    /**
     * Show the 2FA challenge page (Inertia render).
     *
     * This is shown when a user with 2FA enabled hasn't verified TOTP in the current session.
     */
    public function showChallenge(Request $request): Response
    {
        $pagePath = config('omnify-auth.standalone.pages.two_factor_challenge', 'auth/two-factor-challenge');

        return Inertia::render($pagePath);
    }

    /**
     * Verify a TOTP code or recovery code to pass the 2FA challenge.
     *
     * Tries TOTP first. Falls back to recovery codes. On success, sets '2fa_verified_at'
     * in session and redirects to the intended URL.
     */
    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! $user->google2fa_secret) {
            return redirect()->intended(route(config('omnify-auth.standalone.redirect_after_login', 'dashboard')));
        }

        try {
            $secret = decrypt($user->google2fa_secret);
        } catch (\Throwable) {
            return back()->withErrors(['code' => 'Failed to read 2FA secret. Please contact support.']);
        }

        $code = $request->input('code');
        $google2fa = new Google2FA;

        // Try TOTP verification first.
        if ($google2fa->verifyKey($secret, $code)) {
            $request->session()->put('2fa_verified_at', now()->timestamp);

            return redirect()->intended(route(config('omnify-auth.standalone.redirect_after_login', 'dashboard')));
        }

        // Try recovery code verification.
        if ($this->attemptRecoveryCode($request, $code)) {
            $request->session()->put('2fa_verified_at', now()->timestamp);

            return redirect()->intended(route(config('omnify-auth.standalone.redirect_after_login', 'dashboard')));
        }

        return back()->withErrors(['code' => 'Invalid verification code or recovery code.']);
    }

    /**
     * Return the current decrypted recovery codes for display.
     *
     * Only accessible when 2FA is verified in session (protect via middleware or gate).
     */
    public function recoveryCodes(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if (! $user->two_factor_recovery_codes) {
            return response()->json(['codes' => []]);
        }

        try {
            $codes = json_decode(decrypt($user->two_factor_recovery_codes), true);
        } catch (\Throwable) {
            return back()->withErrors(['message' => 'Failed to read recovery codes.']);
        }

        return response()->json(['codes' => $codes ?? []]);
    }

    /**
     * Regenerate a fresh set of 8 recovery codes.
     */
    public function regenerateCodes(Request $request): RedirectResponse
    {
        $codes = $this->generateRecoveryCodes();

        $request->user()->update([
            'two_factor_recovery_codes' => encrypt(json_encode($codes)),
        ]);

        return back()->with(['recoveryCodes' => $codes]);
    }

    /**
     * Generate 8 recovery codes in the format XXXX-XXXX-XXXX.
     *
     * @return array<string>
     */
    protected function generateRecoveryCodes(): array
    {
        $count = (int) config('security.two_factor.recovery_codes_count', 8);
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = Str::upper(Str::random(4).'-'.Str::random(4).'-'.Str::random(4));
        }

        return $codes;
    }

    /**
     * Attempt to verify a recovery code, consuming it on success.
     */
    protected function attemptRecoveryCode(Request $request, string $code): bool
    {
        $user = $request->user();

        if (! $user->two_factor_recovery_codes) {
            return false;
        }

        try {
            $codes = json_decode(decrypt($user->two_factor_recovery_codes), true);
        } catch (\Throwable) {
            return false;
        }

        if (! is_array($codes)) {
            return false;
        }

        $normalizedInput = Str::upper(trim($code));
        $matchIndex = null;

        foreach ($codes as $index => $storedCode) {
            if (Str::upper(trim((string) $storedCode)) === $normalizedInput) {
                $matchIndex = $index;
                break;
            }
        }

        if ($matchIndex === null) {
            return false;
        }

        // Consume the recovery code by removing it from the list.
        array_splice($codes, $matchIndex, 1);

        $user->update([
            'two_factor_recovery_codes' => encrypt(json_encode(array_values($codes))),
        ]);

        return true;
    }
}
