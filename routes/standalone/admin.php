<?php

use Illuminate\Support\Facades\Route;
use Omnify\Core\Http\Controllers\Standalone\Admin\BranchAdminController;
use Omnify\Core\Http\Controllers\Standalone\Admin\BrandAdminController;
use Omnify\Core\Http\Controllers\Standalone\Admin\LocationAdminController;
use Omnify\Core\Http\Controllers\Standalone\Admin\OrganizationAdminController;
use Omnify\Core\Http\Controllers\Standalone\Admin\UserAdminController;

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
        Route::get('/users/create', [UserAdminController::class, 'create'])->name('users.create');
        Route::post('/users', [UserAdminController::class, 'store'])->name('users.store');

        // Organization management
        Route::resource('organizations', OrganizationAdminController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        // Branch management
        Route::resource('branches', BranchAdminController::class)
            ->except(['show']);

        // Brand management
        Route::resource('brands', BrandAdminController::class)
            ->except(['show']);

        // Location management
        Route::resource('locations', LocationAdminController::class)
            ->except(['show']);
    });
