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

type LocationRow = {
    id: string;
    name: string;
    code: string;
    type: string;
    console_branch_id: string;
    console_organization_id: string;
    is_active: boolean;
    city: string | null;
    country_code: string | null;
};

type BranchOption = {
    id: string;
    console_branch_id: string;
    console_organization_id: string;
    name: string;
    slug: string;
};

type OrganizationOption = {
    id: string;
    console_organization_id: string;
    name: string;
    slug: string;
};

type Props = {
    locations: {
        data: LocationRow[];
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
    branches: BranchOption[];
    organizations: OrganizationOption[];
    filters: {
        search?: string;
        branch_id?: string;
        organization_id?: string;
        type?: string;
        sort?: string | null;
    };
};

/* ── Constants ─────────────────────────────────────── */

const LOCATION_TYPES = [
    'office',
    'warehouse',
    'factory',
    'store',
    'clinic',
    'restaurant',
    'other',
] as const;

/* ── Helpers ────────────────────────────────────────── */

function parseSortParam(sort: string | null | undefined): { field: string; order: 'ascend' | 'descend' } {
    if (typeof sort !== 'string' || sort === '') return { field: 'name', order: 'ascend' };
    if (sort.startsWith('-')) return { field: sort.slice(1), order: 'descend' };
    return { field: sort, order: 'ascend' };
}

/* ── Main Page ─────────────────────────────────────── */

export default function AdminLocationsIndex({ locations, branches, organizations, filters }: Props) {
    const { t } = useTranslation();
    const { message, modal } = App.useApp();
    const currentSort = parseSortParam(filters.sort);

    const breadcrumbs = [
        { title: t('nav.dashboard', 'Dashboard'), href: '/dashboard' },
        { title: t('admin.locations.title', 'Locations'), href: '/admin/locations' },
    ];

    const getBranchName = useCallback(
        (consoleBranchId: string) => {
            const branch = branches.find((b) => b.console_branch_id === consoleBranchId);
            return branch?.name ?? consoleBranchId;
        },
        [branches],
    );

    const getOrgName = useCallback(
        (consoleOrgId: string) => {
            const org = organizations.find((o) => o.console_organization_id === consoleOrgId);
            return org?.name ?? consoleOrgId;
        },
        [organizations],
    );

    const deleteMutation = useMutation({
        mutationFn: (id: string) => api.delete(`/admin/locations/${id}`),
        onSuccess: () => {
            message.success(t('admin.locations.deleted', 'Location deleted.'));
            router.reload();
        },
    });

    const handleDelete = (location: LocationRow) => {
        modal.confirm({
            title: t('admin.locations.deleteConfirm', 'Delete this location?'),
            content: t('admin.locations.deleteConfirmContent', 'This action cannot be undone.'),
            okText: t('common.delete', 'Delete'),
            okButtonProps: { danger: true },
            onOk: () => deleteMutation.mutateAsync(location.id),
        });
    };

    const handleTableChange: TableProps<LocationRow>['onChange'] = (pagination, _filters, sorter) => {
        const s = Array.isArray(sorter) ? sorter.find((item) => item.order) : sorter;
        const field = (s?.columnKey ?? s?.field) as string | undefined;
        const order = s?.order;

        let sortParam: string | undefined;
        if (field && order) {
            sortParam = order === 'descend' ? `-${field}` : field;
        }

        const params = buildFilterParams(filters, { set: { sort: sortParam } });
        if (pagination.current && pagination.current > 1) params.page = pagination.current;

        router.get('/admin/locations', params, { preserveState: true, preserveScroll: true });
    };

    const columns: TableColumnsType<LocationRow> = [
        {
            title: t('admin.locations.name', 'Name'),
            dataIndex: 'name',
            key: 'name',
            sorter: true,
            sortOrder: currentSort.field === 'name' ? currentSort.order : null,
        },
        {
            title: t('admin.locations.code', 'Code'),
            dataIndex: 'code',
            key: 'code',
            sorter: true,
            sortOrder: currentSort.field === 'code' ? currentSort.order : null,
        },
        {
            title: t('admin.locations.type', 'Type'),
            key: 'type',
            render: (_, record) => (
                <Tag>{record.type}</Tag>
            ),
        },
        {
            title: t('admin.locations.branch', 'Branch'),
            key: 'console_branch_id',
            render: (_, record) => getBranchName(record.console_branch_id),
        },
        {
            title: t('admin.locations.organization', 'Organization'),
            key: 'console_organization_id',
            render: (_, record) => getOrgName(record.console_organization_id),
        },
        {
            title: t('admin.locations.city', 'City'),
            dataIndex: 'city',
            key: 'city',
        },
        {
            title: t('admin.locations.columns.status', 'Status'),
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
                    menu={{
                        items: [
                            {
                                key: 'edit',
                                label: (
                                    <Link href={`/admin/locations/${record.id}/edit`}>
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
            title={t('admin.locations.title', 'Locations')}
            subtitle={t('admin.locations.subtitle', 'Manage locations across branches.')}
            breadcrumbs={breadcrumbs}
        >
            <Filters routeUrl="/admin/locations" currentFilters={filters}>
                <FilterSearch
                    filterKey="search"
                    placeholder={t('admin.locations.searchPlaceholder', 'Search by name or code...')}
                    style={{ maxWidth: 320 }}
                />
                <FilterSelect
                    filterKey="branch_id"
                    options={branches.map((branch) => ({ value: branch.id, label: branch.name }))}
                    allLabel={t('admin.locations.allBranches', 'All branches')}
                />
                <FilterSelect
                    filterKey="organization_id"
                    options={organizations.map((org) => ({ value: org.id, label: org.name }))}
                    allLabel={t('admin.locations.allOrganizations', 'All organizations')}
                />
                <FilterSelect
                    filterKey="type"
                    options={LOCATION_TYPES.map((type) => ({ value: type, label: type.charAt(0).toUpperCase() + type.slice(1) }))}
                    allLabel={t('admin.locations.allTypes', 'All types')}
                />
                <div style={{ marginLeft: 'auto' }}>
                    <Link href="/admin/locations/create">
                        <Button type="primary" icon={<PlusCircle size={16} />}>
                            {t('admin.locations.create', 'Create Location')}
                        </Button>
                    </Link>
                </div>
            </Filters>

            <Table
                dataSource={locations.data}
                columns={columns}
                rowKey="id"
                onChange={handleTableChange}
                pagination={{
                    current: locations.meta.current_page,
                    total: locations.meta.total,
                    pageSize: locations.meta.per_page,
                }}
            />
        </PageContainer>
    );
}
