<?php

use Illuminate\Support\Facades\Route;
use Omnify\Core\Http\Controllers\Standalone\Admin\Auth\AdminLoginController;

/*
|--------------------------------------------------------------------------
| Admin Authentication Routes
|--------------------------------------------------------------------------
|
| Login/logout routes for the 'admin' guard (Admin model).
| Only loaded when mode = 'standalone' and admin_enabled = true.
|
*/

$prefix = config('omnify-auth.routes.standalone_admin_prefix', 'admin');

// Guest routes (admin login page)
Route::prefix($prefix)
    ->middleware(['web', 'core.admin.guest'])
    ->group(function () {
        Route::get('/login', [AdminLoginController::class, 'showLogin'])->name('admin.login');
        Route::post('/login', [AdminLoginController::class, 'login']);
    });

// Authenticated routes (admin logout)
Route::prefix($prefix)
    ->middleware(['web', 'core.admin'])
    ->group(function () {
        Route::post('/logout', [AdminLoginController::class, 'logout'])->name('admin.logout');
    });
