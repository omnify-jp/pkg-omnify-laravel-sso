<?php

use Illuminate\Support\Facades\Route;
use Omnify\SsoClient\Http\Controllers\Auth\StandaloneLoginController;
use Omnify\SsoClient\Http\Controllers\Auth\StandaloneNewPasswordController;
use Omnify\SsoClient\Http\Controllers\Auth\StandalonePasswordResetController;

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
