import { Link } from '@inertiajs/react';
import { PageContainer } from '@omnify-core/components/page-container';
import { useOrgRoute } from '@omnify-core/hooks/use-org-route';
import { Card, Col, Flex, Row, Typography, theme } from 'antd';
import { GitPullRequest, Shield } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useTranslation } from 'react-i18next';

type Section = {
    key: string;
    icon: string;
    title_key: string;
    title_default: string;
    description_key: string;
    description_default: string;
    path_suffix: string;
};

type Props = {
    sections: Section[];
};

const ICON_MAP: Record<string, LucideIcon> = {
    shield: Shield,
    'git-pull-request': GitPullRequest,
};

export default function OrgSettings({ sections }: Props) {
    const { t } = useTranslation();
    const { token } = theme.useToken();
    const orgRoute = useOrgRoute();

    return (
        <PageContainer
            title={t('orgSettings.title', 'Organization Settings')}
            subtitle={t('orgSettings.subtitle', 'Manage your organization services and configurations.')}
        >
            <Row gutter={[16, 16]}>
                {sections.map((section) => {
                    const Icon = ICON_MAP[section.icon] ?? Shield;
                    return (
                        <Col key={section.key} xs={24} sm={12} lg={8}>
                            <Link href={orgRoute(`/${section.path_suffix}`)}>
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
                                            <Icon size={24} />
                                        </Flex>
                                        <Flex vertical>
                                            <Typography.Text strong>{t(section.title_key, section.title_default)}</Typography.Text>
                                            <Typography.Text type="secondary">
                                                {t(section.description_key, section.description_default)}
                                            </Typography.Text>
                                        </Flex>
                                    </Flex>
                                </Card>
                            </Link>
                        </Col>
                    );
                })}
            </Row>
        </PageContainer>
    );
}
