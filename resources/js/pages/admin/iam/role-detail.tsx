import { Avatar, Button, Card, Col, Descriptions, Divider, Flex, Row, Tag, Typography } from 'antd';
import { PermissionGrid } from '../../../components/access/permission-grid';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Pencil } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { useIamLayout } from '@omnify-core/contexts/iam-layout-context';

import { IamBreadcrumb } from '../../../components/access/iam-breadcrumb';
import { ScopeTypeBadge } from '../../../components/access/scope-type-badge';
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

            <Flex vertical gap={24}>
                <Flex justify="space-between" align="center">
                    <Link href="/admin/iam/roles">
                        <Button type="text" size="small" icon={<ArrowLeft size={16} />}>
                            {t('iam.backToRoles', 'Back to Roles')}
                        </Button>
                    </Link>
                    <Flex align="center" gap={8}>
                        <Link href={`/admin/iam/roles/${role.id}/edit`}>
                            <Button size="small" icon={<Pencil size={16} />}>
                                {t('iam.editRole', 'Edit Role')}
                            </Button>
                        </Link>
                        <IamBreadcrumb
                            segments={[
                                { label: t('iam.roles', 'Roles'), href: '/admin/iam/roles' },
                                { label: role.name },
                            ]}
                        />
                    </Flex>
                </Flex>

                <Row gutter={[24, 24]}>
                    <Col xs={24} lg={8}>
                        <Card>
                            <Flex align="center" gap={12}>
                                <Avatar size={40}>
                                    {role.name.slice(0, 2).toUpperCase()}
                                </Avatar>
                                <Flex vertical>
                                    <Typography.Text strong>{role.name}</Typography.Text>
                                    <Typography.Text type="secondary">{role.slug}</Typography.Text>
                                </Flex>
                            </Flex>
                            {role.description && (
                                <Typography.Paragraph type="secondary">
                                    {role.description}
                                </Typography.Paragraph>
                            )}
                            <Divider />
                            <Descriptions column={1} size="small">
                                <Descriptions.Item label={t('iam.level', 'Level')}>
                                    <Tag>Lv.{role.level}</Tag>
                                </Descriptions.Item>
                                <Descriptions.Item label={t('iam.scope', 'Scope')}>
                                    <Tag>
                                        {role.is_global ? t('iam.global', 'Global') : t('iam.orgScoped', 'Org-scoped')}
                                    </Tag>
                                </Descriptions.Item>
                                <Descriptions.Item label={t('iam.permissions', 'Permissions')}>
                                    {permissions.length}
                                </Descriptions.Item>
                                <Descriptions.Item label={t('iam.assignments', 'Assignments')}>
                                    {assignments.length}
                                </Descriptions.Item>
                                {role.created_at && (
                                    <Descriptions.Item label={t('iam.created', 'Created')}>
                                        {new Date(role.created_at).toLocaleDateString()}
                                    </Descriptions.Item>
                                )}
                            </Descriptions>
                        </Card>
                    </Col>

                    <Col xs={24} lg={16}>
                        <Flex vertical gap={24}>
                            <Card title={t('iam.permissionMatrix', 'Permission Matrix')}>
                                {permissionModules.length === 0 ? (
                                    <Typography.Text type="secondary">
                                        {t('iam.noPermissions', 'No permissions configured.')}
                                    </Typography.Text>
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
                            </Card>

                            <Card title={`${t('iam.whereAssigned', 'Where Assigned')} (${assignments.length})`}>
                                {assignments.length === 0 ? (
                                    <Typography.Text type="secondary">
                                        {t('iam.noAssignments', 'Not assigned to anyone yet.')}
                                    </Typography.Text>
                                ) : (
                                    <Flex vertical gap={16}>
                                        {groupedAssignments.map((group) => (
                                            <Flex vertical key={group.scope} gap={8}>
                                                <Flex align="center" gap={8}>
                                                    <ScopeTypeBadge
                                                        type={toScopeBadgeType(group.scope)}
                                                        label={getScopeLabel(group.scope)}
                                                    />
                                                    <Typography.Text type="secondary">
                                                        ({group.assignments.length})
                                                    </Typography.Text>
                                                </Flex>
                                                <Flex vertical gap={8}>
                                                    {group.assignments.map((assignment, index) => (
                                                        <Link
                                                            key={index}
                                                            href={`/admin/iam/users/${assignment.user.id}`}
                                                        >
                                                            <Card size="small" hoverable>
                                                                <Flex align="center" gap={12}>
                                                                    <Avatar size={32}>
                                                                        {assignment.user.name.slice(0, 2).toUpperCase()}
                                                                    </Avatar>
                                                                    <Flex vertical>
                                                                        <Typography.Text strong ellipsis>
                                                                            {assignment.user.name}
                                                                        </Typography.Text>
                                                                        <Typography.Text type="secondary" ellipsis>
                                                                            {formatScopeLocation(assignment)}
                                                                        </Typography.Text>
                                                                    </Flex>
                                                                </Flex>
                                                            </Card>
                                                        </Link>
                                                    ))}
                                                </Flex>
                                            </Flex>
                                        ))}
                                    </Flex>
                                )}
                            </Card>
                        </Flex>
                    </Col>
                </Row>
            </Flex>
        </Layout>
    );
}
