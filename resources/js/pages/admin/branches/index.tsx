import { Link, router } from '@inertiajs/react';
import { App, Button, Dropdown, Table, Tag } from 'antd';
import type { TableColumnsType, TableProps } from 'antd';
import { Ellipsis, PlusCircle } from 'lucide-react';
import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { useMutation } from '@tanstack/react-query';
import { PageContainer } from '@omnify-core/components/page-container';
import { api } from '@omnify-core/services/api';
import {
    buildFilterParams,
    Filters,
    FilterSearch,
    FilterSelect,
} from '@omnify-core/components/filters';
/* ── Types ─────────────────────────────────────────── */

type BranchRow = {
    id: string;
    name: string;
    slug: string;
    console_organization_id: string;
    is_headquarters: boolean;
    is_active: boolean;
};

type OrganizationOption = {
    id: string;
    console_organization_id: string;
    name: string;
    slug: string;
};

type Props = {
    branches: {
        data: BranchRow[];
        meta: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        };
        links: {
            first: string | null;
            last: string | null;
            prev: string | null;
            next: string | null;
        };
    };
    organizations: OrganizationOption[];
    filters: {
        search?: string;
        organization_id?: string;
        sort?: string | null;
    };
};

/* ── Helpers ────────────────────────────────────────── */

function parseSortParam(sort: string | null | undefined): { field: string; order: 'ascend' | 'descend' } {
    if (typeof sort !== 'string' || sort === '') return { field: 'name', order: 'ascend' };
    if (sort.startsWith('-')) return { field: sort.slice(1), order: 'descend' };
    return { field: sort, order: 'ascend' };
}

/* ── Main Page ─────────────────────────────────────── */

export default function AdminBranchesIndex({ branches, organizations, filters }: Props) {
    const { t } = useTranslation();
    const { message, modal } = App.useApp();
    const currentSort = parseSortParam(filters.sort);

    const breadcrumbs = [
        { title: t('nav.dashboard', 'Dashboard'), href: '/dashboard' },
        { title: t('admin.branches.title', 'Branches'), href: '/admin/branches' },
    ];

    const getOrgName = useCallback(
        (consoleOrgId: string) => {
            const org = organizations.find((o) => o.console_organization_id === consoleOrgId);
            return org?.name ?? consoleOrgId;
        },
        [organizations],
    );

    const deleteMutation = useMutation({
        mutationFn: (id: string) => api.delete(`/admin/branches/${id}`),
        onSuccess: () => {
            message.success(t('admin.branches.deleted', 'Branch deleted.'));
            router.reload();
        },
    });

    const handleDelete = (branch: BranchRow) => {
        modal.confirm({
            title: t('admin.branches.deleteConfirm', 'Delete this branch?'),
            content: t('admin.branches.deleteConfirmContent', 'This action cannot be undone.'),
            okText: t('common.delete', 'Delete'),
            okButtonProps: { danger: true },
            onOk: () => deleteMutation.mutateAsync(branch.id),
        });
    };

    const handleTableChange: TableProps<BranchRow>['onChange'] = (pagination, _filters, sorter) => {
        const s = Array.isArray(sorter) ? sorter.find((item) => item.order) : sorter;
        const field = (s?.columnKey ?? s?.field) as string | undefined;
        const order = s?.order;

        let sortParam: string | undefined;
        if (field && order) {
            sortParam = order === 'descend' ? `-${field}` : field;
        }

        const params = buildFilterParams(filters, { set: { sort: sortParam } });
        if (pagination.current && pagination.current > 1) params.page = pagination.current;

        router.get('/admin/branches', params, { preserveState: true, preserveScroll: true });
    };

    const columns: TableColumnsType<BranchRow> = [
        {
            title: t('admin.branches.name', 'Name'),
            dataIndex: 'name',
            key: 'name',
            sorter: true,
            sortOrder: currentSort.field === 'name' ? currentSort.order : null,
        },
        {
            title: t('admin.branches.slug', 'Slug'),
            dataIndex: 'slug',
            key: 'slug',
            sorter: true,
            sortOrder: currentSort.field === 'slug' ? currentSort.order : null,
        },
        {
            title: t('admin.branches.organization', 'Organization'),
            key: 'console_organization_id',
            render: (_, record) => getOrgName(record.console_organization_id),
        },
        {
            title: t('admin.branches.headquarters', 'HQ'),
            key: 'is_headquarters',
            render: (_, record) =>
                record.is_headquarters ? (
                    <Tag color="blue">{t('common.yes', 'Yes')}</Tag>
                ) : null,
        },
        {
            title: t('admin.branches.columns.status', 'Status'),
            key: 'is_active',
            sorter: true,
            sortOrder: currentSort.field === 'is_active' ? currentSort.order : null,
            render: (_, record) => (
                <Tag color={record.is_active ? 'blue' : undefined}>
                    {record.is_active
                        ? t('common.active', 'Active')
                        : t('common.inactive', 'Inactive')}
                </Tag>
            ),
        },
        {
            key: 'actions',
            width: 48,
            align: 'center',
            render: (_, record) => (
                <Dropdown
                    trigger={['click']}
                    placement="bottomRight"
                    menu={{
                        items: [
                            {
                                key: 'edit',
                                label: (
                                    <Link href={`/admin/branches/${record.id}/edit`}>
                                        {t('common.edit', 'Edit')}
                                    </Link>
                                ),
                            },
                            { type: 'divider' },
                            {
                                key: 'delete',
                                label: t('common.delete', 'Delete'),
                                danger: true,
                                onClick: () => handleDelete(record),
                            },
                        ],
                    }}
                >
                    <Button type="text" size="small" icon={<Ellipsis size={16} />} />
                </Dropdown>
            ),
        },
    ];

    return (
        <PageContainer
            title={t('admin.branches.title', 'Branches')}
            subtitle={t('admin.branches.subtitle', 'Manage branches across organizations.')}
            breadcrumbs={breadcrumbs}
        >
            <Filters
                routeUrl="/admin/branches"
                currentFilters={filters}
                extra={
                    <Link href="/admin/branches/create">
                        <Button type="primary" icon={<PlusCircle size={16} />}>
                            {t('admin.branches.create', 'Create Branch')}
                        </Button>
                    </Link>
                }
            >
                <FilterSearch
                    filterKey="search"
                    placeholder={t('admin.branches.searchPlaceholder', 'Search by name or slug...')}
                />
                <FilterSelect
                    filterKey="organization_id"
                    options={organizations.map((org) => ({ value: org.id, label: org.name }))}
                    allLabel={t('admin.branches.allOrganizations', 'All organizations')}
                />
            </Filters>

            <Table
                dataSource={branches.data}
                columns={columns}
                rowKey="id"
                onChange={handleTableChange}
                pagination={{
                    current: branches.meta.current_page,
                    total: branches.meta.total,
                    pageSize: branches.meta.per_page,
                }}
            />
        </PageContainer>
    );
}
