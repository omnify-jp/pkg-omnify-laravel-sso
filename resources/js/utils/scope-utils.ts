import { Building2, GitBranch, Globe, type LucideIcon } from 'lucide-react';

import type { IamAssignment, IamBranch, IamOrganization, IamPermission, IamScopeNode, ScopeType } from '../types/iam';

// ─── Scope helpers ────────────────────────────────────────────────────────────

export function getScopeIcon(scope: ScopeType): LucideIcon {
    switch (scope) {
        case 'global':
            return Globe;
        case 'org-wide':
            return Building2;
        case 'branch':
            return GitBranch;
    }
}

/**
 * Map package ScopeType to ScopeTypeBadge 'type' values.
 * ScopeTypeBadge has built-in styles for 'global', 'organization', 'branch', 'location'.
 */
export function toScopeBadgeType(scope: ScopeType): string {
    switch (scope) {
        case 'global':
            return 'global';
        case 'org-wide':
            return 'organization';
        case 'branch':
            return 'branch';
    }
}

export function getScopeLabel(scope: ScopeType): string {
    switch (scope) {
        case 'global':
            return 'Global';
        case 'org-wide':
            return 'Organization';
        case 'branch':
            return 'Branch';
    }
}

export function formatScopeLocation(assignment: {
    scope_type: ScopeType;
    organization_name?: string | null;
    branch_name?: string | null;
}): string {
    switch (assignment.scope_type) {
        case 'global':
            return 'Global';
        case 'org-wide':
            return assignment.organization_name ?? 'Organization';
        case 'branch':
            return assignment.branch_name ?? 'Branch';
    }
}

// ─── Scope tree helpers ───────────────────────────────────────────────────────

/**
 * Build a scope tree from flat org/branch arrays.
 * Returns an IamScopeNode root (compatible with @omnifyjp/ui ScopeTreeNode).
 */
export function buildScopeTree(orgs: IamOrganization[], branches: IamBranch[]): IamScopeNode {
    const root: IamScopeNode = {
        type: 'global',
        id: null,
        name: 'Global',
        children: [],
    };

    for (const org of orgs) {
        const orgNode: IamScopeNode = {
            type: 'org-wide',
            id: org.id,
            name: org.name,
            children: [],
        };

        const orgBranches = branches.filter(
            (b) => b.console_organization_id === org.console_organization_id,
        );

        for (const branch of orgBranches) {
            const branchBadges: { label: string; className?: string }[] = [];
            if (branch.is_headquarters) {
                branchBadges.push({
                    label: 'HQ',
                    className:
                        'bg-amber-50 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-200 dark:border-amber-500/30',
                });
            }
            orgNode.children.push({
                type: 'branch',
                id: branch.id,
                name: branch.name,
                children: [],
                badges: branchBadges.length > 0 ? branchBadges : undefined,
            });
        }

        root.children.push(orgNode);
    }

    return root;
}

/**
 * Get assignments directly assigned to a scope (not inherited).
 */
export function getDirectAssignments(
    scopeType: ScopeType,
    scopeId: string | null,
    assignments: IamAssignment[],
): IamAssignment[] {
    return assignments.filter((a) => {
        if (scopeType === 'global') {
            return a.scope_type === 'global';
        }
        if (scopeType === 'org-wide') {
            return a.scope_type === 'org-wide' && a.organization_id === scopeId;
        }
        if (scopeType === 'branch') {
            return a.scope_type === 'branch' && a.branch_id === scopeId;
        }
        return false;
    });
}

/**
 * Get assignments inherited by a scope from its ancestor scopes.
 * Branch inherits from global + its org-wide.
 * Org-wide inherits from global.
 * Global inherits nothing.
 */
