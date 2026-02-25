import {
    Badge, Card, CardContent, CardHeader,
    CardTitle,
} from '@omnifyjp/ui';
import { Head } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { useIamLayout } from '@omnify-sso/contexts/iam-layout-context';

import { IamBreadcrumb } from '../../../components/access/iam-breadcrumb';
import type { IamPermission } from '../../../types/iam';

type Props = {
    permissions: IamPermission[];
};

export default function IamPermissions({ permissions }: Props) {
    const Layout = useIamLayout();
    const { t } = useTranslation();

    const grouped = useMemo(() => {
        const map = new Map<string, IamPermission[]>();
        for (const perm of permissions) {
            const group = perm.group ?? 'general';
            if (!map.has(group)) {
                map.set(group, []);
            }
            map.get(group)!.push(perm);
        }
        return Array.from(map.entries()).map(([group, perms]) => ({ group, perms }));
    }, [permissions]);

    return (
        <Layout
            breadcrumbs={[
                { title: t('iam.title', 'IAM'), href: '/admin/iam' },
                { title: t('iam.permissions', 'Permissions'), href: '/admin/iam/permissions' },
            ]}
        >
            <Head title={t('iam.permissions', 'Permissions')} />

            <div className="space-y-section">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-page-title font-semibold">{t('iam.permissions', 'Permissions')}</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('iam.permissionsSubtitle', 'All permissions registered in this application.')}
                        </p>
                    </div>
                    <IamBreadcrumb segments={[{ label: t('iam.permissions', 'Permissions') }]} />
                </div>

                {grouped.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 py-16 text-muted-foreground">
                        <ShieldCheck className="h-10 w-10 opacity-40" />
                        <p className="text-sm">{t('iam.noPermissions', 'No permissions found.')}</p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {grouped.map(({ group, perms }) => (
                            <Card key={group}>
                                <CardHeader className="px-card pb-3 pt-card">
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <span className="capitalize">{group}</span>
                                        <Badge variant="secondary">{perms.length}</Badge>
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="px-card pb-card">
                                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                        {perms.map((perm) => (
                                            <div
                                                key={perm.id}
                                                className="rounded-lg border border-border p-3"
                                            >
                                                <p className="text-sm font-medium">{perm.name}</p>
                                                <p className="mt-0.5 font-mono text-xs text-muted-foreground">
                                                    {perm.slug}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                <p className="text-sm text-muted-foreground">
                    {t('iam.totalPermissions', '{{count}} permissions across {{groups}} groups', {
                        count: permissions.length,
                        groups: grouped.length,
                    })}
                </p>
            </div>
        </Layout>
    );
}
