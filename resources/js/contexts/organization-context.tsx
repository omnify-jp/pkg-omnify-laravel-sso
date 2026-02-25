import { createContext, useCallback, useMemo } from 'react';

import type { SsoBranch, SsoOrganization, SsoOrganizationData } from '../types/sso';

export type OrganizationContextValue = {
    organization: SsoOrganization | null;
    branch: SsoBranch | null;
    organizations: SsoOrganization[];
    switchOrganization: (organization: SsoOrganization) => void;
    switchBranch: (branch: SsoBranch | null) => void;
};

export const OrganizationContext = createContext<OrganizationContextValue | null>(null);

type OrganizationProviderProps = {
    data: SsoOrganizationData;
    onOrganizationChange?: (organization: SsoOrganization) => void;
    onBranchChange?: (branch: SsoBranch | null) => void;
    children: React.ReactNode;
};

export function OrganizationProvider({ data, onOrganizationChange, onBranchChange, children }: OrganizationProviderProps) {
    const switchOrganization = useCallback(
        (organization: SsoOrganization) => {
            onOrganizationChange?.(organization);
        },
        [onOrganizationChange],
    );

    const switchBranch = useCallback(
        (branch: SsoBranch | null) => {
            onBranchChange?.(branch);
        },
        [onBranchChange],
    );

    const value = useMemo<OrganizationContextValue>(
        () => ({
            organization: data.current,
            branch: data.branch,
            organizations: data.organizations ?? [],
            switchOrganization,
            switchBranch,
        }),
        [data, switchOrganization, switchBranch],
    );

    return <OrganizationContext value={value}>{children}</OrganizationContext>;
}
