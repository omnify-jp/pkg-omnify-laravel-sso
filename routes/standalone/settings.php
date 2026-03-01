<?php

use Illuminate\Support\Facades\Route;
use Omnify\Core\Http\Controllers\Standalone\ChangePasswordController;
use Omnify\Core\Http\Controllers\Standalone\SettingsController;

/*
|--------------------------------------------------------------------------
| User Settings Routes
|--------------------------------------------------------------------------
|
| Settings page for authenticated users: account, security, IAM.
| Only loaded when mode = 'standalone' and settings_enabled = true.
|
*/

$prefix = config('omnify-auth.settings.prefix', 'settings');
$authMiddleware = config('omnify-auth.auth_middleware', ['web', 'auth']);

Route::prefix($prefix)
    ->name('settings.')
    ->middleware($authMiddleware)
    ->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('index');
        Route::get('/account', [SettingsController::class, 'account'])->name('account');
        Route::get('/security', [SettingsController::class, 'security'])->name('security');
        Route::put('/password', [ChangePasswordController::class, 'update'])->name('password.update');
    });
