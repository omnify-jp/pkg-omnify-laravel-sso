import {
    Button, Card, CardContent, CardHeader,
    CardTitle, Input, Label, PermissionGrid,
    Textarea,
} from '@omnifyjp/ui';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useIamLayout } from '@omnify-sso/contexts/iam-layout-context';

import { IamBreadcrumb } from '../../../components/access/iam-breadcrumb';
import type { IamPermission, IamRole } from '../../../types/iam';
import { buildPermissionModules, fromGridIds, toGridIds } from '../../../utils/scope-utils';

type Props = {
    role: IamRole;
    permissions: IamPermission[];
    all_permissions: IamPermission[];
};

export default function IamRoleEdit({ role, permissions, all_permissions }: Props) {
    const Layout = useIamLayout();
    const { t } = useTranslation();

    const currentPermissionIds = permissions.map((p) => p.id);

    const { data, setData, put, processing, errors } = useForm<{
        name: string;
        description: string;
        level: number;
        permission_ids: string[];
    }>({
        name: role.name,
        description: role.description ?? '',
        level: role.level,
        permission_ids: currentPermissionIds,
    });

    const permissionModules = useMemo(
        () => buildPermissionModules(all_permissions),
        [all_permissions],
    );

    const [selectedGridIds, setSelectedGridIds] = useState<string[]>(() =>
        toGridIds(all_permissions, currentPermissionIds),
    );

    const handlePermissionChange = (gridIds: string[]) => {
        setSelectedGridIds(gridIds);
        setData('permission_ids', fromGridIds(all_permissions, gridIds));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/admin/iam/roles/${role.id}`);
    };

    return (
        <Layout
            breadcrumbs={[
                { title: t('iam.title', 'IAM'), href: '/admin/iam' },
                { title: t('iam.roles', 'Roles'), href: '/admin/iam/roles' },
                { title: role.name, href: `/admin/iam/roles/${role.id}` },
                { title: t('iam.edit', 'Edit'), href: `/admin/iam/roles/${role.id}/edit` },
            ]}
        >
            <Head title={`${t('iam.edit', 'Edit')} — ${role.name}`} />

            <div className="space-y-section">
                <div className="flex items-center justify-between">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={`/admin/iam/roles/${role.id}`}>
                            <ArrowLeft className="h-4 w-4" />
                            {t('iam.backToRole', 'Back to Role')}
                        </Link>
                    </Button>
                    <IamBreadcrumb
                        segments={[
                            { label: t('iam.roles', 'Roles'), href: '/admin/iam/roles' },
                            { label: role.name, href: `/admin/iam/roles/${role.id}` },
                            { label: t('iam.edit', 'Edit') },
                        ]}
                    />
                </div>

                <form onSubmit={handleSubmit} className="space-y-section">
                    <div className="max-w-2xl">
                        <Card>
                            <CardHeader className="px-card pb-3 pt-card">
                                <CardTitle className="text-base">
                                    {t('iam.roleInfo', 'Role Information')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 px-card pb-card">
                                <div className="space-y-2">
                                    <Label htmlFor="name">
                                        {t('iam.roleName', 'Role Name')}
                                    </Label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                    />
                                    {errors.name && (
                                        <p className="text-sm text-destructive">{errors.name}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="description">
                                        {t('iam.description', 'Description')}
                                    </Label>
                                    <Textarea
                                        id="description"
                                        value={data.description}
                                        onChange={(e) => setData('description', e.target.value)}
                                    />
                                    {errors.description && (
                                        <p className="text-sm text-destructive">
                                            {errors.description}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="level">{t('iam.level', 'Level')}</Label>
                                    <Input
                                        id="level"
                                        type="number"
                                        min={1}
                                        max={10}
                                        value={data.level}
                                        onChange={(e) =>
                                            setData('level', parseInt(e.target.value, 10))
                                        }
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        {t(
                                            'iam.levelHelp',
                                            'Lower number = higher privilege (1 = Admin).',
                                        )}
                                    </p>
                                    {errors.level && (
                                        <p className="text-sm text-destructive">{errors.level}</p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Permission Matrix */}
                    <Card>
                        <CardHeader className="px-card pb-3 pt-card">
                            <CardTitle className="text-base">
                                {t('iam.permissionMatrix', 'Permission Matrix')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="px-card pb-card">
                            <PermissionGrid
                                modules={permissionModules}
                                selectedIds={selectedGridIds}
                                onChange={handlePermissionChange}
                                labels={{
                                    moduleHeader: t('iam.module', 'Module'),
                                    selectAll: t('iam.selectAll', 'All'),
                                }}
                            />
                            {errors.permission_ids && (
                                <p className="mt-2 text-sm text-destructive">
                                    {errors.permission_ids}
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <div className="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => window.history.back()}
                        >
                            {t('iam.cancel', 'Cancel')}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {t('iam.saveChanges', 'Save Changes')}
                        </Button>
                    </div>
                </form>
            </div>
        </Layout>
    );
}
