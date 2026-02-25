<?php

declare(strict_types=1);

namespace Omnify\SsoClient;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Omnify\SsoClient\Http\Middleware\SetBranchFromHeader;
use Omnify\SsoClient\Http\Middleware\ShareSsoData;
use Omnify\SsoClient\Http\Middleware\SsoAuthenticate;
use Omnify\SsoClient\Http\Middleware\SsoOrganizationAccess;
use Omnify\SsoClient\Http\Middleware\SsoPermissionCheck;
use Omnify\SsoClient\Http\Middleware\SsoRoleCheck;
use Omnify\SsoClient\Services\ConsoleApiService;
use Omnify\SsoClient\Services\ConsoleTokenService;
use Omnify\SsoClient\Services\JwksService;
use Omnify\SsoClient\Services\JwtVerifier;
use Omnify\SsoClient\Services\OrgAccessService;
use Omnify\SsoClient\Services\PermissionService;
use Omnify\SsoClient\Services\RoleService;
use Omnify\SsoClient\Services\UserRoleService;
use Omnify\SsoClient\Services\UserService;
use Omnify\SsoClient\Support\SsoLogger;

class SsoClientServiceProvider extends ServiceProvider
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
        $this->app->singleton('sso.logger', function ($app) {
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
            'User' => \Omnify\SsoClient\Models\User::class,
            'Permission' => \Omnify\SsoClient\Models\Permission::class,
            'Role' => \Omnify\SsoClient\Models\Role::class,
            'RolePermission' => \Omnify\SsoClient\Models\RolePermission::class,
            'Branch' => \Omnify\SsoClient\Models\Branch::class,
            'Organization' => \Omnify\SsoClient\Models\Organization::class,
            'Location' => \Omnify\SsoClient\Models\Location::class,
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

            // Publish all
            $this->publishes([
                __DIR__.'/../config/omnify-auth.php' => config_path('omnify-auth.php'),
                __DIR__.'/../database/migrations/omnify' => database_path('migrations/sso'),
                __DIR__.'/../resources/js/pages/sso' => resource_path('js/pages/sso'),
            ], 'sso-client');

            // Register Artisan commands
            $this->commands([
                \Omnify\SsoClient\Console\Commands\SyncFromConsoleCommand::class,
            ]);
        }
    }

    /**
     * Register the package routes.
     */
    protected function registerRoutes(): void
    {
        // API routes (sso auth, admin)
        $this->loadRoutesFrom(__DIR__.'/../routes/sso.php');

        // Auth pages — mode-dependent
        if (config('omnify-auth.routes.auth_enabled', true)) {
            $mode = config('omnify-auth.mode', 'standalone');

            if ($mode === 'console') {
                // Console SSO: redirect-based login + OAuth callback
                $this->loadRoutesFrom(__DIR__.'/../routes/auth.php');
            } else {
                // Standalone: email/password login
                $this->loadRoutesFrom(__DIR__.'/../routes/auth-standalone.php');
            }
        }

        // Access management pages (Inertia)
        if (config('omnify-auth.routes.access_enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/access.php');

            // Invite routes — console mode only (requires Console API + sso.auth)
            if (config('omnify-auth.mode', 'standalone') === 'console') {
                $this->loadRoutesFrom(__DIR__.'/../routes/access-invite.php');
            }
        }
    }

    /**
     * Register the package middleware.
     */
    protected function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('sso.auth', SsoAuthenticate::class);
        $router->aliasMiddleware('sso.organization', SsoOrganizationAccess::class);
        $router->aliasMiddleware('sso.role', SsoRoleCheck::class);
        $router->aliasMiddleware('sso.permission', SsoPermissionCheck::class);
        $router->aliasMiddleware('sso.branch', SetBranchFromHeader::class);
        $router->aliasMiddleware('sso.share', ShareSsoData::class);
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
                $permissions = \Omnify\SsoClient\Models\Permission::all();
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
