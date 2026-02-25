/**
 * IAM (Identity & Access Management) TypeScript types.
 */

export type ScopeType = 'global' | 'org-wide' | 'branch';

export type IamUser = {
    id: string;
    name: string;
    email: string;
    created_at: string | null;
};

export type IamRole = {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    level: number;
    is_global: boolean;
    created_at: string | null;
    permissions_count?: number;
    assignments_count?: number;
};

export type IamPermission = {
    id: string;
    name: string;
    slug: string;
    group: string | null;
};

export type IamTeam = {
    id: string;
    name: string;
    console_team_id: string;
    created_at: string | null;
};

export type IamRoleAssignment = {
    role: {
        id: string;
        name: string;
        slug: string;
        level: number;
    };
    scope_type: ScopeType;
    organization_id: string | null;
    branch_id: string | null;
    organization_name?: string | null;
    branch_name?: string | null;
    created_at: string | null;
};

export type IamUserAssignment = {
    user: {
        id: string;
        name: string;
        email: string;
    };
    scope_type: ScopeType;
    organization_id: string | null;
    branch_id: string | null;
    organization_name?: string | null;
    branch_name?: string | null;
};

export type IamOrganization = {
    id: string;
    console_organization_id: string;
    name: string;
    slug: string;
    is_active: boolean;
};

export type IamBranch = {
    id: string;
    console_branch_id: string;
    console_organization_id: string;
    name: string;
    is_headquarters: boolean;
    is_active: boolean;
};

/** Node in the scope tree, compatible with @omnifyjp/ui ScopeTreeNode */
export type IamScopeNode = {
    id: string | null;
    name: string;
    type: string;
    children: IamScopeNode[];
    badges?: { label: string; className?: string }[];
};

/** Unified assignment with user + role data embedded */
export type IamAssignment = {
    id: string;
    user: { id: string; name: string; email: string };
    role: { id: string; name: string; slug: string; level: number };
    scope_type: ScopeType;
    organization_id: string | null;
    branch_id: string | null;
    organization_name: string | null;
    branch_name: string | null;
    created_at: string | null;
};

export type PaginatedData<T> = {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
};
