<?php

declare(strict_types=1);

namespace Omnify\Core;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Omnify\Core\Http\Middleware\AdminAuthenticate;
use Omnify\Core\Http\Middleware\AdminRedirectIfAuthenticated;
use Omnify\Core\Http\Middleware\ResolveOrganizationFromUrl;
use Omnify\Core\Http\Middleware\SetBranchFromHeader;
use Omnify\Core\Http\Middleware\ShareSsoData;
use Omnify\Core\Http\Middleware\SsoAuthenticate;
use Omnify\Core\Http\Middleware\SsoOrganizationAccess;
use Omnify\Core\Http\Middleware\SsoPermissionCheck;
use Omnify\Core\Http\Middleware\SsoRoleCheck;
use Omnify\Core\Http\Middleware\StandaloneOrganizationContext;
use Omnify\Core\Services\ConsoleApiService;
use Omnify\Core\Services\ConsoleTokenService;
use Omnify\Core\Services\JwksService;
use Omnify\Core\Services\JwtVerifier;
use Omnify\Core\Services\OrgAccessService;
use Omnify\Core\Services\PermissionService;
use Omnify\Core\Services\RoleService;
use Omnify\Core\Services\UserRoleService;
use Omnify\Core\Services\UserService;
use Omnify\Core\Support\SsoLogger;

class CoreServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__.'/../config/omnify-auth.php',
            'omnify-auth'
        );

        // Register admin guard & provider (standalone mode only)
        $this->registerAdminGuard();

        // Register services as singletons
        $this->app->singleton(JwksService::class, function ($app) {
            return new JwksService(
                config('omnify-auth.console.url'),
                config('omnify-auth.cache.jwks_ttl')
            );
        });

        $this->app->singleton(JwtVerifier::class, function ($app) {
            return new JwtVerifier(
                $app->make(JwksService::class)
            );
        });

        $this->app->singleton(ConsoleApiService::class, function ($app) {
            return new ConsoleApiService(
                config('omnify-auth.console.url'),
                config('omnify-auth.service.slug'),
                config('omnify-auth.console.timeout'),
                config('omnify-auth.console.retry')
            );
        });

        $this->app->singleton(ConsoleTokenService::class, function ($app) {
            return new ConsoleTokenService(
                $app->make(ConsoleApiService::class)
            );
        });

        $this->app->singleton(OrgAccessService::class, function ($app) {
            return new OrgAccessService(
                $app->make(ConsoleApiService::class),
                $app->make(ConsoleTokenService::class),
                config('omnify-auth.cache.org_access_ttl')
            );
        });

        // Register SSO Logger
        $this->app->singleton(SsoLogger::class, function ($app) {
            return new SsoLogger;
        });

        // Register helper function
        $this->app->singleton('core.logger', function ($app) {
            return $app->make(SsoLogger::class);
        });

        // Register Access Management Services
        $this->app->singleton(UserService::class);
        $this->app->singleton(RoleService::class);
        $this->app->singleton(PermissionService::class);
        $this->app->singleton(UserRoleService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'omnify');
        $this->configureLogging();
        $this->registerMorphMap();
        $this->registerPublishing();
        $this->registerMigrations();
        $this->registerRoutes();
        $this->registerMiddleware();
        $this->registerGates();
    }

    /**
     * Configure SSO logging channel.
     */
    protected function configureLogging(): void
    {
        // Add 'sso' log channel if it doesn't exist
        $channel = config('omnify-auth.logging.channel', 'sso');

        if (! config("logging.channels.{$channel}")) {
            config(["logging.channels.{$channel}" => [
                'driver' => 'daily',
                'path' => storage_path("logs/{$channel}.log"),
                'level' => config('omnify-auth.logging.level', 'debug'),
                'days' => 14,
            ]]);
        }
    }

    /**
     * Register morph map for polymorphic relationships.
     * Uses morphMap (not enforceMorphMap) for flexibility in testing.
     */
    protected function registerMorphMap(): void
    {
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
            'User' => \Omnify\Core\Models\User::class,
            'Permission' => \Omnify\Core\Models\Permission::class,
            'Role' => \Omnify\Core\Models\Role::class,
            'RolePermission' => \Omnify\Core\Models\RolePermission::class,
            'Branch' => \Omnify\Core\Models\Branch::class,
            'Organization' => \Omnify\Core\Models\Organization::class,
            'Location' => \Omnify\Core\Models\Location::class,
        ]);
    }

    /**
     * Register the package migrations.
     *
     * Auto-loads migrations from package.
     * Users can also publish using: php artisan vendor:publish --tag=sso-migrations
     */
    protected function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations/omnify');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * Register the package's publishable resources.
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish config
            $this->publishes([
                __DIR__.'/../config/omnify-auth.php' => config_path('omnify-auth.php'),
            ], 'omnify-auth-config');

            // Publish migrations
            $this->publishes([
                __DIR__.'/../database/migrations/omnify' => database_path('migrations/sso'),
            ], 'sso-migrations');

            // Publish Inertia pages
            $this->publishes([
                __DIR__.'/../resources/js/pages/sso' => resource_path('js/pages/sso'),
            ], 'sso-pages');

            // Publish React contexts, hooks, and providers
            $this->publishes([
                __DIR__.'/../resources/js/contexts' => resource_path('js/sso/contexts'),
                __DIR__.'/../resources/js/hooks' => resource_path('js/sso/hooks'),
                __DIR__.'/../resources/js/providers' => resource_path('js/sso/providers'),
                __DIR__.'/../resources/js/types/sso.ts' => resource_path('js/sso/types/sso.ts'),
            ], 'sso-react');

            // Publish seeders
            $this->publishes([
                __DIR__.'/../database/seeders' => database_path('seeders/sso'),
            ], 'sso-seeders');

            // Publish standalone admin pages
            $this->publishes([
                __DIR__.'/../resources/js/pages/admin/organizations' => resource_path('js/pages/admin/organizations'),
                __DIR__.'/../resources/js/pages/admin/branches' => resource_path('js/pages/admin/branches'),
                __DIR__.'/../resources/js/pages/admin/users' => resource_path('js/pages/admin/users'),
            ], 'sso-admin-pages');

            // Publish all
            $this->publishes([
                __DIR__.'/../config/omnify-auth.php' => config_path('omnify-auth.php'),
                __DIR__.'/../database/migrations/omnify' => database_path('migrations/sso'),
                __DIR__.'/../resources/js/pages/sso' => resource_path('js/pages/sso'),
            ], 'sso-client');

            // Register Artisan commands
            $this->commands([
                \Omnify\Core\Console\Commands\SyncFromConsoleCommand::class,
                \Omnify\Core\Console\Commands\OdgSetupCommand::class,
            ]);

            // Standalone-only commands
            if (config('omnify-auth.mode') === 'standalone') {
                $this->commands([
                    \Omnify\Core\Console\Commands\AdminCreateCommand::class,
                ]);
            }
        }
    }

    /**
     * Register the package routes.
     */
    protected function registerRoutes(): void
    {
        $mode = config('omnify-auth.mode', 'standalone');

        // API routes — always loaded (both modes), never org-prefixed
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        // Access management pages — org-prefixed when configured (IAM roles/permissions)
        if (config('omnify-auth.routes.access_enabled', true)) {
            $this->withOrgPrefix(function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/access.php');
            });
        }

        // Settings page — never org-prefixed (user-level, not org-scoped)
        if (config('omnify-auth.settings.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/standalone/settings.php');
        }

        // Mode-specific routes
        if ($mode === 'console') {
            $this->registerConsoleRoutes();
        } else {
            $this->registerStandaloneRoutes();
        }
    }

    /**
     * Register console-mode routes (SSO OAuth).
     */
    protected function registerConsoleRoutes(): void
    {
        // Auth routes — never org-prefixed (SSO login/callback)
        if (config('omnify-auth.routes.auth_enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/console/auth.php');
        }

        // Invite routes — org-prefixed (part of IAM settings)
        if (config('omnify-auth.routes.access_enabled', true)) {
            $this->withOrgPrefix(function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/console/invite.php');
            });
        }
    }

    /**
     * Register standalone-mode routes (email/password auth + admin CRUD).
     */
    protected function registerStandaloneRoutes(): void
    {
        // Auth routes — never org-prefixed (login/register/2fa)
        if (config('omnify-auth.routes.auth_enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/standalone/auth.php');
        }

        if (config('omnify-auth.standalone.admin_enabled', true)) {
            // Admin routes — never org-prefixed (admin manages orgs, doesn't need org context)
            $this->loadRoutesFrom(__DIR__.'/../routes/standalone/admin-auth.php');
            $this->loadRoutesFrom(__DIR__.'/../routes/standalone/admin.php');
        }
    }

    /**
     * Wrap route registration in the org URL prefix when configured.
     *
     * When org_route_prefix is set (e.g. '@{organization}'), routes are nested
     * under that prefix with ResolveOrganizationFromUrl middleware.
     * When empty, routes are loaded without wrapping (cookie-only mode).
     */
    protected function withOrgPrefix(callable $callback): void
    {
        $orgPrefix = config('omnify-auth.routes.org_route_prefix', '');

        if ($orgPrefix !== '') {
            Route::prefix($orgPrefix)
                ->middleware(['core.org.url'])
                ->group($callback);
        } else {
            $callback();
        }
    }

    /**
     * Register the package middleware.
     */
    protected function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('core.auth', SsoAuthenticate::class);
        $router->aliasMiddleware('core.organization', SsoOrganizationAccess::class);
        $router->aliasMiddleware('core.role', SsoRoleCheck::class);
        $router->aliasMiddleware('core.permission', SsoPermissionCheck::class);
        $router->aliasMiddleware('core.branch', SetBranchFromHeader::class);
        $router->aliasMiddleware('core.share', ShareSsoData::class);
        $router->aliasMiddleware('core.standalone.org', StandaloneOrganizationContext::class);
        $router->aliasMiddleware('core.org.url', ResolveOrganizationFromUrl::class);
        $router->aliasMiddleware('core.admin', AdminAuthenticate::class);
        $router->aliasMiddleware('core.admin.guest', AdminRedirectIfAuthenticated::class);
    }

    /**
     * Register the admin auth guard and provider.
     *
     * Programmatically injects 'admin' guard + 'admins' provider into
     * Laravel's auth config. Host apps don't need to touch config/auth.php.
     */
    protected function registerAdminGuard(): void
    {
        if (config('omnify-auth.mode', 'standalone') !== 'standalone') {
            return;
        }

        $this->app['config']->set('auth.guards.admin', [
            'driver' => 'session',
            'provider' => 'admins',
        ]);

        $this->app['config']->set('auth.providers.admins', [
            'driver' => 'eloquent',
            'model' => config('omnify-auth.admin_model', \Omnify\Core\Models\Admin::class),
        ]);
    }

    /**
     * Register permission gates.
     */
    protected function registerGates(): void
    {
        // Define gates based on permissions from database
        Gate::before(function ($user, $ability) {
            // Super admin bypass (optional)
            if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                return true;
            }

            // Check role-based permissions
            if (method_exists($user, 'hasPermission')) {
                return $user->hasPermission($ability) ?: null;
            }

            return null;
        });

        // Dynamic permission gates from database
        $this->app->booted(function () {
            try {
                $permissions = \Omnify\Core\Models\Permission::all();
                foreach ($permissions as $permission) {
                    Gate::define($permission->slug, function ($user) use ($permission) {
                        return $user->hasPermission($permission->slug);
                    });
                }
            } catch (\Exception $e) {
                // Database might not be ready yet (migrations not run)
            }
        });
    }
}
