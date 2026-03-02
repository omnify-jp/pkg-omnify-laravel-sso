import { router } from '@inertiajs/react';
import { App, Button, Card, Descriptions, Drawer, Dropdown, Flex, Form, Input, Select, Switch, Table, Tabs, Tag } from 'antd';
import type { TableColumnsType } from 'antd';
import { isAxiosError } from 'axios';
import { Ellipsis, PlusCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useMutation } from '@tanstack/react-query';
import { PageContainer } from '@omnify-core/components/page-container';
import { api } from '@omnify-core/services/api';

/* ── Types ─────────────────────────────────────────── */

type Organization = {
    id: string;
    name: string;
    slug: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
};

type Branch = {
    id: string;
    name: string;
    slug: string;
    is_headquarters: boolean;
    is_active: boolean;
};

type Location = {
    id: string;
    name: string;
    code: string;
    type: string;
    branch_id: string;
    branch: { id: string; name: string } | null;
    is_active: boolean;
};

type UserRow = {
    id: string;
    name: string;
    email: string;
    role_name: string | null;
};

type PaginatedResponse<T> = {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
};

type Props = {
    organization: Organization;
    branches: PaginatedResponse<Branch>;
    locations: PaginatedResponse<Location>;
    users: PaginatedResponse<UserRow>;
    tab: string;
    filters: {
        branches_q?: string;
        branches_sort?: string;
        locations_q?: string;
        locations_sort?: string;
        users_q?: string;
        users_sort?: string;
        branch_id?: string | null;
    };
};

/* ── Constants ─────────────────────────────────────── */

const LOCATION_TYPES = [
    { value: 'office', label: 'Office' },
    { value: 'warehouse', label: 'Warehouse' },
    { value: 'factory', label: 'Factory' },
    { value: 'store', label: 'Store' },
    { value: 'clinic', label: 'Clinic' },
    { value: 'restaurant', label: 'Restaurant' },
    { value: 'other', label: 'Other' },
];

/* ── Helpers ────────────────────────────────────────── */

