<?php

declare(strict_types=1);

use Omnify\SsoClient\Support\SsoLogger;

if (! function_exists('sso_log')) {
    /**
     * Get the SSO logger instance.
     */
    function sso_log(): SsoLogger
    {
        return app(SsoLogger::class);
    }
}
