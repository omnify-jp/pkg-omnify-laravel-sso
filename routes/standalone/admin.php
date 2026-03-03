<?php

use Illuminate\Support\Facades\Route;
use Omnify\Core\Http\Controllers\Standalone\Admin\AdminAdminController;
use Omnify\Core\Http\Controllers\Standalone\Admin\BranchAdminController;
use Omnify\Core\Http\Controllers\Standalone\Admin\BrandAdminController;
use Omnify\Core\Http\Controllers\Standalone\Admin\LocationAdminController;
use Omnify\Core\Http\Controllers\Standalone\Admin\OrganizationAdminController;
use Omnify\Core\Http\Controllers\Standalone\Admin\OrganizationUserAdminController;
use Omnify\Core\Http\Controllers\Standalone\Admin\UserAdminController;

/*
|--------------------------------------------------------------------------
| Standalone Admin Routes
|--------------------------------------------------------------------------
|
| CRUD routes for managing organizations, branches, and users.
| Only loaded when mode = 'standalone' and admin_enabled = true.
|
| Middleware is resolved based on config (default: ['web', 'core.admin']).
| Prefix: /admin (configurable via standalone_admin_prefix)
| Name prefix: admin.
|
*/

$prefix = config('omnify-auth.routes.standalone_admin_prefix', 'admin');
$middleware = config('omnify-auth.routes.standalone_admin_middleware')
    ?? ['web', 'core.admin'];

Route::prefix($prefix)
    ->name('admin.')
    ->middleware($middleware)
    ->group(function () {
        // Admin (super-admin) management
        Route::resource('admins', AdminAdminController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        // User management (standalone)
        Route::get('/users/create', [UserAdminController::class, 'create'])->name('users.create');
        Route::post('/users', [UserAdminController::class, 'store'])->name('users.store');

        // Organization management
        Route::resource('organizations', OrganizationAdminController::class)
            ->only(['index', 'show', 'store', 'update', 'destroy']);

        // Organization user management (nested)
        Route::post('/organizations/{organization}/users/search', [OrganizationUserAdminController::class, 'search'])
            ->name('organizations.users.search');
        Route::post('/organizations/{organization}/users', [OrganizationUserAdminController::class, 'store'])
            ->name('organizations.users.store');
        Route::put('/organizations/{organization}/users/{user}', [OrganizationUserAdminController::class, 'update'])
            ->name('organizations.users.update');
        Route::delete('/organizations/{organization}/users/{user}', [OrganizationUserAdminController::class, 'destroy'])
            ->name('organizations.users.destroy');

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
