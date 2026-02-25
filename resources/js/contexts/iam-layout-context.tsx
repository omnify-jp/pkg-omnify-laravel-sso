import { createContext, useContext } from 'react';
import type { ComponentType, ReactNode } from 'react';

export type IamBreadcrumbItem = {
    title: string;
    href: string;
};

/**
 * The shape that a host app's layout component must satisfy to be used by IAM pages.
 */
export type IamLayoutComponent = ComponentType<{
    children: ReactNode;
    breadcrumbs?: IamBreadcrumbItem[];
}>;

function PassthroughLayout({ children }: { children: ReactNode; breadcrumbs?: IamBreadcrumbItem[] }) {
    return <>{children}</>;
}

/**
 * Provides the host app's layout component to IAM pages.
 *
 * Wrap your app (in app.tsx) with this context and pass your AppLayout:
 *   <IamLayoutContext.Provider value={AppLayout}>
 *     <App {...props} />
 *   </IamLayoutContext.Provider>
 */
export const IamLayoutContext = createContext<IamLayoutComponent>(PassthroughLayout);

export function useIamLayout(): IamLayoutComponent {
    return useContext(IamLayoutContext);
}
