<?php

use Illuminate\Support\Facades\Route;
use Omnify\SsoClient\Http\Controllers\Admin\PermissionAdminController;
use Omnify\SsoClient\Http\Controllers\Admin\RoleAdminController;
use Omnify\SsoClient\Http\Controllers\Admin\UserAdminController;
use Omnify\SsoClient\Http\Controllers\Admin\UserRoleAdminController;
use Omnify\SsoClient\Http\Controllers\SsoBranchController;
use Omnify\SsoClient\Http\Controllers\SsoCallbackController;
use Omnify\SsoClient\Http\Controllers\SsoLocationController;
use Omnify\SsoClient\Http\Controllers\SsoReadOnlyController;
use Omnify\SsoClient\Http\Controllers\SsoTokenController;

/*
|--------------------------------------------------------------------------
| SSO Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the SsoClientServiceProvider.
|
*/

$prefix = config('omnify-auth.routes.prefix', 'api/sso');
$middleware = config('omnify-auth.routes.middleware', ['api']);
$adminPrefix = config('omnify-auth.routes.admin_prefix', 'api/admin/sso');
$adminMiddleware = config('omnify-auth.routes.admin_middleware', ['api', 'sso.auth', 'sso.organization', 'sso.role:admin']);

// SSO Callback Route (with Sanctum stateful for session cookie)
// コールバックでセッションCookieを設定するため、Sanctum statefulが必要
Route::prefix($prefix)
    ->middleware($middleware)
    ->group(function () {
        Route::post('/callback', [SsoCallbackController::class, 'callback']);
    });

// SSO Auth Routes (with Sanctum stateful for SPA)
Route::prefix($prefix)
    ->middleware($middleware)
    ->group(function () {
        // Authenticated routes
        Route::middleware('sso.auth')->group(function () {
            Route::post('/logout', [SsoCallbackController::class, 'logout']);
            Route::get('/user', [SsoCallbackController::class, 'user']);
            Route::get('/global-logout-url', [SsoCallbackController::class, 'globalLogoutUrl']);

            // Token management (for mobile apps)
            Route::get('/tokens', [SsoTokenController::class, 'index']);
            Route::delete('/tokens/{tokenId}', [SsoTokenController::class, 'destroy']);
            Route::post('/tokens/revoke-others', [SsoTokenController::class, 'revokeOthers']);

            // Read-only access to roles and permissions (for dashboard display)
            // No org/admin requirements - just authenticated users
            Route::get('/roles', [SsoReadOnlyController::class, 'roles']);
            Route::get('/roles/{id}', [SsoReadOnlyController::class, 'role']);
            Route::get('/permissions', [SsoReadOnlyController::class, 'permissions']);
            Route::get('/permission-matrix', [SsoReadOnlyController::class, 'permissionMatrix']);

            // Branches - proxy from console
            Route::get('/branches', [SsoBranchController::class, 'index']);

            // Locations - proxy from console
            Route::get('/locations', [SsoLocationController::class, 'index']);
        });
    });

// Admin Routes
Route::prefix($adminPrefix)
    ->middleware($adminMiddleware)
    ->group(function () {
        // Users
        Route::get('users/search', [UserAdminController::class, 'search']);
        Route::get('users/{user}/permissions', [UserAdminController::class, 'permissions']);
        Route::apiResource('users', UserAdminController::class)->except(['store']);

        // Roles
        Route::apiResource('roles', RoleAdminController::class);
        Route::get('roles/{role}/permissions', [RoleAdminController::class, 'permissions']);
        Route::put('roles/{role}/permissions', [RoleAdminController::class, 'syncPermissions']);

        // Permissions
        Route::apiResource('permissions', PermissionAdminController::class);
        Route::get('permission-matrix', [PermissionAdminController::class, 'matrix']);

        // User Role Assignments (Branch-Level Permissions - Option B)
        // Supports scoped role assignments: global, org-wide, branch-specific
        Route::get('users/{userId}/roles', [UserRoleAdminController::class, 'index']);
        Route::post('users/{userId}/roles', [UserRoleAdminController::class, 'store']);
        Route::put('users/{userId}/roles/sync', [UserRoleAdminController::class, 'sync']);
        Route::delete('users/{userId}/roles/{roleId}', [UserRoleAdminController::class, 'destroy']);
    });
