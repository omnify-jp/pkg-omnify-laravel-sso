<?php

use Illuminate\Support\Facades\Route;
use Omnify\Core\Http\Controllers\Standalone\Auth\SocialLoginController;

/*
|--------------------------------------------------------------------------
| Socialite Routes
|--------------------------------------------------------------------------
|
| Routes for social login (Google, GitHub, etc.).
| Only loaded when omnify-auth.socialite.enabled = true.
|
*/

Route::middleware(config('omnify-auth.guest_middleware', ['web', 'guest']))
    ->group(function () {
        Route::get('auth/{provider}/redirect', [SocialLoginController::class, 'redirect'])
            ->name('socialite.redirect');

        Route::get('auth/{provider}/callback', [SocialLoginController::class, 'callback'])
            ->name('socialite.callback');
    });
