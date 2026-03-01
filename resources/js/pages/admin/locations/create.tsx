import { PageContainer } from '@omnify-core/components/page-container';
import { useFormMutation } from '@omnify-core/hooks';
import { api } from '@omnify-core/services/api';
import { Button, Card, Col, Flex, Form, Grid, Input, Row, Select, Switch } from 'antd';
import { useTranslation } from 'react-i18next';

/* ── Types ─────────────────────────────────────────── */

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
    branches: BranchOption[];
    organizations: OrganizationOption[];
};

type LocationCreateForm = {
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

export default function AdminLocationsCreate({ branches, organizations }: Props) {
    const { t } = useTranslation();
    const screens = Grid.useBreakpoint();
    const isWide = screens.lg ?? false;
    const [form] = Form.useForm<LocationCreateForm>();

    const mutation = useFormMutation({
        form,
        mutationFn: (data: LocationCreateForm) => api.post('/admin/locations', data),
        redirectTo: '/admin/locations',
    });

    const breadcrumbs = [
        { title: t('nav.dashboard', 'Dashboard'), href: '/dashboard' },
        { title: t('admin.locations.title', 'Locations'), href: '/admin/locations' },
        { title: t('admin.locations.create', 'Create Location'), href: '/admin/locations/create' },
    ];

    return (
        <PageContainer
            title={t('admin.locations.create', 'Create Location')}
            subtitle={t('admin.locations.createDesc', 'Add a new location to a branch.')}
            breadcrumbs={breadcrumbs}
        >
            <Form
                form={form}
                layout={isWide ? 'horizontal' : 'vertical'}
                labelCol={isWide ? { span: 6 } : undefined}
                wrapperCol={isWide ? { span: 18 } : undefined}
                initialValues={{
                    name: '',
                    code: '',
                    branch_id: undefined,
                    type: undefined,
                    is_active: true,
                }}
                onFinish={(values) => mutation.mutate(values)}
            >
                <Row gutter={[0, 24]}>
                    <Col xs={24} lg={16}>
                        <Card title={t('admin.locations.locationInfo', 'Location Information')}>
                            <Form.Item
                                name="branch_id"
                                label={t('admin.locations.branch', 'Branch')}
                                rules={[{ required: true }]}
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
                                <Input
                                    type="text"
                                    placeholder={t('admin.locations.namePlaceholder', 'e.g. Main Office')}
                                />
                            </Form.Item>

                            <Form.Item
                                name="code"
                                label={t('admin.locations.code', 'Code')}
                                rules={[{ required: true }]}
                            >
                                <Input
                                    type="text"
                                    placeholder={t('admin.locations.codePlaceholder', 'e.g. HQ-001')}
                                />
                            </Form.Item>

                            <Form.Item
                                name="type"
                                label={t('admin.locations.type', 'Type')}
                                rules={[{ required: true }]}
                            >
                                <Select
                                    placeholder={t('admin.locations.selectType', 'Select a type...')}
                                    options={LOCATION_TYPES}
                                />
                            </Form.Item>

                            <Form.Item
                                name="is_active"
                                label={t('admin.locations.isActive', 'Active')}
                                valuePropName="checked"
                            >
                                <Switch />
                            </Form.Item>
                        </Card>
                    </Col>
                    <Col xs={24} lg={16}>
                        <Flex justify="end" gap={8}>
                            <Button
                                type="default"
                                onClick={() => window.history.back()}
                            >
                                {t('common.cancel', 'Cancel')}
                            </Button>
                            <Button type="primary" htmlType="submit" loading={mutation.isPending}>
                                {t('admin.locations.createLocation', 'Create Location')}
                            </Button>
                        </Flex>
                    </Col>
                </Row>
            </Form>
        </PageContainer>
    );
}
