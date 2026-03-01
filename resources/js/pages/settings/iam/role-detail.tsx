import { Avatar, Button, Card, Col, Descriptions, Divider, Empty, Flex, Row, Tag, Typography, theme } from 'antd';
import { PermissionGrid } from '@omnify-core/components/access/permission-grid';
import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Pencil } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { PageContainer } from '@omnify-core/components/page-container';

import { ScopeTypeBadge } from '@omnify-core/components/access/scope-type-badge';
import type { IamPermission, IamRole, IamUserAssignment, ScopeType } from '@omnify-core/types/iam';
import { buildPermissionModules, formatScopeLocation, getScopeLabel, toGridIds, toScopeBadgeType } from '@omnify-core/utils/scope-utils';

type Props = {
    role: IamRole;
    permissions: IamPermission[];
    all_permissions: IamPermission[];
    assignments: IamUserAssignment[];
};

const SCOPE_ORDER: ScopeType[] = ['global', 'org-wide', 'branch'];

export default function IamRoleDetail({ role, permissions, all_permissions, assignments }: Props) {
    const { t } = useTranslation();
    const { token } = theme.useToken();
    const { url } = usePage();
    const iamBase = url.match(/^(.*\/settings\/iam)/)?.[1] ?? '/settings/iam';

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
        <PageContainer
            title={role.name}
            breadcrumbs={[
                { title: t('iam.roles', 'Roles'), href: `${iamBase}/roles` },
                { title: role.name, href: `${iamBase}/roles/${role.id}` },
            ]}
            extra={
                <Flex align="center" gap="small">
                    <Link href={`${iamBase}/roles`}>
                        <Button type="text" size="small" icon={<ArrowLeft size={16} />}>
                            {t('iam.backToRoles', 'Back to Roles')}
                        </Button>
                    </Link>
                    <Link href={`${iamBase}/roles/${role.id}/edit`}>
                        <Button size="small" icon={<Pencil size={16} />}>
                            {t('iam.editRole', 'Edit Role')}
                        </Button>
                    </Link>
                </Flex>
            }
        >
            <Row gutter={[24, 24]}>
                    <Col xs={24} lg={8}>
                        <Card>
                            <Flex align="center" gap="middle">
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
                        <Flex vertical gap="large">
                            <Card title={t('iam.permissionMatrix', 'Permission Matrix')}>
                                {permissionModules.length === 0 ? (
                                    <Empty description={t('iam.noPermissions', 'No permissions configured.')} />
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
                                    <Empty description={t('iam.noAssignments', 'Not assigned to anyone yet.')} />
                                ) : (
                                    <Flex vertical gap={token.padding}>
                                        {groupedAssignments.map((group) => (
                                            <Flex vertical key={group.scope} gap="small">
                                                <Flex align="center" gap="small">
                                                    <ScopeTypeBadge
                                                        type={toScopeBadgeType(group.scope)}
                                                        label={getScopeLabel(group.scope)}
                                                    />
                                                    <Typography.Text type="secondary">
                                                        ({group.assignments.length})
                                                    </Typography.Text>
                                                </Flex>
                                                <Flex vertical gap="small">
                                                    {group.assignments.map((assignment, index) => (
                                                        <Link
                                                            key={index}
                                                            href={`${iamBase}/users/${assignment.user.id}`}
                                                        >
                                                            <Card size="small" hoverable>
                                                                <Flex align="center" gap="middle">
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
        </PageContainer>
    );
}
