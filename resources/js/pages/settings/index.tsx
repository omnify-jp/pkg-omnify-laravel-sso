import { Link } from '@inertiajs/react';
import { PageContainer } from '@omnify-core/components/page-container';
import { Card, Col, Flex, Row, Typography, theme } from 'antd';
import { KeyRound, ShieldCheck } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export default function SettingsIndex() {
    const { t } = useTranslation();
    const { token } = theme.useToken();

    const sections = [
        {
            key: 'account',
            icon: <KeyRound size={24} />,
            title: t('settings.account.title', 'Account'),
            description: t('settings.account.description', 'Change your password and manage account credentials.'),
            href: '/settings/account',
        },
        {
            key: 'security',
            icon: <ShieldCheck size={24} />,
            title: t('settings.security.title', 'Security'),
            description: t('settings.security.description', 'Manage two-factor authentication and account security options.'),
            href: '/settings/security',
        },
    ];

    return (
        <PageContainer
            title={t('settings.title', 'Settings')}
            subtitle={t('settings.subtitle', 'Manage your personal account settings and security.')}
        >
            <Row gutter={[16, 16]}>
                {sections.map((section) => (
                    <Col key={section.key} xs={24} sm={12} lg={8}>
                        <Link href={section.href}>
                            <Card hoverable size="small" style={{ height: '100%' }}>
                                <Flex align="center" gap="small">
                                    <Flex
                                        align="center"
                                        justify="center"
                                        style={{
                                            width: token.controlHeightLG * 1.2,
                                            height: token.controlHeightLG * 1.2,
                                            borderRadius: token.borderRadius,
                                            background: token.colorFillSecondary,
                                            flexShrink: 0,
                                            color: token.colorTextSecondary,
                                        }}
                                    >
                                        {section.icon}
                                    </Flex>
                                    <Flex vertical>
                                        <Typography.Text strong>{section.title}</Typography.Text>
                                        <Typography.Text type="secondary">
                                            {section.description}
                                        </Typography.Text>
                                    </Flex>
                                </Flex>
                            </Card>
                        </Link>
                    </Col>
                ))}
            </Row>
        </PageContainer>
    );
}
