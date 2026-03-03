<?php

namespace Omnify\Core\Tests;

/**
 * Standalone Admin Pages TestCase
 *
 * Base class for tests hitting standalone admin page routes (/admin/*).
 * Sets standalone_admin_middleware to ['web'] BEFORE service providers boot
 * so the Inertia page routes are accessible without the real core.admin guard.
 *
 * Auth/guard behaviour is already covered in AdminGuardTest.php.
 */
abstract class StandaloneAdminTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Must be set before boot — the admin.php route file reads this config
        // when the ServiceProvider registers routes during application boot.
        $app['config']->set('omnify-auth.mode', 'standalone');
        $app['config']->set('omnify-auth.routes.standalone_admin_middleware', ['web']);
    }
}
