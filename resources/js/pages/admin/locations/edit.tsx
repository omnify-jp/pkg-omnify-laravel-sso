import { PageContainer } from '@omnify-core/components/page-container';
import { useFormMutation } from '@omnify-core/hooks';
import { api } from '@omnify-core/services/api';
import { Button, Card, Col, Flex, Form, Grid, Input, Row, Select, Switch } from 'antd';
import { useTranslation } from 'react-i18next';

/* ── Types ─────────────────────────────────────────── */

type LocationData = {
    id: string;
    name: string;
    code: string;
    type: string;
    console_branch_id: string;
    console_organization_id: string;
    is_active: boolean;
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
    location: LocationData;
    branches: BranchOption[];
    organizations: OrganizationOption[];
};

type LocationUpdateForm = {
    name: string;
    code: string;
    branch_id: string;
    type: string;
    is_active: boolean;
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

/* ── Main Page ─────────────────────────────────────── */

export default function AdminLocationsEdit({ location, branches, organizations }: Props) {
    const { t } = useTranslation();
    const screens = Grid.useBreakpoint();
    const isWide = screens.lg ?? false;
    const [form] = Form.useForm<LocationUpdateForm>();

    const currentBranch = branches.find(
        (b) => b.console_branch_id === location.console_branch_id,
    );

    const mutation = useFormMutation({
        form,
        mutationFn: (data: LocationUpdateForm) => api.put(`/admin/locations/${location.id}`, data),
        redirectTo: '/admin/locations',
    });

    const breadcrumbs = [
        { title: t('nav.dashboard', 'Dashboard'), href: '/dashboard' },
        { title: t('admin.locations.title', 'Locations'), href: '/admin/locations' },
        { title: t('admin.locations.edit', 'Edit Location'), href: `/admin/locations/${location.id}/edit` },
    ];

    return (
        <PageContainer
            title={t('admin.locations.edit', 'Edit Location')}
            subtitle={location.name}
            breadcrumbs={breadcrumbs}
        >
            <Row>
                <Col xs={24} lg={16}>
                    <Form
                        form={form}
                        layout={isWide ? 'horizontal' : 'vertical'}
                        labelCol={isWide ? { span: 6 } : undefined}
                        wrapperCol={isWide ? { span: 18 } : undefined}
                        initialValues={{
                            name: location.name,
                            code: location.code,
                            branch_id: currentBranch?.id,
                            type: location.type,
                            is_active: location.is_active,
                        }}
                        onFinish={(values) => mutation.mutate(values)}
                    >
                        <Flex vertical gap={24}>
                            <Card title={t('admin.locations.locationInfo', 'Location Information')}>
                                <Flex vertical gap={12}>
                                    <Form.Item
                                        name="branch_id"
                                        label={t('admin.locations.branch', 'Branch')}
                                    >
                                        <Select
                                            placeholder={t('admin.locations.selectBranch', 'Select a branch...')}
                                            options={branches.map((branch) => ({
                                                value: branch.id,
                                                label: branch.name,
                                            }))}
                                        />
                                    </Form.Item>

                                    <Form.Item
                                        name="name"
                                        label={t('admin.locations.name', 'Name')}
                                        rules={[{ required: true }]}
                                    >
                                        <Input type="text" />
                                    </Form.Item>

                                    <Form.Item
                                        name="code"
                                        label={t('admin.locations.code', 'Code')}
                                        rules={[{ required: true }]}
                                    >
                                        <Input type="text" />
                                    </Form.Item>

                                    <Form.Item
                                        name="type"
                                        label={t('admin.locations.type', 'Type')}
                                        rules={[{ required: true }]}
                                    >
                                        <Select options={LOCATION_TYPES} />
                                    </Form.Item>

                                    <Form.Item
                                        name="is_active"
                                        label={t('admin.locations.isActive', 'Active')}
                                        valuePropName="checked"
                                    >
                                        <Switch />
                                    </Form.Item>
                                </Flex>
                            </Card>

                            <Flex justify="end" gap={8}>
                                <Button
                                    type="default"
                                    onClick={() => window.history.back()}
                                >
                                    {t('common.cancel', 'Cancel')}
                                </Button>
                                <Button type="primary" htmlType="submit" loading={mutation.isPending}>
                                    {t('common.save', 'Save Changes')}
                                </Button>
                            </Flex>
                        </Flex>
                    </Form>
                </Col>
            </Row>
        </PageContainer>
    );
}
