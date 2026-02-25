import {
    Card, CardContent, CardHeader, CardTitle,
    ScopeTree,
} from '@omnifyjp/ui';
import { Head } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { useIamLayout } from '@omnify-sso/contexts/iam-layout-context';

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

            <div className="space-y-section">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-page-title font-semibold">
                            {t('iam.scopeExplorer', 'Scope Explorer')}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t(
                                'iam.scopeExplorerSubtitle',
                                'Browse role assignments across your scope hierarchy.',
                            )}
                        </p>
                    </div>
                    <IamBreadcrumb
                        segments={[{ label: t('iam.scopeExplorer', 'Scope Explorer') }]}
                    />
                </div>

                <div className="grid grid-cols-1 gap-section lg:grid-cols-[320px_1fr]">
                    {/* Left: Scope Tree */}
                    <Card className="self-start lg:sticky lg:top-4">
                        <CardHeader className="px-card pb-3 pt-card">
                            <CardTitle className="text-base">
                                {t('iam.selectScope', 'Select Scope')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="px-card pb-card">
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
                        </CardContent>
                    </Card>

                    {/* Right: Detail Panel */}
                    <Card>
                        <CardContent className="px-card pb-card pt-card">
                            <ScopeDetailPanel
                                scope={selectedScope.type}
                                scopeId={selectedScope.id}
                                assignments={assignments}
                                organizations={organizations}
                                branches={branches}
                            />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </Layout>
    );
}
