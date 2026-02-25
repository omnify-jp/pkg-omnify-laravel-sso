import {
    Avatar, AvatarFallback, Badge, Button,
    Card, CardContent, CardHeader, CardTitle,
    PermissionGrid, Separator, ScopeTypeBadge,
} from '@omnifyjp/ui';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Pencil } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { useIamLayout } from '@omnify-sso/contexts/iam-layout-context';

import { IamBreadcrumb } from '../../../components/access/iam-breadcrumb';
import type { IamPermission, IamRole, IamUserAssignment, ScopeType } from '../../../types/iam';
import { buildPermissionModules, formatScopeLocation, getScopeLabel, toGridIds, toScopeBadgeType } from '../../../utils/scope-utils';

type Props = {
    role: IamRole;
    permissions: IamPermission[];
    all_permissions: IamPermission[];
    assignments: IamUserAssignment[];
};

const SCOPE_ORDER: ScopeType[] = ['global', 'org-wide', 'branch'];

export default function IamRoleDetail({ role, permissions, all_permissions, assignments }: Props) {
    const Layout = useIamLayout();
    const { t } = useTranslation();

    const permissionModules = useMemo(() => buildPermissionModules(all_permissions), [all_permissions]);
    const selectedGridIds = useMemo(
        () => toGridIds(all_permissions, permissions.map((p) => p.id)),
        [all_permissions, permissions],
    );

    const groupedAssignments = SCOPE_ORDER.map((scope) => ({
        scope,
        assignments: assignments.filter((a) => a.scope_type === scope),
    })).filter((g) => g.assignments.length > 0);

    return (
        <Layout
            breadcrumbs={[
                { title: t('iam.title', 'IAM'), href: '/admin/iam' },
                { title: t('iam.roles', 'Roles'), href: '/admin/iam/roles' },
                { title: role.name, href: `/admin/iam/roles/${role.id}` },
            ]}
        >
            <Head title={role.name} />

            <div className="space-y-section">
                <div className="flex items-center justify-between">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href="/admin/iam/roles">
                            <ArrowLeft className="h-4 w-4" />
                            {t('iam.backToRoles', 'Back to Roles')}
                        </Link>
                    </Button>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={`/admin/iam/roles/${role.id}/edit`}>
                                <Pencil className="h-4 w-4" />
                                {t('iam.editRole', 'Edit Role')}
                            </Link>
                        </Button>
                            <IamBreadcrumb
                            segments={[
                                { label: t('iam.roles', 'Roles'), href: '/admin/iam/roles' },
                                { label: role.name },
                            ]}
                        />
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-section lg:grid-cols-3">
                    {/* Role Info */}
                    <Card>
                        <CardContent className="px-card pb-card pt-card">
                            <div className="mb-4 flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-sm font-semibold text-primary">
                                    {role.name.slice(0, 2).toUpperCase()}
                                </div>
                                <div>
                                    <h3 className="font-semibold">{role.name}</h3>
                                    <p className="text-xs text-muted-foreground">{role.slug}</p>
                                </div>
                            </div>
                            {role.description && (
                                <p className="mb-4 text-sm text-muted-foreground">{role.description}</p>
                            )}
                            <Separator className="my-4" />
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">{t('iam.level', 'Level')}</span>
                                    <Badge variant="outline">Lv.{role.level}</Badge>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">{t('iam.scope', 'Scope')}</span>
                                    <Badge variant="outline">
                                        {role.is_global ? t('iam.global', 'Global') : t('iam.orgScoped', 'Org-scoped')}
                                    </Badge>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">{t('iam.permissions', 'Permissions')}</span>
                                    <span>{permissions.length}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">{t('iam.assignments', 'Assignments')}</span>
                                    <span>{assignments.length}</span>
                                </div>
                                {role.created_at && (
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">{t('iam.created', 'Created')}</span>
                                        <span>{new Date(role.created_at).toLocaleDateString()}</span>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Permissions + Assignments */}
                    <div className="space-y-section lg:col-span-2">
                        {/* Permission Matrix */}
                        <Card>
                            <CardHeader className="px-card pb-3 pt-card">
                                <CardTitle className="text-base">{t('iam.permissionMatrix', 'Permission Matrix')}</CardTitle>
                            </CardHeader>
                            <CardContent className="px-card pb-card">
                                {permissionModules.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">{t('iam.noPermissions', 'No permissions configured.')}</p>
                                ) : (
                                    <PermissionGrid
                                        modules={permissionModules}
                                        selectedIds={selectedGridIds}
                                        readOnly
                                        labels={{
                                            moduleHeader: t('iam.module', 'Module'),
                                            selectAll: t('iam.selectAll', 'All'),
                                        }}
                                    />
                                )}
                            </CardContent>
                        </Card>

                        {/* Where Assigned */}
                        <Card>
                            <CardHeader className="px-card pb-3 pt-card">
                                <CardTitle className="text-base">
                                    {t('iam.whereAssigned', 'Where Assigned')} ({assignments.length})
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="px-card pb-card">
                                {assignments.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">{t('iam.noAssignments', 'Not assigned to anyone yet.')}</p>
                                ) : (
                                    <div className="space-y-4">
                                        {groupedAssignments.map((group) => (
                                            <div key={group.scope}>
                                                <div className="mb-2 flex items-center gap-2">
                                                    <ScopeTypeBadge
                                                        type={toScopeBadgeType(group.scope)}
                                                        label={getScopeLabel(group.scope)}
                                                    />
                                                    <span className="text-xs text-muted-foreground">
                                                        ({group.assignments.length})
                                                    </span>
                                                </div>
                                                <div className="space-y-2">
                                                    {group.assignments.map((assignment, index) => (
                                                        <Link
                                                            key={index}
                                                            href={`/admin/iam/users/${assignment.user.id}`}
                                                            className="flex items-center gap-3 rounded-md p-2 transition-colors hover:bg-accent"
                                                        >
                                                            <Avatar className="h-8 w-8">
                                                                <AvatarFallback className="text-xs">
                                                                    {assignment.user.name.slice(0, 2).toUpperCase()}
                                                                </AvatarFallback>
                                                            </Avatar>
                                                            <div className="min-w-0 flex-1">
                                                                <p className="truncate text-sm font-medium">
                                                                    {assignment.user.name}
                                                                </p>
                                                                <p className="truncate text-xs text-muted-foreground">
                                                                    {formatScopeLocation(assignment)}
                                                                </p>
                                                            </div>
                                                        </Link>
                                                    ))}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </Layout>
    );
}
