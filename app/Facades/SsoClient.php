<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Facades;

use Illuminate\Support\Facades\Facade;
use Omnify\SsoClient\Services\ConsoleApiService;

/**
 * @method static array|null exchangeCode(string $code)
 * @method static array|null refreshToken(string $refreshToken)
 * @method static bool revokeToken(string $refreshToken)
 * @method static array|null getAccess(string $accessToken, string $organizationId)
 * @method static array getOrganizations(string $accessToken)
 * @method static array getUserTeams(string $accessToken, string $organizationId)
 * @method static array getJwks()
 * @method static string getConsoleUrl()
 * @method static string getServiceSlug()
 *
 * @see \Omnify\SsoClient\Services\ConsoleApiService
 */
class SsoClient extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ConsoleApiService::class;
    }
}
