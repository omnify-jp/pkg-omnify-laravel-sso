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
    is_active: boolean;
    role_id: string;
    role_name: string;
    role_slug: string;
    console_branch_id: string | null;
    branch_name: string | null;
    scope_type: 'org-wide' | 'branch';
};

type RoleOption = {
    id: string;
    name: string;
    slug: string;
    level: number;
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
    roles: RoleOption[];
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

/* ── User Form Drawer ──────────────────────────────── */

type UserSearchResult = {
    id: string;
    name: string;
    email: string;
    already_in_org: boolean;
} | null;

type UserFormData = {
    email: string;
    name?: string;
    role_id: string;
    console_branch_id: string | null;
};

type UserFormDrawerProps = {
    open: boolean;
    onClose: () => void;
    organizationSlug: string;
    editingUser: UserRow | null;
    roles: RoleOption[];
    branches: Branch[];
};

function UserFormDrawer({ open, onClose, organizationSlug, editingUser, roles, branches }: UserFormDrawerProps) {
    const { t } = useTranslation();
    const { message } = App.useApp();
    const [form] = Form.useForm<UserFormData>();
    const isEdit = !!editingUser;

    const [searchEmail, setSearchEmail] = useState('');
    const [searchResult, setSearchResult] = useState<UserSearchResult>(null);
    const [hasSearched, setHasSearched] = useState(false);

    useEffect(() => {
        if (!open) return;
        form.resetFields();
        setSearchEmail('');
        setSearchResult(null);
        setHasSearched(false);

        if (isEdit && editingUser) {
            form.setFieldsValue({
                role_id: editingUser.role_id,
                console_branch_id: editingUser.console_branch_id ?? null,
            });
        }
    }, [open, editingUser, isEdit, form]);

    const searchMutation = useMutation({
        mutationFn: (email: string) =>
            api.post<{ user: UserSearchResult }>(`/admin/organizations/${organizationSlug}/users/search`, { email }),
        onSuccess: (response) => {
            setSearchResult(response.data.user);
            setHasSearched(true);
        },
        onError: () => {
            message.error(t('admin.users.searchFailed', 'Failed to search user.'));
        },
    });

    const storeMutation = useMutation({
        mutationFn: (data: UserFormData) =>
            api.post(`/admin/organizations/${organizationSlug}/users`, data),
        onSuccess: () => {
            message.success(t('admin.users.added', 'User added to organization.'));
            onClose();
            router.reload();
        },
        onError: (error) => {
            if (isAxiosError(error) && error.response?.status === 422) {
                const serverErrors = error.response.data.errors as Record<string, string[]>;
                form.setFields(
                    Object.entries(serverErrors).map(([key, messages]) => ({
                        name: key as keyof UserFormData,
                        errors: messages,
                    })),
                );
            } else {
                message.error(t('admin.users.addFailed', 'Failed to add user.'));
            }
        },
    });

    const updateMutation = useMutation({
        mutationFn: (data: Omit<UserFormData, 'email' | 'name'>) =>
            api.put(`/admin/organizations/${organizationSlug}/users/${editingUser!.id}`, data),
        onSuccess: () => {
            message.success(t('admin.users.updated', 'Role assignment updated.'));
            onClose();
            router.reload();
        },
        onError: (error) => {
            if (isAxiosError(error) && error.response?.status === 422) {
                const serverErrors = error.response.data.errors as Record<string, string[]>;
                form.setFields(
                    Object.entries(serverErrors).map(([key, messages]) => ({
                        name: key as keyof UserFormData,
                        errors: messages,
                    })),
                );
            } else {
                message.error(t('admin.users.updateFailed', 'Failed to update role assignment.'));
            }
        },
    });

    const handleSearch = (value: string) => {
        const email = value.trim();
        if (!email) return;
        setSearchEmail(email);
        searchMutation.mutate(email);
    };

    const handleSubmit = () => {
        form.validateFields().then((data) => {
            if (isEdit) {
                updateMutation.mutate({
                    role_id: data.role_id,
                    console_branch_id: data.console_branch_id,
                });
            } else {
                storeMutation.mutate({
                    email: searchEmail,
                    name: data.name,
                    role_id: data.role_id,
                    console_branch_id: data.console_branch_id,
                });
            }
        });
    };

    const isPending = storeMutation.isPending || updateMutation.isPending;
    const userFoundButAlreadyInOrg = hasSearched && searchResult && searchResult.already_in_org;
    const userFoundNotInOrg = hasSearched && searchResult && !searchResult.already_in_org;
    const userNotFound = hasSearched && !searchResult;

    const ORG_WIDE_VALUE = '__org_wide__';

    const branchOptions = [
        { value: ORG_WIDE_VALUE, label: t('admin.users.allBranches', 'All branches (org-wide)') },
        ...branches.map((b) => ({ value: b.id, label: b.name })),
    ];

    return (
        <Drawer
            title={
                isEdit
                    ? t('admin.users.editRole', 'Edit Role Assignment')
                    : t('admin.users.addUser', 'Add User to Organization')
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
                        loading={isPending}
                        onClick={handleSubmit}
                        disabled={!isEdit && (!hasSearched || !!userFoundButAlreadyInOrg)}
                    >
                        {isEdit
                            ? t('common.save', 'Save Changes')
                            : t('admin.users.addUser', 'Add User')}
                    </Button>
                </Flex>
            }
        >
            <Form form={form} layout="vertical">
                {!isEdit && (
                    <Form.Item label={t('admin.users.email', 'Email')}>
                        <Input.Search
                            placeholder={t('admin.users.emailPlaceholder', 'Enter email address...')}
                            enterButton={t('admin.users.search', 'Search')}
                            loading={searchMutation.isPending}
                            onSearch={handleSearch}
                        />
                    </Form.Item>
                )}

                {isEdit && editingUser && (
                    <Descriptions column={1} size="small" bordered>
                        <Descriptions.Item label={t('admin.users.name', 'Name')}>
                            {editingUser.name}
                        </Descriptions.Item>
                        <Descriptions.Item label={t('admin.users.email', 'Email')}>
                            {editingUser.email}
                        </Descriptions.Item>
                    </Descriptions>
                )}

                {!isEdit && hasSearched && (
                    <>
                        {(userFoundButAlreadyInOrg || userFoundNotInOrg) && searchResult && (
                            <Descriptions column={1} size="small" bordered>
                                <Descriptions.Item label={t('admin.users.name', 'Name')}>
                                    {searchResult.name}
                                </Descriptions.Item>
                                <Descriptions.Item label={t('admin.users.email', 'Email')}>
                                    {searchResult.email}
                                </Descriptions.Item>
                            </Descriptions>
                        )}

                        {userFoundButAlreadyInOrg && (
                            <Tag color="warning">
                                {t('admin.users.alreadyInOrg', 'This user already belongs to this organization.')}
                            </Tag>
                        )}

                        {userNotFound && (
                            <>
                                <Tag color="processing">
                                    {t('admin.users.willCreate', 'No existing user found. A new account will be created.')}
                                </Tag>
                                <Form.Item
                                    name="name"
                                    label={t('admin.users.name', 'Name')}
                                    rules={[
                                        { required: true, message: t('validation.required', 'This field is required.') },
                                        { max: 255 },
                                    ]}
                                >
                                    <Input placeholder={t('admin.users.namePlaceholder', 'e.g. John Doe')} />
                                </Form.Item>
                            </>
                        )}
                    </>
                )}

                {(isEdit || (hasSearched && !userFoundButAlreadyInOrg)) && (
                    <>
                        <Form.Item
                            name="role_id"
                            label={t('admin.users.role', 'Role')}
                            rules={[{ required: true, message: t('validation.required', 'This field is required.') }]}
                        >
                            <Select
                                placeholder={t('admin.users.selectRole', 'Select a role...')}
                                options={roles.map((r) => ({
                                    value: r.id,
                                    label: `Lv.${r.level} ${r.name}`,
                                }))}
                            />
                        </Form.Item>

                        <Form.Item
                            name="console_branch_id"
                            label={t('admin.users.branch', 'Branch Scope')}
                            getValueProps={(value) => ({
                                value: value === null || value === undefined ? ORG_WIDE_VALUE : value,
                            })}
                            getValueFromEvent={(value: string) =>
                                value === ORG_WIDE_VALUE ? null : value
                            }
                        >
                            <Select
                                options={branchOptions}
                                placeholder={t('admin.users.selectBranch', 'Select branch scope...')}
                            />
                        </Form.Item>
                    </>
                )}
            </Form>
        </Drawer>
    );
}

/* ── Users Tab ─────────────────────────────────────── */

type UsersTabProps = {
    users: PaginatedResponse<UserRow>;
    organizationSlug: string;
    roles: RoleOption[];
    branches: Branch[];
};

function UsersTab({ users, organizationSlug, roles, branches }: UsersTabProps) {
    const { t } = useTranslation();
    const { message, modal } = App.useApp();
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editingUser, setEditingUser] = useState<UserRow | null>(null);

    const deleteUserMutation = useMutation({
        mutationFn: (userId: string) =>
            api.delete(`/admin/organizations/${organizationSlug}/users/${userId}`),
        onSuccess: () => {
            message.success(t('admin.users.removed', 'User removed from organization.'));
            router.reload();
        },
        onError: (error: unknown) => {
            const axiosError = isAxiosError(error) ? error : null;
            message.error(
                axiosError?.response?.data?.message ?? t('admin.users.removeFailed', 'Failed to remove user.'),
            );
        },
    });

    const handleCreate = () => {
        setEditingUser(null);
        setDrawerOpen(true);
    };

    const handleEdit = (user: UserRow) => {
        setEditingUser(user);
        setDrawerOpen(true);
    };

    const handleDelete = (user: UserRow) => {
        modal.confirm({
            title: t('admin.users.removeConfirm', 'Remove this user from the organization?'),
            content: t(
                'admin.users.removeConfirmContent',
                'The user account will not be deleted. Only the role assignment in this organization will be removed.',
            ),
            okText: t('admin.users.remove', 'Remove'),
            okButtonProps: { danger: true },
            onOk: () => deleteUserMutation.mutateAsync(user.id),
        });
    };

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
        {
            title: t('admin.users.scope', 'Scope'),
            key: 'scope_type',
            render: (_, record) =>
                record.scope_type === 'branch' && record.branch_name
                    ? <Tag color="purple">{record.branch_name}</Tag>
                    : <Tag color="blue">{t('admin.users.orgWide', 'Org-wide')}</Tag>,
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
                                label: t('admin.users.editRole', 'Edit Role'),
                                onClick: () => handleEdit(record),
                            },
                            { type: 'divider' },
                            {
                                key: 'remove',
                                label: t('admin.users.removeFromOrg', 'Remove from org'),
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
                    {t('admin.users.addUser', 'Add User')}
                </Button>
            </Flex>

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

            <UserFormDrawer
                open={drawerOpen}
                onClose={() => setDrawerOpen(false)}
                organizationSlug={organizationSlug}
                editingUser={editingUser}
                roles={roles}
                branches={branches}
            />
        </>
    );
}

/* ── Main Page ─────────────────────────────────────── */

export default function AdminOrganizationShow({ organization, branches, locations, users, roles, tab, filters }: Props) {
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
                    roles={roles}
                    branches={branches.data}
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
