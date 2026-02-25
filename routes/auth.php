<?php

use Illuminate\Support\Facades\Route;
use Omnify\SsoClient\Http\Controllers\SsoPageController;

/*
|--------------------------------------------------------------------------
| SSO Authentication Page Routes
|--------------------------------------------------------------------------
|
| Routes for SSO login and callback pages (Inertia).
| These routes are loaded by the SsoClientServiceProvider.
|
*/

$authPrefix = config('omnify-auth.routes.auth_prefix', 'sso');
$authMiddleware = config('omnify-auth.routes.auth_middleware', ['web', 'guest']);

Route::prefix($authPrefix)
    ->name('sso.')
    ->middleware($authMiddleware)
    ->group(function () {
        Route::get('/login', [SsoPageController::class, 'login'])->name('login');
        Route::get('/callback', [SsoPageController::class, 'callback'])->name('callback');
    });