export function getInheritedAssignments(
    scopeType: ScopeType,
    scopeId: string | null,
    assignments: IamAssignment[],
    orgs: IamOrganization[],
    branches: IamBranch[],
): { assignment: IamAssignment; fromName: string }[] {
    const inherited: { assignment: IamAssignment; fromName: string }[] = [];

    if (scopeType === 'global') {
        return inherited;
    }

    // All non-global scopes inherit from global
    for (const a of assignments.filter((a) => a.scope_type === 'global')) {
        inherited.push({ assignment: a, fromName: 'Global' });
    }

    if (scopeType === 'org-wide') {
        return inherited;
    }

    // Branch also inherits from its org-wide
    if (scopeType === 'branch' && scopeId) {
        const branch = branches.find((b) => b.id === scopeId);
        if (branch) {
            const org = orgs.find(
                (o) => o.console_organization_id === branch.console_organization_id,
            );
            if (org) {
                for (const a of assignments.filter(
                    (a) => a.scope_type === 'org-wide' && a.organization_id === org.id,
                )) {
                    inherited.push({ assignment: a, fromName: org.name });
                }
            }
        }
    }

    return inherited;
}

/**
 * Get the display name for a scope node by its type and ID.
 */
export function getScopeNodeName(
    scopeType: ScopeType,
    scopeId: string | null,
    orgs: IamOrganization[],
    branches: IamBranch[],
): string {
    if (scopeType === 'global') {
        return 'Global';
    }
    if (scopeType === 'org-wide' && scopeId) {
        return orgs.find((o) => o.id === scopeId)?.name ?? 'Organization';
    }
    if (scopeType === 'branch' && scopeId) {
        return branches.find((b) => b.id === scopeId)?.name ?? 'Branch';
    }
    return 'Unknown';
}

// ─── PermissionGrid helpers ───────────────────────────────────────────────────

export type PermissionModuleForGrid = {
    key: string;
    label: string;
    permissions: { key: string; label: string }[];
};

/**
 * Build PermissionGrid modules from a flat list of permissions.
 * Assumes slug format "group.action" (e.g. "tasks.view", "users.create").
 */
export function buildPermissionModules(permissions: IamPermission[]): PermissionModuleForGrid[] {
    const moduleMap = new Map<string, Map<string, string>>();

    for (const perm of permissions) {
        const group = perm.group ?? 'general';
        const dotIndex = perm.slug.lastIndexOf('.');
        const actionKey = dotIndex >= 0 ? perm.slug.slice(dotIndex + 1) : perm.slug;
        const actionLabel = perm.name;

        if (!moduleMap.has(group)) {
            moduleMap.set(group, new Map());
        }
        moduleMap.get(group)!.set(actionKey, actionLabel);
    }

    return Array.from(moduleMap.entries()).map(([groupKey, actions]) => ({
        key: groupKey,
        label: groupKey.charAt(0).toUpperCase() + groupKey.slice(1),
        permissions: Array.from(actions.entries()).map(([actionKey, actionLabel]) => ({
            key: actionKey,
            label: actionLabel,
        })),
    }));
}

/**
 * Convert a list of DB permission IDs to PermissionGrid's "module:action" format.
 */
export function toGridIds(allPermissions: IamPermission[], selectedIds: string[]): string[] {
    const selectedSet = new Set(selectedIds);
    const gridIds: string[] = [];

    for (const perm of allPermissions) {
        if (!selectedSet.has(perm.id)) {
            continue;
        }
        const group = perm.group ?? 'general';
        const dotIndex = perm.slug.lastIndexOf('.');
        const actionKey = dotIndex >= 0 ? perm.slug.slice(dotIndex + 1) : perm.slug;
        gridIds.push(`${group}:${actionKey}`);
    }

    return gridIds;
}

/**
 * Convert PermissionGrid's "module:action" format back to DB permission IDs.
 */
export function fromGridIds(allPermissions: IamPermission[], gridIds: string[]): string[] {
    const gridSet = new Set(gridIds);
    const ids: string[] = [];

    for (const perm of allPermissions) {
        const group = perm.group ?? 'general';
        const dotIndex = perm.slug.lastIndexOf('.');
        const actionKey = dotIndex >= 0 ? perm.slug.slice(dotIndex + 1) : perm.slug;
        if (gridSet.has(`${group}:${actionKey}`)) {
            ids.push(perm.id);
        }
    }

    return ids;
}
