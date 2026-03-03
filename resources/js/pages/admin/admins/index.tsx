import { router, usePage } from '@inertiajs/react';
import { App, Button, Drawer, Dropdown, Flex, Form, Input, Switch, Table, Tag } from 'antd';
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
    Filters,
    FilterSearch,
} from '@omnify-core/components/filters';

/* ── Types ─────────────────────────────────────────── */

type AdminRow = {
    id: string;
    name: string;
    email: string;
    is_active: boolean;
    created_at: string;
};

type AdminFormData = {
    name: string;
    email: string;
    password: string;
    is_active: boolean;
};

type Props = {
    admins: {
        data: AdminRow[];
        meta: { current_page: number; last_page: number; per_page: number; total: number };
    };
    filters: { q?: string | null; sort?: string | null };
};

/* ── Helpers ────────────────────────────────────────── */

function parseSortParam(sort: string | null | undefined): { field: string; order: 'ascend' | 'descend' } {
    if (typeof sort !== 'string' || sort === '') return { field: 'name', order: 'ascend' };
    if (sort.startsWith('-')) return { field: sort.slice(1), order: 'descend' };
    return { field: sort, order: 'ascend' };
}

/* ── Admin Form Drawer ──────────────────────────────── */

type AdminFormDrawerProps = {
    open: boolean;
    onClose: () => void;
    admin: AdminRow | null;
};

function AdminFormDrawer({ open, onClose, admin }: AdminFormDrawerProps) {
    const { t } = useTranslation();
    const { message } = App.useApp();
    const [form] = Form.useForm<AdminFormData>();
    const isEdit = !!admin;

    useEffect(() => {
        if (!open) return;
        form.setFieldsValue({
            name: admin?.name ?? '',
            email: admin?.email ?? '',
            password: '',
            is_active: admin?.is_active ?? true,
        });
    }, [open, admin, form]);

    const mutation = useMutation({
        mutationFn: (data: AdminFormData) => {
            if (isEdit) {
                return api.put(`/admin/admins/${admin.id}`, data);
            }
            return api.post('/admin/admins', data);
        },
        onSuccess: () => {
            message.success(
                isEdit
                    ? t('admin.admins.updated', 'Administrator updated.')
                    : t('admin.admins.created', 'Administrator created.'),
            );
            onClose();
            router.reload();
        },
        onError: (error) => {
            if (isAxiosError(error) && error.response?.status === 422) {
                const serverErrors = error.response.data.errors as Record<string, string[]>;
                form.setFields(
                    Object.entries(serverErrors).map(([key, messages]) => ({
                        name: key as keyof AdminFormData,
                        errors: messages,
                    })),
                );
            }
        },
    });

    return (
        <Drawer
            title={
                isEdit
                    ? t('admin.admins.edit', 'Edit Administrator')
                    : t('admin.admins.create', 'Create Administrator')
            }
            placement="right"
            width={480}
            open={open}
            onClose={onClose}
            footer={
                <Flex justify="end" gap="small">
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
                            : t('admin.admins.createAdmin', 'Create Administrator')}
                    </Button>
                </Flex>
            }
        >
            <Form form={form} layout="vertical">
                <Form.Item
                    name="name"
                    label={t('admin.admins.name', 'Name')}
                    rules={[
                        { required: true, message: t('validation.required', 'This field is required.') },
                        { max: 255 },
                    ]}
                >
                    <Input placeholder={t('admin.admins.namePlaceholder', 'e.g. Jane Smith')} />
                </Form.Item>

                <Form.Item
                    name="email"
                    label={t('admin.admins.email', 'Email')}
                    rules={[
                        { required: true, message: t('validation.required', 'This field is required.') },
                        { type: 'email', message: t('validation.email', 'Please enter a valid email address.') },
                        { max: 255 },
                    ]}
                >
                    <Input
                        type="email"
                        placeholder={t('admin.admins.emailPlaceholder', 'admin@example.com')}
                        autoComplete="email"
                    />
                </Form.Item>

                <Form.Item
                    name="password"
                    label={t('admin.admins.password', 'Password')}
                    rules={
                        isEdit
                            ? [{ min: 8, message: t('validation.min.string', 'Minimum 8 characters.') }]
                            : [
                                { required: true, message: t('validation.required', 'This field is required.') },
                                { min: 8, message: t('validation.min.string', 'Minimum 8 characters.') },
                              ]
                    }
                >
                    <Input.Password
                        placeholder={
                            isEdit
                                ? t('admin.admins.passwordEditPlaceholder', 'Leave blank to keep current')
                                : t('admin.admins.passwordPlaceholder', 'Minimum 8 characters')
                        }
                        autoComplete="new-password"
                    />
                </Form.Item>

                <Form.Item
                    name="is_active"
                    label={t('admin.admins.isActive', 'Active')}
                    valuePropName="checked"
                >
                    <Switch />
                </Form.Item>
            </Form>
        </Drawer>
    );
}

/* ── Main Page ─────────────────────────────────────── */

