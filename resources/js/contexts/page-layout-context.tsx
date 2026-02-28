import { createContext, useContext } from 'react';
import type { ComponentType, ReactNode } from 'react';

export type BreadcrumbItem = {
    title: string;
    href: string;
};

export type PageLayoutComponent = ComponentType<{
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}>;

function PassthroughLayout({ children }: { children: ReactNode; breadcrumbs?: BreadcrumbItem[] }) {
    return <>{children}</>;
}

/**
 * Provides the host app's layout component to all pages.
 *
 * The host app injects its AppLayout via resolve-page.tsx:
 *   <PageLayoutContext.Provider value={AppLayout}>
 *     <Page {...props} />
 *   </PageLayoutContext.Provider>
 */
export const PageLayoutContext = createContext<PageLayoutComponent>(PassthroughLayout);

export function usePageLayout(): PageLayoutComponent {
    return useContext(PageLayoutContext);
}
