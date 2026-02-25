<?php

use Illuminate\Support\Facades\Route;
use Omnify\SsoClient\Http\Controllers\InvitePageController;

/*
|--------------------------------------------------------------------------
| Invite Routes — Console Mode Only
|--------------------------------------------------------------------------
|
| These routes are only loaded when mode = 'console'.
| In standalone mode, invitations are managed locally (no Console API needed).
|
| Always uses 'sso.auth' middleware because invitations require a valid
| Console access token to call the Console invite API.
|
*/

$accessPrefix = config('omnify-auth.routes.access_prefix', 'admin/iam');

Route::prefix($accessPrefix)
    ->name('access.')
    ->middleware(['web', 'sso.auth'])
    ->group(function () {
        Route::get('/invite/create', [InvitePageController::class, 'inviteCreate'])->name('invite.create');
        Route::post('/invite', [InvitePageController::class, 'inviteStore'])->name('invite.store');
    });
