import { createContext, useContext } from 'react';
import type { ComponentType, ReactNode } from 'react';

export type AuthLayoutComponent = ComponentType<{
    children?: ReactNode;
    title?: string;
    description?: string;
}>;

function PassthroughLayout({ children }: { children?: ReactNode; title?: string; description?: string }) {
    return <>{children}</>;
}

/**
 * Provides the host app's auth layout component to SSO pages.
 *
 * The host app injects its AuthLayout via resolve-page.tsx:
 *   <AuthLayoutContext.Provider value={AuthLayout}>
 *     <Page {...props} />
 *   </AuthLayoutContext.Provider>
 */
export const AuthLayoutContext = createContext<AuthLayoutComponent>(PassthroughLayout);

export function useAuthLayout(): AuthLayoutComponent {
    return useContext(AuthLayoutContext);
}
