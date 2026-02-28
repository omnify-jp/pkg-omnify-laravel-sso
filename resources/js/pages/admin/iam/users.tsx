import { Avatar, Button, Flex, Table, Tag, Typography } from 'antd';
import type { TableColumnsType } from 'antd';
import { Link, router } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { PageContainer } from '@omnify-core/components/page-container';
import { Filters, FilterSearch } from '@omnify-core/components/filters';

import { IamBreadcrumb } from '../../../components/access/iam-breadcrumb';
import type { IamUser, PaginatedData } from '../../../types/iam';

type UserRow = IamUser & { roles_count: number };

type Props = {
    users: PaginatedData<UserRow>;
    filters: { search?: string };
};

export default function IamUsers({ users, filters }: Props) {
    const { t } = useTranslation();

    const breadcrumbs = [
        { title: t('iam.title', 'IAM'), href: '/admin/iam' },
        { title: t('iam.users', 'Users'), href: '/admin/iam/users' },
    ];

    const columns: TableColumnsType<UserRow> = [
        {
            title: t('iam.user', 'User'),
            dataIndex: 'name',
            key: 'user',
            render: (_: unknown, user: UserRow) => (
                <Flex align="center" gap={12}>
                    <Avatar size={32}>
                        {user.name.slice(0, 2).toUpperCase()}
                    </Avatar>
                    <Typography.Text strong>{user.name}</Typography.Text>
                </Flex>
            ),
        },
        {
            title: t('iam.email', 'Email'),
            dataIndex: 'email',
            key: 'email',
            render: (email: string) => (
                <Typography.Text type="secondary">{email}</Typography.Text>
            ),
        },
        {
            title: t('iam.roles', 'Roles'),
            dataIndex: 'roles_count',
            key: 'roles_count',
            align: 'center',
            render: (count: number) => <Tag>{count}</Tag>,
        },
        {
            title: t('iam.joined', 'Joined'),
            dataIndex: 'created_at',
            key: 'joined',
            render: (createdAt: string | null) => (
                <Typography.Text type="secondary">
                    {createdAt ? new Date(createdAt).toLocaleDateString() : '\u2014'}
                </Typography.Text>
            ),
        },
        {
            key: 'actions',
            width: 48,
            align: 'center',
            render: (_: unknown, user: UserRow) => (
                <Link href={`/admin/iam/users/${user.id}`}>
                    <Button type="text" size="small" icon={<Eye size={16} />} />
                </Link>
            ),
        },
    ];

    return (
        <PageContainer
            title={t('iam.users', 'Users')}
            subtitle={t('iam.usersSubtitle', 'Manage user accounts and role assignments.')}
            breadcrumbs={breadcrumbs}
            extra={<IamBreadcrumb segments={[{ label: t('iam.users', 'Users') }]} />}
        >
            <Filters routeUrl="/admin/iam/users" currentFilters={filters}>
                <FilterSearch
                    filterKey="search"
                    placeholder={t('iam.searchUsers', 'Search users...')}
                    style={{ maxWidth: 320 }}
                />
            </Filters>

            <Table
                columns={columns}
                dataSource={users.data}
                rowKey="id"
                locale={{ emptyText: t('iam.noUsersFound', 'No users found.') }}
                pagination={{
                    current: users.meta.current_page,
                    total: users.meta.total,
                    pageSize: users.meta.per_page,
                    onChange: (page) => {
                        router.get(
                            '/admin/iam/users',
                            { search: filters.search || undefined, page },
                            { preserveState: true, preserveScroll: true },
                        );
                    },
                }}
            />
        </PageContainer>
    );
}
