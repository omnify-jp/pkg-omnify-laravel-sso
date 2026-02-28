import { Avatar, Button, Card, Col, Descriptions, Divider, Flex, Row, Select, Tag, Typography } from 'antd';
import { PermissionGrid } from '../../../components/access/permission-grid';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Mail } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useIamLayout } from '@omnify-core/contexts/iam-layout-context';

import { IamBreadcrumb } from '../../../components/access/iam-breadcrumb';
import { ScopeTypeBadge } from '../../../components/access/scope-type-badge';
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
    /** Map of role_id -> permission_ids for computing effective permissions */
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
            // After the global early return, a.scope_type is 'org-wide' | 'branch'
            if (scopeType === 'global') {
                return false;
            }
            if (scopeType === 'org-wide') {
                return (
                    a.scope_type === 'org-wide' &&
                    a.organization_id === selected.organization_id
                );
            }
            if (scopeType === 'branch') {
                return (
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

            <Flex vertical gap={24}>
                <Flex justify="space-between" align="center">
                    <Link href="/admin/iam/users">
                        <Button type="text" size="small" icon={<ArrowLeft size={16} />}>
                            {t('iam.backToUsers', 'Back to Users')}
                        </Button>
                    </Link>
                    <IamBreadcrumb
                        segments={[
                            { label: t('iam.users', 'Users'), href: '/admin/iam/users' },
                            { label: user.name },
                        ]}
                    />
                </Flex>

                <Row gutter={[24, 24]}>
                    <Col xs={24} lg={8}>
                        <Card>
                            <Flex vertical align="center" gap={8}>
                                <Avatar size={64}>
                                    {user.name.slice(0, 2).toUpperCase()}
                                </Avatar>
                                <Typography.Title level={5}>{user.name}</Typography.Title>
                                <Flex align="center" gap={4}>
                                    <Mail size={12} />
                                    <Typography.Text type="secondary">{user.email}</Typography.Text>
                                </Flex>
                            </Flex>
                            <Divider />
                            <Descriptions column={1} size="small">
                                <Descriptions.Item label={t('iam.joined', 'Joined')}>
                                    {user.created_at
                                        ? new Date(user.created_at).toLocaleDateString()
                                        : '\u2014'}
                                </Descriptions.Item>
                                <Descriptions.Item label={t('iam.totalAssignments', 'Assignments')}>
                                    {assignments.length}
                                </Descriptions.Item>
                            </Descriptions>
                        </Card>
                    </Col>

                    <Col xs={24} lg={16}>
                        <Flex vertical gap={24}>
                            <Card title={t('iam.roleAssignments', 'Role Assignments')}>
                                {assignments.length === 0 ? (
                                    <Typography.Text type="secondary">
                                        {t('iam.noAssignments', 'No role assignments.')}
                                    </Typography.Text>
                                ) : (
                                    <Flex vertical gap={24}>
                                        {groupedAssignments.map((group) => (
                                            <Flex vertical key={group.scope} gap={8}>
                                                <Flex align="center" gap={8}>
                                                    <ScopeTypeBadge
                                                        type={toScopeBadgeType(group.scope)}
                                                        label={getScopeLabel(group.scope)}
                                                    />
                                                </Flex>
                                                <Flex vertical gap={8}>
                                                    {group.assignments.map((assignment, index) => (
                                                        <Card key={index} size="small">
                                                            <Flex vertical gap={4}>
                                                                <Flex align="center" gap={8}>
                                                                    <Tag>
                                                                        <Typography.Text type="secondary">
                                                                            Lv.
                                                                            {assignment.role.level}
                                                                        </Typography.Text>
                                                                        {' '}{assignment.role.name}
                                                                    </Tag>
                                                                    <Typography.Text type="secondary">
                                                                        @{' '}
                                                                        {formatScopeLocation(
                                                                            assignment,
                                                                        )}
                                                                    </Typography.Text>
                                                                </Flex>
                                                                {assignment.created_at && (
                                                                    <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                                                                        {new Date(
                                                                            assignment.created_at,
                                                                        ).toLocaleDateString()}
                                                                    </Typography.Text>
                                                                )}
                                                            </Flex>
                                                        </Card>
                                                    ))}
                                                </Flex>
                                            </Flex>
                                        ))}
                                    </Flex>
                                )}
                            </Card>

                            <Card
                                title={
                                    selectedPermScope
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
                                          )
                                }
                                extra={
                                    <Select
                                        value={selectedPermScope || undefined}
                                        onChange={(value) => setSelectedPermScope(value ?? '')}
                                        placeholder={t(
                                            'iam.selectScopeToView',
                                            'Select scope\u2026',
                                        )}
                                        popupMatchSelectWidth={false}
                                        options={scopeOptions.map((opt) => ({
                                            value: opt.value,
                                            label: opt.label,
                                        }))}
                                    />
                                }
                            >
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
                                        <Typography.Text type="secondary">
                                            {t('iam.noPermissions', 'No permissions configured.')}
                                        </Typography.Text>
                                    )
                                ) : (
                                    <Typography.Text type="secondary">
                                        {t(
                                            'iam.selectScopeToView',
                                            'Select a scope above to view effective permissions.',
                                        )}
                                    </Typography.Text>
                                )}
                            </Card>
                        </Flex>
                    </Col>
                </Row>
            </Flex>
        </Layout>
    );
}
