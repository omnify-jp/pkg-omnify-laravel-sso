import { Head } from '@inertiajs/react';
import { Flex, Typography } from 'antd';
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
        <Layout breadcrumbs={breadcrumbs}>
            <Head title={title} />

            <Flex vertical gap={24}>
                {(title || extra) && (
                    <Flex justify="space-between" align="start">
                        <Flex vertical gap={2}>
                            <Typography.Title level={4} style={{ marginBottom: 0 }}>
                                {title}
                            </Typography.Title>
                            {subtitle && (
                                <Typography.Text type="secondary">{subtitle}</Typography.Text>
                            )}
                        </Flex>
                        {extra}
                    </Flex>
                )}

                {children}

                {footer}
            </Flex>
        </Layout>
    );
}
