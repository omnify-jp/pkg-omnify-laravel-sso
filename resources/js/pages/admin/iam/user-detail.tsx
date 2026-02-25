import {
    Avatar, AvatarFallback, Badge, Button,
    Card, CardContent, CardHeader, CardTitle,
    PermissionGrid, Select, SelectContent, SelectItem,
    SelectTrigger, SelectValue, Separator, ScopeTypeBadge,
} from '@omnifyjp/ui';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Mail } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useIamLayout } from '@omnify-sso/contexts/iam-layout-context';

import { IamBreadcrumb } from '../../../components/access/iam-breadcrumb';
import type { IamPermission, IamRoleAssignment, IamUser, ScopeType } from '../../../types/iam';
import {
    buildPermissionModules,
    formatScopeLocation,
    getScopeLabel,
    toGridIds,
    toScopeBadgeType,
} from '../../../utils/scope-utils';

type Props = {
    user: IamUser;
    assignments: IamRoleAssignment[];
    all_permissions: IamPermission[];
    /** Map of role_id → permission_ids for computing effective permissions */
    role_permissions: Record<string, string[]>;
};

const SCOPE_ORDER: ScopeType[] = ['global', 'org-wide', 'branch'];

export default function IamUserDetail({ user, assignments, all_permissions, role_permissions }: Props) {
    const Layout = useIamLayout();
    const { t } = useTranslation();
    const [selectedPermScope, setSelectedPermScope] = useState<string>('');

    const permissionModules = useMemo(
        () => buildPermissionModules(all_permissions),
        [all_permissions],
    );

    const groupedAssignments = SCOPE_ORDER.map((scope) => ({
        scope,
        assignments: assignments.filter((a) => a.scope_type === scope),
    })).filter((g) => g.assignments.length > 0);

    // Build scope options for effective permissions dropdown
    const scopeOptions = assignments.map((a, index) => ({
        value: `${a.scope_type}:${a.organization_id ?? a.branch_id ?? 'null'}:${index}`,
        label: formatScopeLocation(a),
        scope_type: a.scope_type,
        organization_id: a.organization_id,
        branch_id: a.branch_id,
        role_id: a.role.id,
    }));

    // Calculate effective permission IDs at selected scope (with inheritance)
    const effectivePermissionIds = useMemo((): string[] => {
        if (!selectedPermScope) {
            return [];
        }

        const selected = scopeOptions.find((o) => o.value === selectedPermScope);
        if (!selected) {
            return [];
        }

        const scopeType = selected.scope_type;

        // Find all relevant assignments (direct + inherited from ancestors)
        const relevant = assignments.filter((a) => {
            if (a.scope_type === 'global') {
                return true;
            }
            if (scopeType === 'global') {
                return a.scope_type === 'global';
            }
            if (scopeType === 'org-wide') {
                return (
                    a.scope_type === 'global' ||
                    (a.scope_type === 'org-wide' &&
                        a.organization_id === selected.organization_id)
                );
            }
            if (scopeType === 'branch') {
                return (
                    a.scope_type === 'global' ||
                    a.scope_type === 'org-wide' ||
                    (a.scope_type === 'branch' && a.branch_id === selected.branch_id)
                );
            }
            return false;
        });

        // Collect all unique permission IDs from these roles
        const permIds = new Set<string>();
        for (const a of relevant) {
            const perms = role_permissions[a.role.id] ?? [];
            for (const id of perms) {
                permIds.add(id);
            }
        }

        return [...permIds];
    }, [selectedPermScope, assignments, scopeOptions, role_permissions]);

    return (
        <Layout
            breadcrumbs={[
                { title: t('iam.title', 'IAM'), href: '/admin/iam' },
                { title: t('iam.users', 'Users'), href: '/admin/iam/users' },
                { title: user.name, href: `/admin/iam/users/${user.id}` },
            ]}
        >
            <Head title={user.name} />

            <div className="space-y-section">
                <div className="flex items-center justify-between">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href="/admin/iam/users">
                            <ArrowLeft className="h-4 w-4" />
                            {t('iam.backToUsers', 'Back to Users')}
                        </Link>
                    </Button>
                    <IamBreadcrumb
                        segments={[
                            { label: t('iam.users', 'Users'), href: '/admin/iam/users' },
                            { label: user.name },
                        ]}
                    />
                </div>

                <div className="grid grid-cols-1 gap-section lg:grid-cols-3">
                    {/* User Info */}
                    <Card>
                        <CardContent className="px-card pb-card pt-card">
                            <div className="flex flex-col items-center text-center">
                                <Avatar className="mb-3 h-16 w-16">
                                    <AvatarFallback className="text-lg">
                                        {user.name.slice(0, 2).toUpperCase()}
                                    </AvatarFallback>
                                </Avatar>
                                <h3 className="text-lg font-semibold">{user.name}</h3>
                                <div className="mt-1 flex items-center gap-1 text-sm text-muted-foreground">
                                    <Mail className="h-3 w-3" />
                                    {user.email}
                                </div>
                            </div>
                            <Separator className="my-4" />
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        {t('iam.joined', 'Joined')}
                                    </span>
                                    <span>
                                        {user.created_at
                                            ? new Date(user.created_at).toLocaleDateString()
                                            : '—'}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        {t('iam.totalAssignments', 'Assignments')}
                                    </span>
                                    <span>{assignments.length}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Role Assignments + Effective Permissions */}
                    <div className="space-y-section lg:col-span-2">
                        {/* Scoped Assignments */}
                        <Card>
                            <CardHeader className="px-card pb-3 pt-card">
                                <CardTitle className="text-base">
                                    {t('iam.roleAssignments', 'Role Assignments')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="px-card pb-card">
                                {assignments.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t('iam.noAssignments', 'No role assignments.')}
                                    </p>
                                ) : (
                                    <div className="space-y-6">
                                        {groupedAssignments.map((group) => (
                                            <div key={group.scope}>
                                                <div className="mb-2 flex items-center gap-2">
                                                    <ScopeTypeBadge
                                                        type={toScopeBadgeType(group.scope)}
                                                        label={getScopeLabel(group.scope)}
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    {group.assignments.map((assignment, index) => (
                                                        <div
                                                            key={index}
                                                            className="flex items-center gap-3 rounded-lg border border-border p-3"
                                                        >
                                                            <div className="flex-1">
                                                                <div className="flex items-center gap-2">
                                                                    <Badge variant="outline">
                                                                        <span className="mr-1 text-xs text-muted-foreground">
                                                                            Lv.
                                                                            {assignment.role.level}
                                                                        </span>
                                                                        {assignment.role.name}
                                                                    </Badge>
                                                                    <span className="text-sm text-muted-foreground">
                                                                        @{' '}
                                                                        {formatScopeLocation(
                                                                            assignment,
                                                                        )}
                                                                    </span>
                                                                </div>
                                                                {assignment.created_at && (
                                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                                        {new Date(
                                                                            assignment.created_at,
                                                                        ).toLocaleDateString()}
                                                                    </p>
                                                                )}
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Effective Permissions */}
                        <Card>
                            <CardHeader className="px-card pb-3 pt-card">
                                <div className="flex items-center justify-between">
                                    <CardTitle className="text-base">
                                        {selectedPermScope
                                            ? t(
                                                  'iam.effectivePermissionsAt',
                                                  'Effective Permissions @ {{scope}}',
                                                  {
                                                      scope:
                                                          scopeOptions.find(
                                                              (o) => o.value === selectedPermScope,
                                                          )?.label ?? '',
                                                  },
                                              )
                                            : t(
                                                  'iam.effectivePermissions',
                                                  'Effective Permissions',
                                              )}
                                    </CardTitle>
                                    <Select
                                        value={selectedPermScope}
                                        onValueChange={setSelectedPermScope}
                                    >
                                        <SelectTrigger className="w-56">
                                            <SelectValue
                                                placeholder={t(
                                                    'iam.selectScopeToView',
                                                    'Select scope…',
                                                )}
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {scopeOptions.map((opt) => (
                                                <SelectItem key={opt.value} value={opt.value}>
                                                    {opt.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </CardHeader>
                            <CardContent className="px-card pb-card">
                                {selectedPermScope ? (
                                    permissionModules.length > 0 ? (
                                        <PermissionGrid
                                            modules={permissionModules}
                                            selectedIds={toGridIds(
                                                all_permissions,
                                                effectivePermissionIds,
                                            )}
                                            readOnly
                                            labels={{
                                                moduleHeader: t('iam.module', 'Module'),
                                                selectAll: t('iam.selectAll', 'All'),
                                            }}
                                        />
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            {t('iam.noPermissions', 'No permissions configured.')}
                                        </p>
                                    )
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        {t(
                                            'iam.selectScopeToView',
                                            'Select a scope above to view effective permissions.',
                                        )}
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </Layout>
    );
}
