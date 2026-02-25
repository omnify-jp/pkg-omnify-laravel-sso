import { AuthProvider } from '../contexts/auth-context';
import { LanguageProvider } from '../contexts/language-context';
import { OrganizationProvider } from '../contexts/organization-context';
import type { SsoProviderProps } from '../types/sso';

import type { SsoOrganizationData } from '../types/sso';

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