function toSlug(name: string): string {
    return name
        .toLowerCase()
        .trim()
        .replace(/[^\w\s-]/g, '')
        .replace(/[\s_-]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

/* ── Organization Edit Drawer ──────────────────────── */

type OrgFormData = {
    name: string;
    slug: string;
    is_active: boolean;
};

type OrgEditDrawerProps = {
    open: boolean;
    onClose: () => void;
    organization: Organization;
};

function OrgEditDrawer({ open, onClose, organization }: OrgEditDrawerProps) {
    const { t } = useTranslation();
    const { message } = App.useApp();
    const [form] = Form.useForm<OrgFormData>();

    useEffect(() => {
        if (!open) return;
        form.setFieldsValue({
            name: organization.name,
            slug: organization.slug,
            is_active: organization.is_active,
        });
    }, [open, organization, form]);

    const mutation = useMutation({
        mutationFn: (data: OrgFormData) => api.put(`/admin/organizations/${organization.slug}`, data),
        onSuccess: () => {
            message.success(t('admin.organizations.updated', 'Organization updated.'));
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
        const currentSlug = form.getFieldValue('slug') as string;
        const currentName = form.getFieldValue('name') as string;
        if (currentSlug === '' || currentSlug === toSlug(currentName)) {
            form.setFieldValue('slug', toSlug(name));
        }
    };

    return (
        <Drawer
            title={t('admin.organizations.edit', 'Edit Organization')}
            placement="right"
            size={480}
            open={open}
            onClose={onClose}
            footer={
                <Flex justify="end" gap="small">
                    <Button onClick={onClose}>{t('common.cancel', 'Cancel')}</Button>
                    <Button
                        type="primary"
                        loading={mutation.isPending}
                        onClick={() => form.validateFields().then((data) => mutation.mutate(data))}
                    >
                        {t('common.save', 'Save Changes')}
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
                >
                    <Switch />
                </Form.Item>
            </Form>
        </Drawer>
    );
}

/* ── Branch Form Drawer ────────────────────────────── */

type BranchFormData = {
    name: string;
    slug: string;
    is_headquarters: boolean;
    is_active: boolean;
};

type BranchFormDrawerProps = {
    open: boolean;
    onClose: () => void;
    branch: Branch | null;
    organizationId: string;
};

function BranchFormDrawer({ open, onClose, branch, organizationId }: BranchFormDrawerProps) {
    const { t } = useTranslation();
    const { message } = App.useApp();
    const [form] = Form.useForm<BranchFormData>();
    const isEdit = !!branch;

    useEffect(() => {
        if (!open) return;
        form.setFieldsValue({
            name: branch?.name ?? '',
            slug: branch?.slug ?? '',
            is_headquarters: branch?.is_headquarters ?? false,
            is_active: branch?.is_active ?? true,
        });
    }, [open, branch, form]);

    const mutation = useMutation({
        mutationFn: (data: BranchFormData) => {
            if (isEdit) {
                return api.put(`/admin/branches/${branch.id}`, data);
            }
            return api.post('/admin/branches', { ...data, organization_id: organizationId });
        },
        onSuccess: () => {
            message.success(
                isEdit
                    ? t('admin.branches.updated', 'Branch updated.')
                    : t('admin.branches.created', 'Branch created.'),
            );
            onClose();
            router.reload();
        },
        onError: (error) => {
            if (isAxiosError(error) && error.response?.status === 422) {
                const serverErrors = error.response.data.errors as Record<string, string[]>;
                form.setFields(
                    Object.entries(serverErrors).map(([key, messages]) => ({
                        name: key as keyof BranchFormData,
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
            title={
                isEdit
                    ? t('admin.branches.edit', 'Edit Branch')
                    : t('admin.branches.create', 'Create Branch')
            }
            placement="right"
            size={480}
            open={open}
            onClose={onClose}
            footer={
                <Flex justify="end" gap="small">
                    <Button onClick={onClose}>{t('common.cancel', 'Cancel')}</Button>
                    <Button
                        type="primary"
                        loading={mutation.isPending}
                        onClick={() => form.validateFields().then((data) => mutation.mutate(data))}
                    >
                        {isEdit
                            ? t('common.save', 'Save Changes')
                            : t('admin.branches.createBranch', 'Create Branch')}
                    </Button>
                </Flex>
            }
        >
            <Form form={form} layout="vertical">
                <Form.Item
                    name="name"
                    label={t('admin.branches.name', 'Name')}
                    rules={[
                        { required: true, message: t('validation.required', 'This field is required.') },
                        { max: 255 },
                    ]}
                >
                    <Input
                        onChange={handleNameChange}
                        placeholder={t('admin.branches.namePlaceholder', 'e.g. Tokyo Office')}
                    />
                </Form.Item>

                <Form.Item
                    name="slug"
                    label={t('admin.branches.slug', 'Slug')}
                    rules={[
                        { required: true, message: t('validation.required', 'This field is required.') },
                        { max: 255 },
                    ]}
                    extra={t('admin.branches.slugDesc', 'URL-friendly identifier. Auto-generated from name.')}
                >
                    <Input placeholder="tokyo-office" />
                </Form.Item>

                <Form.Item
                    name="is_headquarters"
                    label={t('admin.branches.isHeadquarters', 'Headquarters')}
                    valuePropName="checked"
                    initialValue={false}
                >
                    <Switch />
                </Form.Item>

                <Form.Item
                    name="is_active"
                    label={t('admin.branches.isActive', 'Active')}
                    valuePropName="checked"
                    initialValue={true}
                >
                    <Switch />
                </Form.Item>
            </Form>
        </Drawer>
    );
}

/* ── Location Form Drawer ──────────────────────────── */

type LocationFormData = {
    name: string;
    code: string;
    type: string;
    branch_id: string;
    is_active: boolean;
};

type LocationFormDrawerProps = {
    open: boolean;
    onClose: () => void;
    location: Location | null;
    organizationId: string;
    branches: Branch[];
};

function LocationFormDrawer({ open, onClose, location, organizationId, branches }: LocationFormDrawerProps) {
    const { t } = useTranslation();
    const { message } = App.useApp();
    const [form] = Form.useForm<LocationFormData>();
    const isEdit = !!location;

    useEffect(() => {
        if (!open) return;
        form.setFieldsValue({
            name: location?.name ?? '',
            code: location?.code ?? '',
            type: location?.type ?? undefined,
            branch_id: location?.branch_id ?? undefined,
            is_active: location?.is_active ?? true,
        });
    }, [open, location, form]);

    const mutation = useMutation({
        mutationFn: (data: LocationFormData) => {
            if (isEdit) {
                return api.put(`/admin/locations/${location.id}`, data);
            }
            return api.post('/admin/locations', { ...data, organization_id: organizationId });
        },
        onSuccess: () => {
            message.success(
                isEdit
                    ? t('admin.locations.updated', 'Location updated.')
                    : t('admin.locations.created', 'Location created.'),
            );
            onClose();
            router.reload();
        },
        onError: (error) => {
            if (isAxiosError(error) && error.response?.status === 422) {
                const serverErrors = error.response.data.errors as Record<string, string[]>;
                form.setFields(
                    Object.entries(serverErrors).map(([key, messages]) => ({
                        name: key as keyof LocationFormData,
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
                    ? t('admin.locations.edit', 'Edit Location')
                    : t('admin.locations.create', 'Create Location')
            }
            placement="right"
            size={480}
            open={open}
            onClose={onClose}
            footer={
                <Flex justify="end" gap="small">
                    <Button onClick={onClose}>{t('common.cancel', 'Cancel')}</Button>
                    <Button
                        type="primary"
                        loading={mutation.isPending}
                        onClick={() => form.validateFields().then((data) => mutation.mutate(data))}
                    >
                        {isEdit
                            ? t('common.save', 'Save Changes')
                            : t('admin.locations.createLocation', 'Create Location')}
                    </Button>
                </Flex>
            }
        >
            <Form form={form} layout="vertical">
                <Form.Item
                    name="name"
                    label={t('admin.locations.name', 'Name')}
                    rules={[
                        { required: true, message: t('validation.required', 'This field is required.') },
                        { max: 255 },
                    ]}
                >
                    <Input placeholder={t('admin.locations.namePlaceholder', 'e.g. Main Office')} />
                </Form.Item>

                <Form.Item
                    name="code"
                    label={t('admin.locations.code', 'Code')}
                    rules={[
                        { required: true, message: t('validation.required', 'This field is required.') },
                        { max: 64 },
                    ]}
                >
                    <Input placeholder={t('admin.locations.codePlaceholder', 'e.g. HQ-001')} />
                </Form.Item>

                <Form.Item
                    name="type"
                    label={t('admin.locations.type', 'Type')}
                    rules={[{ required: true, message: t('validation.required', 'This field is required.') }]}
                >
                    <Select
                        placeholder={t('admin.locations.selectType', 'Select a type...')}
                        options={LOCATION_TYPES}
                    />
                </Form.Item>

                <Form.Item
                    name="branch_id"
                    label={t('admin.locations.branch', 'Branch')}
                    rules={[{ required: true, message: t('validation.required', 'This field is required.') }]}
                >
                    <Select
                        placeholder={t('admin.locations.selectBranch', 'Select a branch...')}
                        options={branches.map((b) => ({ value: b.id, label: b.name }))}
                    />
                </Form.Item>

                <Form.Item
                    name="is_active"
                    label={t('admin.locations.isActive', 'Active')}
                    valuePropName="checked"
                    initialValue={true}
                >
                    <Switch />
                </Form.Item>
            </Form>
        </Drawer>
    );
}

/* ── General Tab ───────────────────────────────────── */

type GeneralTabProps = {
    organization: Organization;
    onEdit: () => void;
};

function GeneralTab({ organization, onEdit }: GeneralTabProps) {
    const { t } = useTranslation();

    return (
        <Card
            title={t('admin.organizations.generalInfo', 'General Information')}
            extra={
                <Button onClick={onEdit}>
                    {t('common.edit', 'Edit')}
                </Button>
            }
        >
            <Descriptions column={1} bordered>
                <Descriptions.Item label={t('admin.organizations.name', 'Name')}>
                    {organization.name}
                </Descriptions.Item>
                <Descriptions.Item label={t('admin.organizations.slug', 'Slug')}>
                    {organization.slug}
                </Descriptions.Item>
                <Descriptions.Item label={t('admin.organizations.columns.status', 'Status')}>
                    <Tag color={organization.is_active ? 'blue' : undefined}>
                        {organization.is_active
                            ? t('common.active', 'Active')
                            : t('common.inactive', 'Inactive')}
                    </Tag>
                </Descriptions.Item>
                <Descriptions.Item label={t('common.createdAt', 'Created At')}>
                    {organization.created_at}
                </Descriptions.Item>
                <Descriptions.Item label={t('common.updatedAt', 'Updated At')}>
                    {organization.updated_at}
                </Descriptions.Item>
            </Descriptions>
        </Card>
    );
}

/* ── Branches Tab ──────────────────────────────────── */

type BranchesTabProps = {
    branches: PaginatedResponse<Branch>;
    organizationId: string;
    organizationSlug: string;
};

function BranchesTab({ branches, organizationId, organizationSlug }: BranchesTabProps) {
    const { t } = useTranslation();
    const { message, modal } = App.useApp();
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editingBranch, setEditingBranch] = useState<Branch | null>(null);

    const deleteMutation = useMutation({
        mutationFn: (id: string) => api.delete(`/admin/branches/${id}`),
        onSuccess: () => {
            message.success(t('admin.branches.deleted', 'Branch deleted.'));
            router.reload();
        },
        onError: () => {
            message.error(t('admin.branches.deleteFailed', 'Failed to delete branch.'));
        },
    });

    const handleCreate = () => {
        setEditingBranch(null);
        setDrawerOpen(true);
    };

    const handleEdit = (branch: Branch) => {
        setEditingBranch(branch);
        setDrawerOpen(true);
    };

    const handleDelete = (branch: Branch) => {
        modal.confirm({
            title: t('admin.branches.deleteConfirm', 'Delete this branch?'),
            content: t('admin.branches.deleteConfirmContent', 'This action cannot be undone.'),
            okText: t('common.delete', 'Delete'),
            okButtonProps: { danger: true },
            onOk: () => deleteMutation.mutateAsync(branch.id),
        });
    };

    const columns: TableColumnsType<Branch> = [
        {
            title: t('admin.branches.name', 'Name'),
            dataIndex: 'name',
            key: 'name',
        },
        {
            title: t('admin.branches.slug', 'Slug'),
            dataIndex: 'slug',
            key: 'slug',
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

    return (
        <>
            <Flex justify="end">
                <Button type="primary" icon={<PlusCircle size={16} />} onClick={handleCreate}>
                    {t('admin.branches.create', 'Create Branch')}
                </Button>
            </Flex>

            <Table
                dataSource={branches.data}
                columns={columns}
                rowKey="id"
                pagination={{
                    current: branches.meta.current_page,
                    total: branches.meta.total,
                    pageSize: branches.meta.per_page,
                    onChange: (page) => {
                        router.get(
                            `/admin/organizations/${organizationSlug}`,
                            { tab: 'branches', branches_page: page },
                            { preserveState: true, preserveScroll: true },
                        );
                    },
                }}
            />

            <BranchFormDrawer
                open={drawerOpen}
                onClose={() => setDrawerOpen(false)}
                branch={editingBranch}
                organizationId={organizationId}
            />
        </>
    );
}

/* ── Locations Tab ─────────────────────────────────── */

type LocationsTabProps = {
    locations: PaginatedResponse<Location>;
    branches: Branch[];
    organizationId: string;
    organizationSlug: string;
    filters: { branch_id?: string | null };
};

function LocationsTab({ locations, branches, organizationId, organizationSlug, filters }: LocationsTabProps) {
    const { t } = useTranslation();
    const { message, modal } = App.useApp();
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editingLocation, setEditingLocation] = useState<Location | null>(null);

    const deleteMutation = useMutation({
        mutationFn: (id: string) => api.delete(`/admin/locations/${id}`),
        onSuccess: () => {
            message.success(t('admin.locations.deleted', 'Location deleted.'));
            router.reload();
        },
        onError: () => {
            message.error(t('admin.locations.deleteFailed', 'Failed to delete location.'));
        },
    });

    const handleCreate = () => {
        setEditingLocation(null);
        setDrawerOpen(true);
    };

    const handleEdit = (location: Location) => {
        setEditingLocation(location);
        setDrawerOpen(true);
    };

    const handleDelete = (location: Location) => {
        modal.confirm({
            title: t('admin.locations.deleteConfirm', 'Delete this location?'),
            content: t('admin.locations.deleteConfirmContent', 'This action cannot be undone.'),
            okText: t('common.delete', 'Delete'),
            okButtonProps: { danger: true },
            onOk: () => deleteMutation.mutateAsync(location.id),
        });
    };

    const handleBranchFilter = (value: string) => {
        router.get(
            `/admin/organizations/${organizationSlug}`,
            { tab: 'locations', branch_id: value === '__all__' ? undefined : value },
            { preserveState: true, preserveScroll: true },
        );
    };

    const columns: TableColumnsType<Location> = [
        {
            title: t('admin.locations.name', 'Name'),
            dataIndex: 'name',
            key: 'name',
        },
        {
            title: t('admin.locations.code', 'Code'),
            dataIndex: 'code',
            key: 'code',
        },
        {
            title: t('admin.locations.type', 'Type'),
            key: 'type',
            render: (_, record) => <Tag>{record.type}</Tag>,
        },
        {
            title: t('admin.locations.branch', 'Branch'),
            key: 'branch_name',
            render: (_, record) => record.branch?.name ?? '—',
        },
        {
            title: t('admin.locations.columns.status', 'Status'),
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

    return (
        <>
            <Flex justify="space-between" align="center">
                <Select
                    value={filters.branch_id ?? '__all__'}
                    onChange={handleBranchFilter}
                    options={[
                        { value: '__all__', label: t('admin.locations.allBranches', 'All branches') },
                        ...branches.map((b) => ({ value: b.id, label: b.name })),
                    ]}
                />
                <Button type="primary" icon={<PlusCircle size={16} />} onClick={handleCreate}>
                    {t('admin.locations.create', 'Create Location')}
                </Button>
            </Flex>

            <Table
                dataSource={locations.data}
                columns={columns}
                rowKey="id"
                pagination={{
                    current: locations.meta.current_page,
                    total: locations.meta.total,
                    pageSize: locations.meta.per_page,
                    onChange: (page) => {
                        router.get(
                            `/admin/organizations/${organizationSlug}`,
                            { tab: 'locations', branch_id: filters.branch_id ?? undefined, locations_page: page },
                            { preserveState: true, preserveScroll: true },
                        );
                    },
                }}
            />

            <LocationFormDrawer
                open={drawerOpen}
                onClose={() => setDrawerOpen(false)}
                location={editingLocation}
                organizationId={organizationId}
                branches={branches}
            />
        </>
    );
}

/* ── Users Tab ─────────────────────────────────────── */

type UsersTabProps = {
    users: PaginatedResponse<UserRow>;
    organizationSlug: string;
};

function UsersTab({ users, organizationSlug }: UsersTabProps) {
    const { t } = useTranslation();

    const columns: TableColumnsType<UserRow> = [
        {
            title: t('admin.users.name', 'Name'),
            dataIndex: 'name',
            key: 'name',
        },
        {
            title: t('admin.users.email', 'Email'),
            dataIndex: 'email',
            key: 'email',
        },
        {
            title: t('admin.users.role', 'Role'),
            key: 'role_name',
            render: (_, record) => record.role_name ? <Tag>{record.role_name}</Tag> : '—',
        },
    ];

    return (
        <Table
            dataSource={users.data}
            columns={columns}
            rowKey="id"
            pagination={{
                current: users.meta.current_page,
                total: users.meta.total,
                pageSize: users.meta.per_page,
                onChange: (page) => {
                    router.get(
                        `/admin/organizations/${organizationSlug}`,
                        { tab: 'users', users_page: page },
                        { preserveState: true, preserveScroll: true },
                    );
                },
            }}
        />
    );
}

/* ── Main Page ─────────────────────────────────────── */

export default function AdminOrganizationShow({ organization, branches, locations, users, tab, filters }: Props) {
    const { t } = useTranslation();
    const { message, modal } = App.useApp();
    const [orgEditOpen, setOrgEditOpen] = useState(false);

    const breadcrumbs = [
        { title: t('nav.dashboard', 'Dashboard'), href: '/dashboard' },
        { title: t('admin.organizations.title', 'Organizations'), href: '/admin/organizations' },
        { title: organization.name, href: `/admin/organizations/${organization.slug}` },
    ];

    const deleteMutation = useMutation({
        mutationFn: () => api.delete(`/admin/organizations/${organization.slug}`),
        onSuccess: () => {
            message.success(t('admin.organizations.deleted', 'Organization deleted.'));
            router.visit('/admin/organizations');
        },
    });

    const handleDelete = () => {
        modal.confirm({
            title: t('admin.organizations.deleteConfirm', 'Delete this organization?'),
            content: t('admin.organizations.deleteConfirmContent', 'This action cannot be undone.'),
            okText: t('common.delete', 'Delete'),
            okButtonProps: { danger: true },
            onOk: () => deleteMutation.mutateAsync(),
        });
    };

    const handleTabChange = (key: string) => {
        router.get(
            `/admin/organizations/${organization.slug}`,
            { tab: key },
            { preserveState: true },
        );
    };

    const tabItems = [
        {
            key: 'general',
            label: t('admin.organizations.tabs.general', 'General'),
            children: (
                <GeneralTab
                    organization={organization}
                    onEdit={() => setOrgEditOpen(true)}
                />
            ),
        },
        {
            key: 'branches',
            label: t('admin.organizations.tabs.branches', `Branches (${branches.meta.total})`),
            children: (
                <BranchesTab
                    branches={branches}
                    organizationId={organization.id}
                    organizationSlug={organization.slug}
                />
            ),
        },
        {
            key: 'locations',
            label: t('admin.organizations.tabs.locations', `Locations (${locations.meta.total})`),
            children: (
                <LocationsTab
                    locations={locations}
                    branches={branches.data}
                    organizationId={organization.id}
                    organizationSlug={organization.slug}
                    filters={filters}
                />
            ),
        },
        {
            key: 'users',
            label: t('admin.organizations.tabs.users', `Users (${users.meta.total})`),
            children: (
                <UsersTab
                    users={users}
                    organizationSlug={organization.slug}
                />
            ),
        },
    ];

    return (
        <PageContainer
            title={organization.name}
            subtitle={organization.slug}
            breadcrumbs={breadcrumbs}
            extra={
                <Flex gap="small" align="center">
                    <Tag color={organization.is_active ? 'blue' : undefined}>
                        {organization.is_active
                            ? t('common.active', 'Active')
                            : t('common.inactive', 'Inactive')}
                    </Tag>
                    <Button onClick={() => setOrgEditOpen(true)}>
                        {t('common.edit', 'Edit')}
                    </Button>
                    <Button danger loading={deleteMutation.isPending} onClick={handleDelete}>
                        {t('common.delete', 'Delete')}
                    </Button>
                </Flex>
            }
        >
            <Tabs
                activeKey={tab || 'general'}
                onChange={handleTabChange}
                items={tabItems}
            />

            <OrgEditDrawer
                open={orgEditOpen}
                onClose={() => setOrgEditOpen(false)}
                organization={organization}
            />
        </PageContainer>
    );
}
