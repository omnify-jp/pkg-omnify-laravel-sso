<?php

use Illuminate\Support\Facades\Route;
use Omnify\Core\Http\Controllers\AccessPageController;

/*
|--------------------------------------------------------------------------
| Access Management Routes (IAM-style)
|--------------------------------------------------------------------------
|
| Routes for managing users, roles, teams, permissions, and assignments.
| These routes are loaded by the CoreServiceProvider.
|
| Middleware is resolved based on mode (unless explicitly overridden):
|   console    → ['web', 'core.auth']   (Console SSO authentication)
|   standalone → ['web', 'auth']        (standard Laravel session auth)
|
*/

$mode = config('omnify-auth.mode', 'standalone');
$accessPrefix = config('omnify-auth.routes.access_prefix', 'settings/iam');
$accessMiddleware = config('omnify-auth.routes.access_middleware')
    ?? ($mode === 'console' ? ['web', 'core.auth'] : ['web', 'auth']);

// Org Settings Index — hub page (/@{org}/settings)
Route::get('settings', [AccessPageController::class, 'orgSettingsIndex'])
    ->name('org-settings.index')
    ->middleware($accessMiddleware);

Route::prefix($accessPrefix)
    ->name('access.')
    ->middleware($accessMiddleware)
    ->group(function () {
        // Overview
        Route::get('/', [AccessPageController::class, 'overview'])->name('overview');

        // Scope Explorer
        Route::get('/scope-explorer', [AccessPageController::class, 'scopeExplorer'])->name('scope-explorer');

        // Users
        Route::get('/users', [AccessPageController::class, 'users'])->name('users');
        Route::get('/users/{userId}', [AccessPageController::class, 'userShow'])->name('users.show');

        // Roles — create/store BEFORE the show route to avoid conflict
        Route::get('/roles/create', [AccessPageController::class, 'roleCreate'])->name('roles.create');
        Route::post('/roles', [AccessPageController::class, 'roleStore'])->name('roles.store');
        Route::get('/roles', [AccessPageController::class, 'roles'])->name('roles');
        Route::get('/roles/{roleId}', [AccessPageController::class, 'roleShow'])->name('roles.show');
        Route::get('/roles/{roleId}/edit', [AccessPageController::class, 'roleEdit'])->name('roles.edit');
        Route::put('/roles/{roleId}', [AccessPageController::class, 'roleUpdate'])->name('roles.update');

        // Assignments — create/store BEFORE the list route
        Route::get('/assignments/create', [AccessPageController::class, 'assignmentCreate'])->name('assignments.create');
        Route::post('/assignments', [AccessPageController::class, 'assignmentStore'])->name('assignments.store');
        Route::get('/assignments', [AccessPageController::class, 'assignments'])->name('assignments');
        Route::delete('/assignments/{userId}/{roleId}', [AccessPageController::class, 'assignmentDelete'])->name('assignments.delete');

        // Permissions
        Route::get('/permissions', [AccessPageController::class, 'permissions'])->name('permissions');
    });
