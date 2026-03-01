import { createContext, useCallback, useMemo, useState } from 'react';

import { OrganizationSelectionModal } from '@omnify-core/components/organization-selection-modal';
import type { SsoBranch, SsoOrganization, SsoOrganizationData } from '@omnify-core/types/sso';

export type OrganizationContextValue = {
    organization: SsoOrganization | null;
    branch: SsoBranch | null;
    organizations: SsoOrganization[];
    branches: SsoBranch[];
    switchOrganization: (organization: SsoOrganization) => void;
    switchBranch: (branch: SsoBranch | null) => void;
    selectOrganization: () => void;
};

export const OrganizationContext = createContext<OrganizationContextValue | null>(null);

type OrganizationProviderProps = {
    data: SsoOrganizationData;
    requireBranch?: boolean;
    onOrganizationChange?: (organization: SsoOrganization) => void;
    onBranchChange?: (branch: SsoBranch | null) => void;
    children: React.ReactNode;
};

export function OrganizationProvider({ data, requireBranch = false, onOrganizationChange, onBranchChange, children }: OrganizationProviderProps) {
    const needsSelection = !data.current || (requireBranch && !data.branch);
    const [modalOpen, setModalOpen] = useState(needsSelection);

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

    const selectOrganization = useCallback(() => {
        setModalOpen(true);
    }, []);

    const value = useMemo<OrganizationContextValue>(
        () => ({
            organization: data.current,
            branch: data.branch,
            organizations: data.organizations ?? [],
            branches: data.branches ?? [],
            switchOrganization,
            switchBranch,
            selectOrganization,
        }),
        [data, switchOrganization, switchBranch, selectOrganization],
    );

    return (
        <OrganizationContext value={value}>
            {children}
            <OrganizationSelectionModal
                open={modalOpen}
                onClose={() => setModalOpen(false)}
                current={data.current}
                organizations={data.organizations ?? []}
                branches={data.branches ?? []}
                requireBranch={requireBranch}
                closable={!needsSelection}
                onOrganizationChange={onOrganizationChange}
                onBranchChange={(branch) => onBranchChange?.(branch)}
            />
        </OrganizationContext>
    );
}