export default function AdminAdminsIndex({ admins, filters }: Props) {
    const { t } = useTranslation();
    const currentSort = parseSortParam(filters.sort);

    const [formDrawerOpen, setFormDrawerOpen] = useState(false);
    const [editingAdmin, setEditingAdmin] = useState<AdminRow | null>(null);

    const { message, modal } = App.useApp();

    const { auth } = usePage<{ auth: { user: { id: string } | null } }>().props;
    const currentAdminId = auth.user?.id;

    const breadcrumbs = [
        { title: t('nav.dashboard', 'Dashboard'), href: '/admin' },
        { title: t('admin.admins.title', 'Administrators'), href: '/admin/admins' },
    ];

    const deleteMutation = useMutation({
        mutationFn: (id: string) => api.delete(`/admin/admins/${id}`),
        onSuccess: () => {
            message.success(t('admin.admins.deleted', 'Administrator deleted.'));
            router.reload();
        },
    });

    const handleDelete = (record: AdminRow) => {
        modal.confirm({
            title: t('admin.admins.deleteConfirm', 'Delete this administrator?'),
            content: t('admin.admins.deleteConfirmContent', 'This action cannot be undone.'),
            okText: t('common.delete', 'Delete'),
            okButtonProps: { danger: true },
            onOk: () => deleteMutation.mutateAsync(record.id),
        });
    };

    const handleTableChange: TableProps<AdminRow>['onChange'] = (pagination, _filters, sorter) => {
        const s = Array.isArray(sorter) ? sorter.find((item) => item.order) : sorter;
        const field = (s?.columnKey ?? s?.field) as string | undefined;
        const order = s?.order;

        let sortParam: string | undefined;
        if (field && order) {
            sortParam = order === 'descend' ? `-${field}` : field;
        }

        const params = buildFilterParams(filters, { set: { sort: sortParam } });
        if (pagination.current && pagination.current > 1) params.page = pagination.current;

        router.get('/admin/admins', params, { preserveState: true, preserveScroll: true });
    };

    const handleCreate = () => {
        setEditingAdmin(null);
        setFormDrawerOpen(true);
    };

    const handleEdit = (record: AdminRow) => {
        setEditingAdmin(record);
        setFormDrawerOpen(true);
    };

    const columns: TableColumnsType<AdminRow> = [
        {
            title: t('admin.admins.columns.name', 'Name'),
            dataIndex: 'name',
            key: 'name',
            sorter: true,
            sortOrder: currentSort.field === 'name' ? currentSort.order : null,
        },
        {
            title: t('admin.admins.columns.email', 'Email'),
            dataIndex: 'email',
            key: 'email',
            sorter: true,
            sortOrder: currentSort.field === 'email' ? currentSort.order : null,
        },
        {
            title: t('admin.admins.columns.status', 'Status'),
            key: 'is_active',
            render: (_, record) => (
                <Tag color={record.is_active ? 'blue' : undefined}>
                    {record.is_active
                        ? t('common.active', 'Active')
                        : t('common.inactive', 'Inactive')}
                </Tag>
            ),
        },
        {
            title: t('admin.admins.columns.createdAt', 'Created'),
            dataIndex: 'created_at',
            key: 'created_at',
            sorter: true,
            sortOrder: currentSort.field === 'created_at' ? currentSort.order : null,
            render: (value: string) => dayjs(value).format('YYYY-MM-DD'),
        },
        {
            key: 'actions',
            width: 48,
            align: 'center',
            render: (_, record) => {
                const isSelf = record.id === currentAdminId;

                return (
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
                                    disabled: isSelf,
                                    onClick: () => !isSelf && handleDelete(record),
                                },
                            ],
                        }}
                    >
                        <Button type="text" size="small" icon={<Ellipsis size={16} />} />
                    </Dropdown>
                );
            },
        },
    ];

    return (
        <PageContainer
            title={t('admin.admins.title', 'Administrators')}
            subtitle={t('admin.admins.subtitle', 'Manage administrator accounts.')}
            breadcrumbs={breadcrumbs}
        >
            <Filters
                routeUrl="/admin/admins"
                currentFilters={filters}
                extra={
                    <Button type="primary" icon={<PlusCircle size={16} />} onClick={handleCreate}>
                        {t('admin.admins.createButton', 'Create Admin')}
                    </Button>
                }
            >
                <FilterSearch
                    filterKey="q"
                    placeholder={t('admin.admins.searchPlaceholder', 'Search by name or email...')}
                />
            </Filters>

            <Table
                dataSource={admins.data}
                columns={columns}
                rowKey="id"
                onChange={handleTableChange}
                pagination={{
                    current: admins.meta.current_page,
                    total: admins.meta.total,
                    pageSize: admins.meta.per_page,
                }}
            />

            <AdminFormDrawer
                open={formDrawerOpen}
                onClose={() => setFormDrawerOpen(false)}
                admin={editingAdmin}
            />
        </PageContainer>
    );
}
