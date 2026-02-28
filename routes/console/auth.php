<?php

use Illuminate\Support\Facades\Route;
use Omnify\Core\Http\Controllers\Console\LogoutController;
use Omnify\Core\Http\Controllers\Console\SsoPageController;

/*
|--------------------------------------------------------------------------
| SSO Authentication Page Routes
|--------------------------------------------------------------------------
|
| Routes for SSO login and callback pages (Inertia).
| These routes are loaded by the CoreServiceProvider.
|
*/

$authPrefix = config('omnify-auth.routes.auth_prefix', 'sso');
$guestMiddleware = config('omnify-auth.routes.auth_middleware', ['web', 'guest']);
$authMiddleware = config('omnify-auth.auth_middleware', ['web', 'auth']);

Route::prefix($authPrefix)
    ->name('core.')
    ->middleware($guestMiddleware)
    ->group(function () {
        Route::get('/login', [SsoPageController::class, 'login'])->name('login');
        Route::get('/callback', [SsoPageController::class, 'callback'])->name('callback');
    });

Route::middleware($authMiddleware)
    ->group(function () {
        Route::post('/logout', LogoutController::class)->name('logout');
    });
