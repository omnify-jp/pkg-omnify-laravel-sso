import { Avatar, Button, Card, Flex, Table, Tag, Typography } from 'antd';
import type { TableColumnsType } from 'antd';
import { Link, router, usePage } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { PageContainer } from '@omnify-core/components/page-container';
import { Filters, FilterSearch } from '@omnify-core/components/filters';

import type { IamUser, PaginatedData } from '@omnify-core/types/iam';

type UserRow = IamUser & { roles_count: number };

type Props = {
    users: PaginatedData<UserRow>;
    filters: { search?: string };
};

export default function IamUsers({ users, filters }: Props) {
    const { t } = useTranslation();
    const { url } = usePage();
    const iamBase = url.match(/^(.*\/settings\/iam)/)?.[1] ?? '/settings/iam';

    const breadcrumbs = [
        { title: t('iam.users', 'Users'), href: `${iamBase}/users` },
    ];

    const columns: TableColumnsType<UserRow> = [
        {
            title: t('iam.user', 'User'),
            dataIndex: 'name',
            key: 'user',
            render: (_: unknown, user: UserRow) => (
                <Flex align="center" gap="middle">
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
                <Link href={`${iamBase}/users/${user.id}`}>
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
        >
            <Flex vertical gap="large">
                <Filters routeUrl={`${iamBase}/users`} currentFilters={filters}>
                    <FilterSearch
                        filterKey="search"
                        placeholder={t('iam.searchUsers', 'Search users...')}
                    />
                </Filters>

                <Card styles={{ body: { padding: 0 } }}>
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
                                    `${iamBase}/users`,
                                    { search: filters.search || undefined, page },
                                    { preserveState: true, preserveScroll: true },
                                );
                            },
                        }}
                    />
                </Card>
            </Flex>
        </PageContainer>
    );
}
