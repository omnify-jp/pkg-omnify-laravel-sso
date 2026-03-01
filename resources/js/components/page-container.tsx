import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { usePageLayout } from '@omnify-core/contexts/page-layout-context';
import type { BreadcrumbItem } from '@omnify-core/contexts/page-layout-context';

export type { BreadcrumbItem };

export type PageContainerProps = {
    title: string;
    subtitle?: string;
    breadcrumbs?: BreadcrumbItem[];
    extra?: ReactNode;
    footer?: ReactNode;
    children: ReactNode;
};

export function PageContainer({
    title,
    subtitle,
    breadcrumbs,
    extra,
    footer,
    children,
}: PageContainerProps) {
    const Layout = usePageLayout();

    return (
        <Layout breadcrumbs={breadcrumbs} title={title} subtitle={subtitle} extra={extra}>
            <Head title={title} />

            {children}

            {footer}
        </Layout>
    );
}
