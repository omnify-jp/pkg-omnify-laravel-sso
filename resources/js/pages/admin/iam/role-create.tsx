import {
    Button, Card, CardContent, CardHeader,
    CardTitle, Input, Label, PermissionGrid,
    Textarea,
} from '@omnifyjp/ui';
import { Head, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useIamLayout } from '@omnify-sso/contexts/iam-layout-context';

import { IamBreadcrumb } from '../../../components/access/iam-breadcrumb';
import type { IamPermission } from '../../../types/iam';
import { buildPermissionModules, fromGridIds, toGridIds } from '../../../utils/scope-utils';

type Props = {
    all_permissions: IamPermission[];
};

export default function IamRoleCreate({ all_permissions }: Props) {
    const Layout = useIamLayout();
    const { t } = useTranslation();

    const { data, setData, post, processing, errors } = useForm<{
        name: string;
        description: string;
        level: number;
        permission_ids: string[];
    }>({
        name: '',
        description: '',
        level: 3,
        permission_ids: [],
    });

    const permissionModules = useMemo(
        () => buildPermissionModules(all_permissions),
        [all_permissions],
    );

    const [selectedGridIds, setSelectedGridIds] = useState<string[]>([]);

    const handlePermissionChange = (gridIds: string[]) => {
        setSelectedGridIds(gridIds);
        setData('permission_ids', fromGridIds(all_permissions, gridIds));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/iam/roles');
    };

    return (
        <Layout
            breadcrumbs={[
                { title: t('iam.title', 'IAM'), href: '/admin/iam' },
                { title: t('iam.roles', 'Roles'), href: '/admin/iam/roles' },
                {
                    title: t('iam.createRole', 'Create Role'),
                    href: '/admin/iam/roles/create',
                },
            ]}
        >
            <Head title={t('iam.createRole', 'Create Role')} />

            <div className="space-y-section">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-page-title font-semibold">
                            {t('iam.createRole', 'Create Role')}
                        </h1>
                    </div>
                    <IamBreadcrumb
                        segments={[
                            { label: t('iam.roles', 'Roles'), href: '/admin/iam/roles' },
                            { label: t('iam.createRole', 'Create Role') },
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
                                        placeholder={t('iam.roleNamePlaceholder', 'e.g. Manager')}
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
                                        placeholder={t(
                                            'iam.descriptionPlaceholder',
                                            'Describe this role…',
                                        )}
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
                            {t('iam.save', 'Save')}
                        </Button>
                    </div>
                </form>
            </div>
        </Layout>
    );
}
