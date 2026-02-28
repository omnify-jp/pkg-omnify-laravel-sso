import { Card, Col, Flex, Row, Typography } from 'antd';
import { ScopeTree } from '../../../components/access/scope-tree';
import { Head } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { useIamLayout } from '@omnify-core/contexts/iam-layout-context';

import { ScopeDetailPanel } from '../../../components/access/scope-detail-panel';
import { IamBreadcrumb } from '../../../components/access/iam-breadcrumb';
import type { IamAssignment, IamBranch, IamOrganization, ScopeType } from '../../../types/iam';
import { buildScopeTree } from '../../../utils/scope-utils';

type Props = {
    organizations: IamOrganization[];
    branches: IamBranch[];
    assignments: IamAssignment[];
};

export default function IamScopeExplorer({ organizations, branches, assignments }: Props) {
    const Layout = useIamLayout();
    const { t } = useTranslation();

    const [selectedScope, setSelectedScope] = useState<{ type: ScopeType; id: string | null }>({
        type: 'global',
        id: null,
    });

    const nodes = useMemo(() => {
        const root = buildScopeTree(organizations, branches);
        return [root];
    }, [organizations, branches]);

    return (
        <Layout
            breadcrumbs={[
                { title: t('iam.title', 'IAM'), href: '/admin/iam' },
                { title: t('iam.scopeExplorer', 'Scope Explorer'), href: '/admin/iam/scope-explorer' },
            ]}
        >
            <Head title={t('iam.scopeExplorer', 'Scope Explorer')} />

            <Flex vertical gap={24}>
                <Flex justify="space-between" align="center">
                    <Flex vertical>
                        <Typography.Title level={4}>
                            {t('iam.scopeExplorer', 'Scope Explorer')}
                        </Typography.Title>
                        <Typography.Text type="secondary">
                            {t(
                                'iam.scopeExplorerSubtitle',
                                'Browse role assignments across your scope hierarchy.',
                            )}
                        </Typography.Text>
                    </Flex>
                    <IamBreadcrumb
                        segments={[{ label: t('iam.scopeExplorer', 'Scope Explorer') }]}
                    />
                </Flex>

                <Row gutter={[24, 24]}>
                    <Col xs={24} lg={8}>
                        <Card title={t('iam.selectScope', 'Select Scope')}>
                            <ScopeTree
                                nodes={nodes}
                                selectedScope={selectedScope}
                                onSelect={(s) =>
                                    setSelectedScope({
                                        type: s.type as ScopeType,
                                        id: s.id,
                                    })
                                }
                                defaultExpandDepth={2}
                            />
                        </Card>
                    </Col>

                    <Col xs={24} lg={16}>
                        <Card>
                            <ScopeDetailPanel
                                scope={selectedScope.type}
                                scopeId={selectedScope.id}
                                assignments={assignments}
                                organizations={organizations}
                                branches={branches}
                            />
                        </Card>
                    </Col>
                </Row>
            </Flex>
        </Layout>
    );
}
