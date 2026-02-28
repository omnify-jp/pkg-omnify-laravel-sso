import { router } from '@inertiajs/react';
import { App, Button, DatePicker, Drawer, Dropdown, Flex, Form, Input, Select, Switch, Table, Tag, Typography } from 'antd';
import type { TableColumnsType, TableProps } from 'antd';
import { isAxiosError } from 'axios';
import dayjs from 'dayjs';
import { Ellipsis, PlusCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useMutation } from '@tanstack/react-query';
import { PageContainer } from '@omnify-core/components/page-container';
import { api } from '@omnify-core/services/api';
import {
    buildFilterParams,
    FilterAdvancedButton,
    FilterChips,
    FilterDrawer,
    Filters,
    FilterSearch,
} from '@omnify-core/components/filters';
/* ── Types ─────────────────────────────────────────── */

type Organization = {
    id: string;
    name: string;
    slug: string;
    is_active: boolean;
};

type Props = {
    organizations: {
        data: Organization[];
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
    filters: {
        q?: string | null;
        sort?: string | null;
        filter?: Record<string, string> | null;
    };
};

/* ── Helpers ────────────────────────────────────────── */

function parseSortParam(sort: string | null | undefined): { field: string; order: 'ascend' | 'descend' } {
    if (typeof sort !== 'string' || sort === '') return { field: 'name', order: 'ascend' };
    if (sort.startsWith('-')) return { field: sort.slice(1), order: 'descend' };
    return { field: sort, order: 'ascend' };
}

function toSlug(name: string): string {
    return name
        .toLowerCase()
        .trim()
        .replace(/[^\w\s-]/g, '')
        .replace(/[\s_-]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

/* ── Organization Form Drawer ──────────────────────── */

type OrgFormData = {
    name: string;
    slug: string;
    is_active: boolean;
};

type OrgFormDrawerProps = {
    open: boolean;
    onClose: () => void;
    organization: Organization | null;
};

function OrganizationFormDrawer({ open, onClose, organization }: OrgFormDrawerProps) {
    const { t } = useTranslation();
    const { message } = App.useApp();
    const [form] = Form.useForm<OrgFormData>();
    const isEdit = !!organization;

    // Reset form when drawer opens (only when Form is mounted)
    useEffect(() => {
        if (!open) return;
        form.setFieldsValue({
            name: organization?.name ?? '',
            slug: organization?.slug ?? '',
            is_active: organization?.is_active ?? true,
        });
    }, [open, organization, form]);

    const mutation = useMutation({
        mutationFn: (data: OrgFormData) => {
            if (isEdit) {
                return api.put(`/admin/organizations/${organization.id}`, data);
            }
            return api.post('/admin/organizations', data);
        },
        onSuccess: () => {
            message.success(
                isEdit
                    ? t('admin.organizations.updated', 'Organization updated.')
                    : t('admin.organizations.created', 'Organization created.'),
            );
            onClose();
            router.reload();
        },
        onError: (error) => {
            if (isAxiosError(error) && error.response?.status === 422) {
                const serverErrors = error.response.data.errors as Record<string, string[]>;
                form.setFields(
                    Object.entries(serverErrors).map(([key, messages]) => ({
                        name: key as keyof OrgFormData,
                        errors: messages,
                    })),
                );
            }
        },
    });

    const handleNameChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const name = e.target.value;
        if (!isEdit) {
            const currentSlug = form.getFieldValue('slug') as string;
            const currentName = form.getFieldValue('name') as string;
            if (currentSlug === '' || currentSlug === toSlug(currentName)) {
                form.setFieldValue('slug', toSlug(name));
            }
        }
    };

    return (
        <Drawer
            title={isEdit
                ? t('admin.organizations.edit', 'Edit Organization')
                : t('admin.organizations.create', 'Create Organization')}
            placement="right"
            size={480}
            open={open}
            onClose={onClose}
            footer={
                <Flex justify="end" gap={8}>
                    <Button onClick={onClose}>
                        {t('common.cancel', 'Cancel')}
                    </Button>
                    <Button
                        type="primary"
                        loading={mutation.isPending}
                        onClick={() => form.validateFields().then((data) => mutation.mutate(data))}
                    >
                        {isEdit
                            ? t('common.save', 'Save Changes')
                            : t('admin.organizations.createOrg', 'Create Organization')}
                    </Button>
                </Flex>
            }
        >
            <Form form={form} layout="vertical">
                <Form.Item
                    name="name"
                    label={t('admin.organizations.name', 'Name')}
                    rules={[
                        { required: true, message: t('validation.required', 'This field is required.') },
                        { max: 255 },
                    ]}
                >
                    <Input
                        onChange={handleNameChange}
                        placeholder={t('admin.organizations.namePlaceholder', 'e.g. Acme Corporation')}
                    />
                </Form.Item>

                <Form.Item
                    name="slug"
                    label={t('admin.organizations.slug', 'Slug')}
                    rules={[
                        { required: true, message: t('validation.required', 'This field is required.') },
                        { max: 255 },
                    ]}
                    extra={t('admin.organizations.slugDesc', 'URL-friendly identifier. Auto-generated from name.')}
                >
                    <Input placeholder="acme-corporation" />
                </Form.Item>

                <Form.Item
                    name="is_active"
                    label={t('admin.organizations.isActive', 'Active')}
                    valuePropName="checked"
                    initialValue={true}
                >
                    <Switch />
                </Form.Item>
            </Form>
        </Drawer>
    );
}

/* ── Main Page ─────────────────────────────────────── */

export default function AdminOrganizationsIndex({ organizations, filters }: Props) {
    const { t } = useTranslation();
    const currentSort = parseSortParam(filters.sort);

    // Filter drawer state
    const [filterDrawerOpen, setFilterDrawerOpen] = useState(false);
    const [draftFilters, setDraftFilters] = useState<Record<string, string | undefined>>({});

    // Form drawer state
    const [formDrawerOpen, setFormDrawerOpen] = useState(false);
    const [editingOrg, setEditingOrg] = useState<Organization | null>(null);

    const { message, modal } = App.useApp();

    const breadcrumbs = [
        { title: t('nav.dashboard', 'Dashboard'), href: '/dashboard' },
        { title: t('admin.organizations.title', 'Organizations'), href: '/admin/organizations' },
    ];

    const deleteMutation = useMutation({
        mutationFn: (id: string) => api.delete(`/admin/organizations/${id}`),
        onSuccess: () => {
            message.success(t('admin.organizations.deleted', 'Organization deleted.'));
            router.reload();
        },
    });

    const handleDelete = (org: Organization) => {
        modal.confirm({
            title: t('admin.organizations.deleteConfirm', 'Delete this organization?'),
            content: t('admin.organizations.deleteConfirmContent', 'This action cannot be undone.'),
            okText: t('common.delete', 'Delete'),
            okButtonProps: { danger: true },
            onOk: () => deleteMutation.mutateAsync(org.id),
        });
    };

    const handleTableChange: TableProps<Organization>['onChange'] = (pagination, _filters, sorter) => {
        const s = Array.isArray(sorter) ? sorter.find((item) => item.order) : sorter;
        const field = (s?.columnKey ?? s?.field) as string | undefined;
        const order = s?.order;

        let sortParam: string | undefined;
        if (field && order) {
            sortParam = order === 'descend' ? `-${field}` : field;
        }

        const params = buildFilterParams(filters, { set: { sort: sortParam } });
        if (pagination.current && pagination.current > 1) params.page = pagination.current;

        router.get('/admin/organizations', params, { preserveState: true, preserveScroll: true });
    };

    const handleOpenFilterDrawer = () => {
        setDraftFilters({
            is_active: filters.filter?.is_active ?? undefined,
            created_at_from: filters.filter?.created_at_from ?? undefined,
            created_at_to: filters.filter?.created_at_to ?? undefined,
        });
        setFilterDrawerOpen(true);
    };

    const handleFilterApply = () => {
        router.get(
            '/admin/organizations',
            buildFilterParams(filters, { setAdvanced: draftFilters }),
            { preserveState: true, preserveScroll: true },
        );
        setFilterDrawerOpen(false);
    };

    const handleFilterReset = () => {
        router.get(
            '/admin/organizations',
            buildFilterParams(filters, { setAdvanced: {} }),
            { preserveState: true, preserveScroll: true },
        );
        setFilterDrawerOpen(false);
    };

    const handleCreate = () => {
        setEditingOrg(null);
        setFormDrawerOpen(true);
    };

    const handleEdit = (org: Organization) => {
        setEditingOrg(org);
        setFormDrawerOpen(true);
    };

    const columns: TableColumnsType<Organization> = [
        {
            title: t('admin.organizations.name', 'Name'),
            dataIndex: 'name',
            key: 'name',
            sorter: true,
            sortOrder: currentSort.field === 'name' ? currentSort.order : null,
        },
        {
            title: t('admin.organizations.slug', 'Slug'),
            dataIndex: 'slug',
            key: 'slug',
            sorter: true,
            sortOrder: currentSort.field === 'slug' ? currentSort.order : null,
        },
        {
            title: t('admin.organizations.columns.status', 'Status'),
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
                                label: t('common.edit', 'Edit'),
                                onClick: () => handleEdit(record),
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

    const chipLabels: Record<string, string> = {
        is_active: t('admin.organizations.columns.status', 'Status'),
        created_at_from: t('common.from', 'From'),
        created_at_to: t('common.to', 'To'),
    };

    const chipValueLabels: Record<string, Record<string, string>> = {
        is_active: {
            '1': t('common.active', 'Active'),
            '0': t('common.inactive', 'Inactive'),
        },
    };

    return (
        <PageContainer
            title={t('admin.organizations.title', 'Organizations')}
            subtitle={t('admin.organizations.subtitle', 'Manage organizations in standalone mode.')}
            breadcrumbs={breadcrumbs}
        >
            <Filters routeUrl="/admin/organizations" currentFilters={filters}>
                <FilterSearch
                    filterKey="q"
                    placeholder={t('admin.organizations.searchPlaceholder', 'Search by name or slug...')}
                    style={{ maxWidth: 320 }}
                />
                <FilterAdvancedButton
                    label={t('common.filters', 'Filters')}
                    onClick={handleOpenFilterDrawer}
                />
                <div style={{ marginLeft: 'auto' }}>
                    <Button type="primary" icon={<PlusCircle size={16} />} onClick={handleCreate}>
                        {t('admin.organizations.create', 'Create Organization')}
                    </Button>
                </div>
            </Filters>

            <FilterChips
                labels={chipLabels}
                valueLabels={chipValueLabels}
                currentFilters={filters}
                onRemove={(key) => {
                    router.get(
                        '/admin/organizations',
                        buildFilterParams(filters, { removeAdvancedKey: key }),
                        { preserveState: true, preserveScroll: true },
                    );
                }}
            />

            <Table
                dataSource={organizations.data}
                columns={columns}
                rowKey="id"
                onChange={handleTableChange}
                pagination={{
                    current: organizations.meta.current_page,
                    total: organizations.meta.total,
                    pageSize: organizations.meta.per_page,
                }}
            />

            <FilterDrawer
                open={filterDrawerOpen}
                onClose={() => setFilterDrawerOpen(false)}
                title={t('common.advancedFilters', 'Advanced Filters')}
                onApply={handleFilterApply}
                onReset={handleFilterReset}
            >
                <Flex vertical gap={4}>
                    <Typography.Text>{t('admin.organizations.columns.status', 'Status')}</Typography.Text>
                    <Select
                        value={draftFilters.is_active ?? '__all__'}
                        onChange={(v) => setDraftFilters((prev) => ({ ...prev, is_active: v === '__all__' ? undefined : v }))}
                        options={[
                            { value: '__all__', label: t('common.all', 'All') },
                            { value: '1', label: t('common.active', 'Active') },
                            { value: '0', label: t('common.inactive', 'Inactive') },
                        ]}
                        style={{ width: '100%' }}
                    />
                </Flex>
                <Flex vertical gap={4}>
                    <Typography.Text>{t('common.createdFrom', 'Created From')}</Typography.Text>
                    <DatePicker
                        value={draftFilters.created_at_from ? dayjs(draftFilters.created_at_from) : null}
                        onChange={(_, dateStr) => setDraftFilters((prev) => ({ ...prev, created_at_from: (dateStr as string) || undefined }))}
                        style={{ width: '100%' }}
                    />
                </Flex>
                <Flex vertical gap={4}>
                    <Typography.Text>{t('common.createdTo', 'Created To')}</Typography.Text>
                    <DatePicker
                        value={draftFilters.created_at_to ? dayjs(draftFilters.created_at_to) : null}
                        onChange={(_, dateStr) => setDraftFilters((prev) => ({ ...prev, created_at_to: (dateStr as string) || undefined }))}
                        style={{ width: '100%' }}
                    />
                </Flex>
            </FilterDrawer>

            <OrganizationFormDrawer
                open={formDrawerOpen}
                onClose={() => setFormDrawerOpen(false)}
                organization={editingOrg}
            />
        </PageContainer>
    );
}
