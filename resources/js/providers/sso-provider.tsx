import { AuthProvider } from '@omnify-core/contexts/auth-context';
import { LanguageProvider } from '@omnify-core/contexts/language-context';
import { OrganizationProvider } from '@omnify-core/contexts/organization-context';
import type { SsoProviderProps } from '@omnify-core/types/sso';

import type { SsoOrganizationData } from '@omnify-core/types/sso';

const defaultOrganizationData: SsoOrganizationData = {
    current: null,
    branch: null,
    organizations: [],
};

export function SsoProvider({ auth, organization, language, onOrganizationChange, onBranchChange, onLocaleChange, children }: SsoProviderProps) {
    return (
        <AuthProvider data={auth}>
            <OrganizationProvider data={organization ?? defaultOrganizationData} onOrganizationChange={onOrganizationChange} onBranchChange={onBranchChange}>
                <LanguageProvider data={language} onLocaleChange={onLocaleChange}>
                    {children}
                </LanguageProvider>
            </OrganizationProvider>
        </AuthProvider>
    );
}
