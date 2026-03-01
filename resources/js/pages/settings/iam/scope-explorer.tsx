import { Card, Col, Row } from 'antd';
import { ScopeTree } from '@omnify-core/components/access/scope-tree';
import { useState, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { useOrgRoute } from '@omnify-core/hooks/use-org-route';
import { PageContainer } from '@omnify-core/components/page-container';

import { ScopeDetailPanel } from '@omnify-core/components/access/scope-detail-panel';
import type { IamAssignment, IamBranch, IamOrganization, ScopeType } from '@omnify-core/types/iam';
import { buildScopeTree } from '@omnify-core/utils/scope-utils';

type Props = {
    organizations: IamOrganization[];
    branches: IamBranch[];
    assignments: IamAssignment[];
};

export default function IamScopeExplorer({ organizations, branches, assignments }: Props) {
    const { t } = useTranslation();
    const orgRoute = useOrgRoute();
    const iamBase = orgRoute('/settings/iam');

    const [selectedScope, setSelectedScope] = useState<{ type: ScopeType; id: string | null }>({
        type: 'global',
        id: null,
    });

    const nodes = useMemo(() => {
        const root = buildScopeTree(organizations, branches);
        return [root];
    }, [organizations, branches]);

    return (
        <PageContainer
            title={t('iam.scopeExplorer', 'Scope Explorer')}
            subtitle={t('iam.scopeExplorerSubtitle', 'Browse role assignments across your scope hierarchy.')}
            breadcrumbs={[
                { title: t('iam.scopeExplorer', 'Scope Explorer'), href: `${iamBase}/scope-explorer` },
            ]}
        >
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
        </PageContainer>
    );
}
