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

type BrandRow = {
    id: string;
    name: string;
    slug: string;
    console_organization_id: string;
    is_active: boolean;
    description: string | null;
};

type OrganizationOption = {
    id: string;
    console_organization_id: string;
    name: string;
    slug: string;
};

type Props = {
    brands: {
        data: BrandRow[];
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

export default function AdminBrandsIndex({ brands, organizations, filters }: Props) {
    const { t } = useTranslation();
    const { message, modal } = App.useApp();
    const currentSort = parseSortParam(filters.sort);

    const breadcrumbs = [
        { title: t('nav.dashboard', 'Dashboard'), href: '/dashboard' },
        { title: t('admin.brands.title', 'Brands'), href: '/admin/brands' },
    ];

    const getOrgName = useCallback(
        (consoleOrgId: string) => {
            const org = organizations.find((o) => o.console_organization_id === consoleOrgId);
            return org?.name ?? consoleOrgId;
        },
        [organizations],
    );

    const deleteMutation = useMutation({
        mutationFn: (id: string) => api.delete(`/admin/brands/${id}`),
        onSuccess: () => {
            message.success(t('admin.brands.deleted', 'Brand deleted.'));
            router.reload();
        },
    });

    const handleDelete = (brand: BrandRow) => {
        modal.confirm({
            title: t('admin.brands.deleteConfirm', 'Delete this brand?'),
            content: t('admin.brands.deleteConfirmContent', 'This action cannot be undone.'),
            okText: t('common.delete', 'Delete'),
            okButtonProps: { danger: true },
            onOk: () => deleteMutation.mutateAsync(brand.id),
        });
    };

    const handleTableChange: TableProps<BrandRow>['onChange'] = (pagination, _filters, sorter) => {
        const s = Array.isArray(sorter) ? sorter.find((item) => item.order) : sorter;
        const field = (s?.columnKey ?? s?.field) as string | undefined;
        const order = s?.order;

        let sortParam: string | undefined;
        if (field && order) {
            sortParam = order === 'descend' ? `-${field}` : field;
        }

        const params = buildFilterParams(filters, { set: { sort: sortParam } });
        if (pagination.current && pagination.current > 1) params.page = pagination.current;

        router.get('/admin/brands', params, { preserveState: true, preserveScroll: true });
    };

    const columns: TableColumnsType<BrandRow> = [
        {
            title: t('admin.brands.name', 'Name'),
            dataIndex: 'name',
            key: 'name',
            sorter: true,
            sortOrder: currentSort.field === 'name' ? currentSort.order : null,
        },
        {
            title: t('admin.brands.slug', 'Slug'),
            dataIndex: 'slug',
            key: 'slug',
            sorter: true,
            sortOrder: currentSort.field === 'slug' ? currentSort.order : null,
        },
        {
            title: t('admin.brands.organization', 'Organization'),
            key: 'console_organization_id',
            render: (_, record) => getOrgName(record.console_organization_id),
        },
        {
            title: t('admin.brands.columns.status', 'Status'),
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
                                    <Link href={`/admin/brands/${record.id}/edit`}>
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
            title={t('admin.brands.title', 'Brands')}
            subtitle={t('admin.brands.subtitle', 'Manage brands across organizations.')}
            breadcrumbs={breadcrumbs}
        >
            <Filters
                routeUrl="/admin/brands"
                currentFilters={filters}
                extra={
                    <Link href="/admin/brands/create">
                        <Button type="primary" icon={<PlusCircle size={16} />}>
                            {t('admin.brands.create', 'Create Brand')}
                        </Button>
                    </Link>
                }
            >
                <FilterSearch
                    filterKey="search"
                    placeholder={t('admin.brands.searchPlaceholder', 'Search by name or slug...')}
                />
                <FilterSelect
                    filterKey="organization_id"
                    options={organizations.map((org) => ({ value: org.id, label: org.name }))}
                    allLabel={t('admin.brands.allOrganizations', 'All organizations')}
                />
            </Filters>

            <Table
                dataSource={brands.data}
                columns={columns}
                rowKey="id"
                onChange={handleTableChange}
                pagination={{
                    current: brands.meta.current_page,
                    total: brands.meta.total,
                    pageSize: brands.meta.per_page,
                }}
            />
        </PageContainer>
    );
}
