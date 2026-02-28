<?php

namespace Omnify\Core\Tests;

/**
 * Console Mode TestCase
 *
 * Base class for tests that require console mode (omnify-auth.mode = 'console').
 * Sets mode BEFORE service providers boot so console-mode routes are registered.
 */
abstract class ConsoleTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Must be set before boot — ServiceProvider reads this to register routes
        $app['config']->set('omnify-auth.mode', 'console');
    }
}
