import { Link } from '@inertiajs/react';
import { Avatar, Col, ConfigProvider, Flex, Layout, Row, Tag, Typography } from 'antd';
import { Database, Server, ShieldAlert } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { adminAuthTheme, adminColors } from '@omnify-core/lib/antd-theme';
import type { ReactNode } from 'react';

function FeatureItem({ icon: Icon, title, description }: { icon: typeof Database; title: string; description: string }) {
    return (
        <Flex gap={12} align="start">
            <Avatar size={40} shape="square" icon={<Icon size={20} />} style={{ backgroundColor: adminColors.primary }} />
            <Flex vertical>
                <Typography.Text strong style={{ color: adminColors.text }}>{title}</Typography.Text>
                <Typography.Text style={{ color: adminColors.textMuted }}>{description}</Typography.Text>
            </Flex>
        </Flex>
    );
}

function AdminBrandingPanel() {
    const { t } = useTranslation();

    return (
        <Layout style={{ height: '100%', backgroundColor: adminColors.bg }}>
            <Layout.Content style={{ padding: 40, display: 'flex', flexDirection: 'column', justifyContent: 'space-between', backgroundColor: adminColors.bg }}>
                <div>
                    <Flex align="center" gap={10}>
                        <Avatar size={36} shape="square" icon={<ShieldAlert size={20} />} style={{ backgroundColor: adminColors.primary }} />
                        <span style={{ fontSize: 20, fontWeight: 600, color: adminColors.text }}>
                            {t('admin.layout.appName', 'Admin')}
                        </span>
                    </Flex>
                </div>

                <Flex vertical gap={32}>
                    <Flex vertical gap={16}>
                        <Tag color={adminColors.primary} icon={<ShieldAlert size={12} />} style={{ width: 'fit-content' }}>
                            {t('admin.layout.godModeLabel', 'GOD MODE')}
                        </Tag>
                        <span style={{ fontSize: 30, fontWeight: 600, color: adminColors.text }}>
                            {t('admin.layout.headline', 'System Administration')}
                        </span>
                        <span style={{ fontSize: 16, color: adminColors.textMuted }}>
                            {t('admin.layout.subheadline', 'Manage organizations, users, and system settings')}
                        </span>
                    </Flex>

                    <Flex vertical gap={16}>
                        <FeatureItem
                            icon={Database}
                            title={t('admin.layout.feature1Title', 'Data Management')}
                            description={t('admin.layout.feature1Description', 'Full access to all system data')}
                        />
                        <FeatureItem
                            icon={Server}
                            title={t('admin.layout.feature2Title', 'System Settings')}
                            description={t('admin.layout.feature2Description', 'Configure system-wide settings')}
                        />
                        <FeatureItem
                            icon={ShieldAlert}
                            title={t('admin.layout.feature3Title', 'Security Control')}
                            description={t('admin.layout.feature3Description', 'Manage users and access control')}
                        />
                    </Flex>
                </Flex>

            </Layout.Content>
        </Layout>
    );
}

type AdminAuthLayoutProps = {
    children?: ReactNode;
    title?: string;
    description?: string;
};

export default function AdminAuthLayout({ children, title, description }: AdminAuthLayoutProps) {
    return (
        <ConfigProvider theme={adminAuthTheme}>
            <Row align="stretch" style={{ minHeight: '100vh' }}>
                <Col xs={0} lg={12} xl={13}>
                    <AdminBrandingPanel />
                </Col>

                <Col xs={24} lg={12} xl={11}>
                    <Flex vertical justify="center" align="center" style={{ minHeight: '100vh', padding: '40px 24px' }}>
                        <div style={{ width: '100%', maxWidth: 400 }}>
                            <Flex align="center" gap={8} justify="space-between">
                                <Link href="/">
                                    <Flex align="center" gap={8}>
                                        <Avatar size={32} shape="square" icon={<ShieldAlert size={16} />} style={{ backgroundColor: adminColors.primary }} />
                                        <Typography.Text strong>Admin</Typography.Text>
                                    </Flex>
                                </Link>
                            </Flex>

                            {(title || description) && (
                                <div style={{ marginTop: 40 }}>
                                    {title && <Typography.Title level={3}>{title}</Typography.Title>}
                                    {description && <Typography.Text type="secondary">{description}</Typography.Text>}
                                </div>
                            )}

                            <div style={{ marginTop: 32 }}>
                                {children}
                            </div>
                        </div>
                    </Flex>
                </Col>
            </Row>
        </ConfigProvider>
    );
}
