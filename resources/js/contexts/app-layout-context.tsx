import { createContext, useContext } from 'react';
import type { PageLayoutComponent } from '@omnify-core/contexts/page-layout-context';

function PassthroughLayout({ children }: { children: React.ReactNode }) {
    return <>{children}</>;
}

/**
 * Provides the host app's root layout component.
 *
 * Unlike PageLayoutContext (which can be overridden per page prefix),
 * AppLayoutContext always holds the host app's AppLayout.
 * Used by nested layouts (e.g. OrgSettingsLayout) that need to wrap
 * their content in the host's base layout.
 */
export const AppLayoutContext = createContext<PageLayoutComponent>(PassthroughLayout);

export function useAppLayout(): PageLayoutComponent {
    return useContext(AppLayoutContext);
}
