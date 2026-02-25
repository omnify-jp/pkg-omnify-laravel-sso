import { useContext } from 'react';

import { OrganizationContext, type OrganizationContextValue } from '../contexts/organization-context';

export function useOrganization(): OrganizationContextValue {
    const context = useContext(OrganizationContext);

    if (!context) {
        throw new Error('useOrganization must be used within an <SsoProvider> or <OrganizationProvider>');
    }

    return context;
}
