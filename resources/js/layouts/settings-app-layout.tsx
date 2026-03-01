import { usePage } from '@inertiajs/react';
import { SettingsSidebar } from '@omnify-core/components/settings-sidebar';
import { Col, Flex, Row, Typography, theme } from 'antd';
import type { ComponentType, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';

type BreadcrumbItem = { title: string; href: string };

type Props = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    title?: string;
    subtitle?: string;
    extra?: ReactNode;
    baseLayout: ComponentType<{ children: ReactNode; breadcrumbs?: BreadcrumbItem[] }>;
};

export default function SettingsAppLayout({ children, breadcrumbs, title, subtitle, extra, baseLayout: BaseLayout }: Props) {
    const { t } = useTranslation();
    const { token } = theme.useToken();
    const { url } = usePage();

    // Settings index: no sidebar, no breadcrumb prefix
    const isIndex = url === '/settings' || url === '/settings/';

    const settingsBreadcrumbs: BreadcrumbItem[] = isIndex
        ? []
        : [{ title: t('settings.title', 'Settings'), href: '/settings' }, ...(breadcrumbs ?? [])];

    return (
        <BaseLayout breadcrumbs={settingsBreadcrumbs}>
            <Flex vertical gap="large">
                {(title || extra) && (
                    <Flex justify="space-between" align="start">
                        <Flex vertical gap={token.paddingXXS / 2}>
                            <Typography.Title level={4} style={{ margin: 0 }}>
                                {title}
                            </Typography.Title>
                            {subtitle && (
                                <Typography.Text type="secondary">{subtitle}</Typography.Text>
                            )}
                        </Flex>
                        {extra}
                    </Flex>
                )}

                {!isIndex ? (
                    <Row gutter={24}>
                        <Col xs={24} md={6}>
                            <SettingsSidebar />
                        </Col>
                        <Col xs={24} md={18}>
                            {children}
                        </Col>
                    </Row>
                ) : (
                    children
                )}
            </Flex>
        </BaseLayout>
    );
}
