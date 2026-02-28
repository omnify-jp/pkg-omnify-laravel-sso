<?php

declare(strict_types=1);

namespace Omnify\Core\Facades;

use Illuminate\Support\Facades\Facade;
use Omnify\Core\Services\ConsoleApiService;

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
 * @see \Omnify\Core\Services\ConsoleApiService
 */
class Core extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ConsoleApiService::class;
    }
}
