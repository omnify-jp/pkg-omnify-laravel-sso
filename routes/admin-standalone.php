<?php

use Illuminate\Support\Facades\Route;
use Omnify\SsoClient\Http\Controllers\Admin\BranchAdminController;
use Omnify\SsoClient\Http\Controllers\Admin\OrganizationAdminController;
use Omnify\SsoClient\Http\Controllers\Admin\UserStandaloneAdminController;

/*
|--------------------------------------------------------------------------
| Standalone Admin Routes
|--------------------------------------------------------------------------
|
| CRUD routes for managing organizations, branches, and users.
| Only loaded when mode = 'standalone' and admin_enabled = true.
|
| Middleware is resolved based on config (default: ['web', 'auth']).
| Prefix: /admin (configurable via standalone_admin_prefix)
| Name prefix: admin.
|
*/

$prefix = config('omnify-auth.routes.standalone_admin_prefix', 'admin');
$middleware = config('omnify-auth.routes.standalone_admin_middleware')
    ?? ['web', 'auth'];

Route::prefix($prefix)
    ->name('admin.')
    ->middleware($middleware)
    ->group(function () {
        // User management (standalone)
        Route::get('/users/create', [UserStandaloneAdminController::class, 'create'])->name('users.create');
        Route::post('/users', [UserStandaloneAdminController::class, 'store'])->name('users.store');

        // Organization management
        Route::resource('organizations', OrganizationAdminController::class)
            ->except(['show']);

        // Branch management
        Route::resource('branches', BranchAdminController::class)
            ->except(['show']);
    });
