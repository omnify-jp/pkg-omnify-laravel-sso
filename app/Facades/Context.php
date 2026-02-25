<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Facades;

use Illuminate\Support\Facades\Facade;
use Omnify\SsoClient\Services\ContextService;

/**
 * Context Facade for accessing current organization, branch, and team context.
 *
 * @method static string|null organizationId() Get current organization ID
 * @method static bool hasOrganization() Check if organization context is set
 * @method static string|null branchId() Get current branch ID
 * @method static mixed branch() Get current branch model
 * @method static bool hasBranch() Check if branch context is set
 * @method static string|null teamId() Get current team ID
 * @method static bool hasTeam() Check if team context is set
 * @method static string|null organizationRole() Get current user's role in organization
 * @method static string|null serviceRole() Get current user's service role
 * @method static int serviceRoleLevel() Get current user's service role level
 * @method static bool hasOrganizationWideAccess() Check if user has organization-wide access
 * @method static bool canAccessBranch(string $branchId) Check if user can access a branch
 * @method static bool canAccessTeam(string $teamId) Check if user can access a team
 * @method static array toArray() Get all context as array
 *
 * @see \Omnify\SsoClient\Services\ContextService
 */
class Context extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return ContextService::class;
    }
}
