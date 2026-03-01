import { Button, Card, Flex, Input, Select, Table, Tag, Typography, theme } from 'antd';
import type { TableColumnsType } from 'antd';
import { Link, router } from '@inertiajs/react';
import { useOrgRoute } from '@omnify-core/hooks/use-org-route';
import { Eye, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageContainer } from '@omnify-core/components/page-container';

import { ScopeTypeBadge } from '@omnify-core/components/access/scope-type-badge';
import type { IamAssignment, ScopeType } from '@omnify-core/types/iam';
import { formatScopeLocation, getScopeLabel, toScopeBadgeType } from '@omnify-core/utils/scope-utils';

type Props = {
    assignments: IamAssignment[];
};

export default function IamAssignments({ assignments }: Props) {
    const { t } = useTranslation();
    const { token } = theme.useToken();
    const orgRoute = useOrgRoute();
    const iamBase = orgRoute('/settings/iam');
    const [search, setSearch] = useState('');
    const [scopeFilter, setScopeFilter] = useState<string>('all');

    const filtered = assignments.filter((a) => {
        const matchesSearch =
            !search ||
            a.user.name.toLowerCase().includes(search.toLowerCase()) ||
            a.role.name.toLowerCase().includes(search.toLowerCase()) ||
            a.user.email.toLowerCase().includes(search.toLowerCase());
        const matchesScope = scopeFilter === 'all' || a.scope_type === scopeFilter;
        return matchesSearch && matchesScope;
    });

    const handleDelete = (assignment: IamAssignment) => {
        if (!confirm(t('iam.confirmDeleteAssignment', 'Remove this assignment?'))) {
            return;
        }
        router.delete(
            `${iamBase}/assignments/${assignment.user.id}/${assignment.role.id}`,
        );
    };

    const columns: TableColumnsType<IamAssignment> = [
        {
            title: t('iam.user', 'User'),
            key: 'user',
            render: (_: unknown, assignment: IamAssignment) => (
                <Flex vertical>
                    <Typography.Text strong>{assignment.user.name}</Typography.Text>
                    <Typography.Text type="secondary">{assignment.user.email}</Typography.Text>
                </Flex>
            ),
        },
        {
            title: t('iam.role', 'Role'),
            key: 'role',
            render: (_: unknown, assignment: IamAssignment) => (
                <Tag>
                    <Typography.Text type="secondary">
                        Lv.{assignment.role.level}
                    </Typography.Text>
                    {' '}{assignment.role.name}
                </Tag>
            ),
        },
        {
            title: t('iam.scopeType', 'Scope Type'),
            key: 'scope_type',
            render: (_: unknown, assignment: IamAssignment) => (
                <ScopeTypeBadge
                    type={toScopeBadgeType(assignment.scope_type as ScopeType)}
                    label={getScopeLabel(assignment.scope_type as ScopeType)}
                />
            ),
        },
        {
            title: t('iam.scopeEntity', 'Scope'),
            key: 'scope_entity',
            render: (_: unknown, assignment: IamAssignment) => (
                <Typography.Text>{formatScopeLocation(assignment)}</Typography.Text>
            ),
        },
        {
            title: t('iam.assignedAt', 'Assigned'),
            dataIndex: 'created_at',
            key: 'assigned_at',
            render: (createdAt: string | null) => (
                <Typography.Text type="secondary">
                    {createdAt ? new Date(createdAt).toLocaleDateString() : '\u2014'}
                </Typography.Text>
            ),
        },
        {
            title: t('iam.actions', 'Actions'),
            key: 'actions',
            width: 96,
            render: (_: unknown, assignment: IamAssignment) => (
                <Flex align="center" gap={token.paddingXXS}>
                    <Link href={`${iamBase}/users/${assignment.user.id}`}>
                        <Button type="text" size="small" icon={<Eye size={16} />} />
                    </Link>
                    <Button
                        type="text"
                        size="small"
                        danger
                        icon={<Trash2 size={16} />}
                        onClick={() => handleDelete(assignment)}
                    />
                </Flex>
            ),
        },
    ];

    return (
        <PageContainer
            title={t('iam.assignments', 'Assignments')}
            subtitle={t('iam.assignmentsSubtitle', 'All scoped role assignments across users.')}
            breadcrumbs={[
                { title: t('iam.assignments', 'Assignments'), href: `${iamBase}/assignments` },
            ]}
        >
            <Flex vertical gap="large">
                <Flex justify="space-between" align="center" wrap="wrap" gap="middle">
                    <Flex gap="middle" wrap="wrap">
                        <Input
                            placeholder={t('iam.searchAssignments', 'Search by user or role\u2026')}
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                        <Select
                            value={scopeFilter}
                            onChange={setScopeFilter}
                            popupMatchSelectWidth={false}
                            options={[
                                { value: 'all', label: t('iam.allScopes', 'All Scopes') },
                                { value: 'global', label: t('iam.global', 'Global') },
                                { value: 'org-wide', label: t('iam.orgWide', 'Organization') },
                                { value: 'branch', label: t('iam.branch', 'Branch') },
                            ]}
                        />
                    </Flex>
                    <Link href={`${iamBase}/assignments/create`}>
                        <Button type="primary" icon={<Plus size={16} />}>
                            {t('iam.createAssignment', 'Create Assignment')}
                        </Button>
                    </Link>
                </Flex>

                <Card styles={{ body: { padding: 0 } }}>
                    <Table
                        columns={columns}
                        dataSource={filtered}
                        rowKey="id"
                        pagination={false}
                        locale={{ emptyText: t('iam.noAssignments', 'No assignments found.') }}
                    />
                </Card>

                <Typography.Text type="secondary">
                    {t('iam.showingCount', 'Showing {{filtered}} of {{total}}', {
                        filtered: filtered.length,
                        total: assignments.length,
                    })}
                </Typography.Text>
            </Flex>
        </PageContainer>
    );
}
