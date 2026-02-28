<?php

declare(strict_types=1);

namespace Omnify\Core\Services;

use Illuminate\Http\Request;

/**
 * Service for managing organization, branch, and team context.
 *
 * Provides a unified API for accessing current context from:
 * - Request attributes (set by middleware)
 * - Session (fallback)
 *
 * @see \Omnify\Core\Http\Middleware\SsoOrganizationAccess
 */
class ContextService
{
    private ?Request $request = null;

    /**
     * Set the current request instance.
     */
    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    /**
     * Get the current request instance.
     */
    protected function getRequest(): ?Request
    {
        if ($this->request) {
            return $this->request;
        }

        // Fallback to request helper
        return request();
    }

    // =========================================================================
    // ORGANIZATION CONTEXT
    // =========================================================================

    /**
     * Get current organization ID.
     */
    public function organizationId(): ?string
    {
        $request = $this->getRequest();

        return $request?->attributes->get('organizationId')
            ?? session('current_organization_id');
    }

    /**
     * Check if organization context is set.
     */
    public function hasOrganization(): bool
    {
        return $this->organizationId() !== null;
    }

    // =========================================================================
    // BRANCH CONTEXT
    // =========================================================================

    /**
     * Get current branch ID.
     */
    public function branchId(): ?string
    {
        $request = $this->getRequest();

        return $request?->attributes->get('branchId')
            ?? session('current_branch_id');
    }

    /**
     * Get current branch model.
     */
    public function branch(): mixed
    {
        return $this->getRequest()?->attributes->get('branch');
    }

    /**
     * Check if branch context is set.
     */
    public function hasBranch(): bool
    {
        return $this->branchId() !== null;
    }

    // =========================================================================
    // TEAM CONTEXT
    // =========================================================================

    /**
     * Get current team ID.
     */
    public function teamId(): ?string
    {
        $request = $this->getRequest();

        return $request?->attributes->get('teamId')
            ?? session('current_team_id');
    }

    /**
     * Check if team context is set.
     */
    public function hasTeam(): bool
    {
        return $this->teamId() !== null;
    }

    // =========================================================================
    // ROLE & PERMISSION CONTEXT
    // =========================================================================

    /**
     * Get current user's role in organization.
     */
    public function organizationRole(): ?string
    {
        $request = $this->getRequest();

        return $request?->attributes->get('organizationRole')
            ?? session('organization_role');
    }

    /**
     * Get current user's service role.
     */
    public function serviceRole(): ?string
    {
        $request = $this->getRequest();

        return $request?->attributes->get('serviceRole')
            ?? session('service_role');
    }

    /**
     * Get current user's service role level (higher = more access).
     */
    public function serviceRoleLevel(): int
    {
        $request = $this->getRequest();

        return (int) ($request?->attributes->get('serviceRoleLevel')
            ?? session('service_role_level', 0));
    }

    // =========================================================================
    // ACCESS CHECKS
    // =========================================================================

    /**
     * Check if user has organization-wide access (no branch restriction).
     */
    public function hasOrganizationWideAccess(): bool
    {
        // User has organization-wide access if:
        // 1. Their role has no branch_id restriction
        // 2. Or they have a high enough service role level
        return $this->serviceRoleLevel() >= 80;
    }

    /**
     * Check if user can access a specific branch.
     */
    public function canAccessBranch(string $branchId): bool
    {
        // Organization-wide access = can access any branch
        if ($this->hasOrganizationWideAccess()) {
            return true;
        }

        // Otherwise, can only access current branch
        return $this->branchId() === $branchId;
    }

    /**
     * Check if user can access a specific team.
     */
    public function canAccessTeam(string $teamId): bool
    {
        // Organization-wide access = can access any team
        if ($this->hasOrganizationWideAccess()) {
            return true;
        }

        // Otherwise, can only access current team
        return $this->teamId() === $teamId;
    }

    // =========================================================================
    // CONTEXT ARRAY
    // =========================================================================

    /**
     * Get all context as array.
     *
     * @return array{
     *     organization_id: string|null,
     *     branch_id: string|null,
     *     team_id: string|null,
     *     organization_role: string|null,
     *     service_role: string|null,
     *     service_role_level: int
     * }
     */
    public function toArray(): array
    {
        return [
            'organization_id' => $this->organizationId(),
            'branch_id' => $this->branchId(),
            'team_id' => $this->teamId(),
            'organization_role' => $this->organizationRole(),
            'service_role' => $this->serviceRole(),
            'service_role_level' => $this->serviceRoleLevel(),
        ];
    }
}
