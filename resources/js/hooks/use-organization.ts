import { useContext } from 'react';

import { OrganizationContext, type OrganizationContextValue } from '@omnify-core/contexts/organization-context';

export function useOrganization(): OrganizationContextValue {
    const context = useContext(OrganizationContext);

    if (!context) {
        throw new Error('useOrganization must be used within an <SsoProvider> or <OrganizationProvider>');
    }

    return context;
}
