import { useContext } from 'react';

import { AuthContext, type AuthContextValue } from '@omnify-core/contexts/auth-context';

export function useAuth(): AuthContextValue {
    const context = useContext(AuthContext);

    if (!context) {
        throw new Error('useAuth must be used within an <SsoProvider> or <AuthProvider>');
    }

    return context;
}
