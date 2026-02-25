import { createContext, useMemo } from 'react';

import type { SsoAuthData, SsoPermission, SsoRole, SsoUser } from '../types/sso';

export type AuthContextValue = {
    user: SsoUser | null;
    isAuthenticated: boolean;
    permissions: SsoPermission[];
    roles: SsoRole[];
    can: (permission: string) => boolean;
    hasRole: (roleSlug: string) => boolean;
};

export const AuthContext = createContext<AuthContextValue | null>(null);

type AuthProviderProps = {
    data: SsoAuthData;
    children: React.ReactNode;
};

export function AuthProvider({ data, children }: AuthProviderProps) {
    const value = useMemo<AuthContextValue>(() => {
        const permissions = data.permissions ?? [];
        const roles = data.roles ?? [];
        const permissionSlugs = new Set(permissions.map((p) => p.slug));
        const roleSlugs = new Set(roles.map((r) => r.slug));

        return {
            user: data.user,
            isAuthenticated: data.user !== null,
            permissions,
            roles,
            can: (permission: string) => permissionSlugs.has(permission),
            hasRole: (roleSlug: string) => roleSlugs.has(roleSlug),
        };
    }, [data]);

    return <AuthContext value={value}>{children}</AuthContext>;
}
