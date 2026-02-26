<?php

use Illuminate\Support\Facades\Route;
use Omnify\SsoClient\Http\Controllers\Auth\StandaloneLoginController;
use Omnify\SsoClient\Http\Controllers\Auth\StandaloneNewPasswordController;
use Omnify\SsoClient\Http\Controllers\Auth\StandalonePasswordResetController;
use Omnify\SsoClient\Http\Controllers\Auth\TwoFactorController;

/*
|--------------------------------------------------------------------------
| Standalone Auth Routes
|--------------------------------------------------------------------------
|
| Routes for email/password authentication (mode = 'standalone').
| These routes are loaded by the SsoClientServiceProvider.
|
*/

$prefix = config('omnify-auth.standalone.route_prefix', '');
$guestMiddleware = config('omnify-auth.guest_middleware', ['web', 'guest']);
$authMiddleware = config('omnify-auth.auth_middleware', ['web', 'auth']);

Route::prefix($prefix)
    ->middleware($guestMiddleware)
    ->group(function () {
        Route::get('/login', [StandaloneLoginController::class, 'showLogin'])->name('login');
        Route::post('/login', [StandaloneLoginController::class, 'login']);
    });

Route::prefix($prefix)
    ->middleware($authMiddleware)
    ->group(function () {
        Route::post('/logout', [StandaloneLoginController::class, 'logout'])->name('logout');
    });

if (config('omnify-auth.standalone.password_reset', true)) {
    Route::prefix($prefix)
        ->middleware($guestMiddleware)
        ->group(function () {
            Route::get('/forgot-password', [StandalonePasswordResetController::class, 'create'])->name('password.request');
            Route::post('/forgot-password', [StandalonePasswordResetController::class, 'store'])->name('password.email');
            Route::get('/reset-password/{token}', [StandaloneNewPasswordController::class, 'create'])->name('password.reset');
            Route::post('/reset-password', [StandaloneNewPasswordController::class, 'store'])->name('password.update');
        });
}

// 2FA setup and management routes (authenticated users)
Route::prefix($prefix)
    ->middleware($authMiddleware)
    ->group(function () {
        Route::post('/2fa/setup', [TwoFactorController::class, 'setup'])->name('auth.two-factor.setup');
        Route::post('/2fa/enable', [TwoFactorController::class, 'enable'])->name('auth.two-factor.enable');
        Route::post('/2fa/disable', [TwoFactorController::class, 'disable'])->name('auth.two-factor.disable');
        Route::get('/2fa/recovery-codes', [TwoFactorController::class, 'recoveryCodes'])->name('auth.two-factor.recovery-codes');
        Route::post('/2fa/recovery-codes/regenerate', [TwoFactorController::class, 'regenerateCodes'])->name('auth.two-factor.regenerate-codes');
    });

// 2FA challenge routes (authenticated but not yet 2FA-verified)
Route::prefix($prefix)
    ->middleware($authMiddleware)
    ->group(function () {
        Route::get('/2fa/challenge', [TwoFactorController::class, 'showChallenge'])->name('auth.two-factor-challenge');
        Route::post('/2fa/challenge', [TwoFactorController::class, 'verify'])->name('auth.two-factor.verify');
    });
