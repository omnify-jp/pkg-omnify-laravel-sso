<?php

use Illuminate\Support\Facades\Route;
use Omnify\Core\Http\Controllers\SelectOrganizationController;

/*
|--------------------------------------------------------------------------
| Organization Selection Route
|--------------------------------------------------------------------------
|
| Shown when the authenticated user has no organization context.
| No org prefix — this is the page they land on BEFORE selecting an org.
|
*/

$authMiddleware = config('omnify-auth.auth_middleware', ['web', 'auth']);

Route::middleware($authMiddleware)
    ->get('/select-organization', SelectOrganizationController::class)
    ->name('core.select-organization');
