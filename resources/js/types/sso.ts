/**
 * Shared SSO types for React contexts.
 *
 * These types are independent of Inertia and can be used
 * by any React app consuming the SSO package.
 */

// ─── Auth ────────────────────────────────────────────────────────────────────

export type SsoUser = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type SsoRole = {
    id: number;
    name: string;
    slug: string;
    scope_type: 'global' | 'org-wide' | 'branch';
    organization_id?: string | null;
    branch_id?: string | null;
};

export type SsoPermission = {
    id: number;
    name: string;
    slug: string;
    module?: string | null;
};

export type SsoAuthData = {
    user: SsoUser | null;
    permissions?: SsoPermission[];
    roles?: SsoRole[];
};

// ─── Organization ────────────────────────────────────────────────────────────

export type SsoOrganization = {
    id: string;
    name: string;
    slug: string;
    is_active: boolean;
    [key: string]: unknown;
};

export type SsoBranch = {
    id: string;
    name: string;
    slug: string;
    is_headquarters: boolean;
    is_active: boolean;
    organization_id: string;
    [key: string]: unknown;
};

export type SsoOrganizationData = {
    current: SsoOrganization | null;
    branch: SsoBranch | null;
    organizations?: SsoOrganization[];
    branches?: SsoBranch[];
};

// ─── Language ────────────────────────────────────────────────────────────────

export type SsoLocale = string;

export type SsoLanguageData = {
    locale: SsoLocale;
    locales: readonly SsoLocale[];
    localeNames: Record<SsoLocale, string>;
};

// ─── Provider (combined) ─────────────────────────────────────────────────────

export type SsoProviderProps = {
    auth: SsoAuthData;
    organization?: SsoOrganizationData;
    language: SsoLanguageData;
    onOrganizationChange?: (organization: SsoOrganization) => void;
    onBranchChange?: (branch: SsoBranch | null) => void;
    onLocaleChange?: (locale: SsoLocale) => void;
    children: React.ReactNode;
};
