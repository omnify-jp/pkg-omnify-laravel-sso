<?php

namespace PragmaRX\Google2FA;

use Illuminate\Support\Str;

/**
 * Google2FA Stub for Testing
 *
 * Provides a test-friendly implementation of the Google2FA library.
 * The real library is not a dependency of this package (host app provides it).
 * This stub allows feature tests to exercise 2FA controller logic.
 *
 * Static properties allow tests to control verification behavior.
 */
class Google2FA
{
    /**
     * Controls what verifyKey() returns. Set in tests to simulate valid/invalid codes.
     */
    public static bool $verifyKeyResult = true;

    /**
     * The secret key that will be returned by generateSecretKey().
     */
    public static string $generatedSecret = 'JBSWY3DPEHPK3PXP1234567890ABCDEF';

    /**
     * Reset static state between tests.
     */
    public static function resetTestState(): void
    {
        static::$verifyKeyResult = true;
        static::$generatedSecret = 'JBSWY3DPEHPK3PXP1234567890ABCDEF';
    }

    /**
     * Generate a "secret key" of the given length.
     */
    public function generateSecretKey(int $length = 16): string
    {
        return static::$generatedSecret;
    }

    /**
     * Generate OTP Auth URL for QR code.
     */
    public function getQRCodeUrl(string $company, string $holder, string $secret): string
    {
        return "otpauth://totp/{$company}:{$holder}?secret={$secret}&issuer={$company}";
    }

    /**
     * Verify a TOTP key. Result controlled by static::$verifyKeyResult.
     */
    public function verifyKey(string $secret, string $key, ?int $window = null): bool
    {
        return static::$verifyKeyResult;
    }

    /**
     * Get the current OTP for a given secret. Returns a fixed test code.
     */
    public function getCurrentOtp(string $secret): string
    {
        return '123456';
    }
}
