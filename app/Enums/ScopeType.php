<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Enums;

/**
 * Scope type for role assignments and permissions.
 *
 * Determines the context level at which a role is assigned:
 * - Global: applies across all organizations
 * - OrgWide: applies to a specific organization
 * - Branch: applies to a specific branch within an organization
 */
enum ScopeType: string
{
    case Global = 'global';
    case OrgWide = 'org-wide';
    case Branch = 'branch';

    /**
     * Determine scope type from organization and branch IDs.
     */
    public static function fromContext(?string $organizationId, ?string $branchId): self
    {
        if ($organizationId === null) {
            return self::Global;
        }

        if ($branchId === null) {
            return self::OrgWide;
        }

        return self::Branch;
    }

    /**
     * Sort priority (lower = higher priority).
     */
    public function sortOrder(): int
    {
        return match ($this) {
            self::Global => 0,
            self::OrgWide => 1,
            self::Branch => 2,
        };
    }
}
