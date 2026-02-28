import { Button, Dropdown, Flex, Input, Table, Tag, Typography } from 'antd';
import type { TableColumnsType } from 'antd';
import { Link } from '@inertiajs/react';
import { Ellipsis, Eye } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageContainer } from '@omnify-core/components/page-container';

import { IamBreadcrumb } from '../../../components/access/iam-breadcrumb';
import type { IamRole } from '../../../types/iam';

type Props = {
    roles: IamRole[];
};

export default function IamRoles({ roles }: Props) {
    const { t } = useTranslation();
    const [search, setSearch] = useState('');

    const breadcrumbs = [
        { title: t('iam.title', 'IAM'), href: '/admin/iam' },
        { title: t('iam.roles', 'Roles'), href: '/admin/iam/roles' },
    ];

    const filtered = useMemo(
        () =>
            roles.filter(
                (r) =>
                    r.name.toLowerCase().includes(search.toLowerCase()) ||
                    r.slug.toLowerCase().includes(search.toLowerCase()),
            ),
        [roles, search],
    );

    const levelLabel = (level: number) => {
        if (level >= 100) return { label: 'Admin', color: 'error' as const };
        if (level >= 50) return { label: 'Manager', color: 'warning' as const };
        if (level >= 10) return { label: 'Member', color: 'processing' as const };
        return { label: 'Viewer', color: 'default' as const };
    };

    const columns: TableColumnsType<IamRole> = [
        {
            title: t('iam.roleName', 'Role Name'),
            dataIndex: 'name',
            key: 'name',
            render: (_: unknown, role: IamRole) => (
                <Flex vertical>
                    <Typography.Text strong>{role.name}</Typography.Text>
                    <Typography.Text type="secondary">{role.slug}</Typography.Text>
                </Flex>
            ),
        },
        {
            title: t('iam.level', 'Level'),
            dataIndex: 'level',
            key: 'level',
            render: (_: unknown, role: IamRole) => {
                const { label, color } = levelLabel(role.level);
                return (
                    <Tag color={color}>
                        Lv.{role.level} &middot; {label}
                    </Tag>
                );
            },
        },
        {
            title: t('iam.description', 'Description'),
            dataIndex: 'description',
            key: 'description',
            render: (description: string | null) => (
                <Typography.Text type="secondary" ellipsis>
                    {description ?? '\u2014'}
                </Typography.Text>
            ),
        },
        {
            title: t('iam.permissions', 'Permissions'),
            dataIndex: 'permissions_count',
            key: 'permissions_count',
            align: 'center',
            render: (count: number | null) => count ?? '\u2014',
        },
        {
            title: t('iam.assignments', 'Assignments'),
            dataIndex: 'assignments_count',
            key: 'assignments_count',
            align: 'center',
            render: (count: number | null) => count ?? '\u2014',
        },
        {
            title: t('iam.scope', 'Scope'),
            dataIndex: 'is_global',
            key: 'scope',
            render: (isGlobal: boolean) => (
                <Tag>
                    {isGlobal ? t('iam.global', 'Global') : t('iam.orgScoped', 'Org-scoped')}
                </Tag>
            ),
        },
        {
            key: 'actions',
            width: 48,
            align: 'center',
            render: (_: unknown, role: IamRole) => (
                <Dropdown
                    placement="bottomRight"
                    menu={{
                        items: [
                            {
                                key: 'view',
                                icon: <Eye size={16} />,
                                label: (
                                    <Link href={`/admin/iam/roles/${role.id}`}>
                                        {t('common.view', 'View')}
                                    </Link>
                                ),
                            },
                        ],
                    }}
                    trigger={['click']}
                >
                    <Button type="text" size="small" icon={<Ellipsis size={16} />} />
                </Dropdown>
            ),
        },
    ];

    return (
        <PageContainer
            title={t('iam.roles', 'Roles')}
            subtitle={t('iam.rolesSubtitle', 'Define roles and their permissions.')}
            breadcrumbs={breadcrumbs}
            extra={<IamBreadcrumb segments={[{ label: t('iam.roles', 'Roles') }]} />}
        >
            <Input.Search
                placeholder={t('iam.searchRoles', 'Search roles...')}
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                style={{ maxWidth: 320 }}
            />

            <Table
                columns={columns}
                dataSource={filtered}
                rowKey="id"
                pagination={false}
                locale={{ emptyText: t('iam.noRolesFound', 'No roles found.') }}
            />

            <Typography.Text type="secondary">
                {t('iam.showingRoles', 'Showing {{filtered}} of {{total}} roles', {
                    filtered: filtered.length,
                    total: roles.length,
                })}
            </Typography.Text>
        </PageContainer>
    );
}
