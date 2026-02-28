import { PageContainer } from '@omnify-core/components/page-container';
import { useFormMutation } from '@omnify-core/hooks';
import { api } from '@omnify-core/services/api';
import { Button, Card, Col, Flex, Form, Grid, Input, Row, Select, Switch } from 'antd';
import { useTranslation } from 'react-i18next';

type BranchData = {
    id: string;
    name: string;
    slug: string;
    console_organization_id: string;
    is_active: boolean;
    is_headquarters: boolean;
};

type OrganizationOption = {
    id: string;
    console_organization_id: string;
    name: string;
    slug: string;
};

type Props = {
    branch: BranchData;
    organizations: OrganizationOption[];
};

type BranchUpdateForm = {
    name: string;
    slug: string;
    organization_id: string;
    is_active: boolean;
    is_headquarters: boolean;
};

export default function AdminBranchesEdit({ branch, organizations }: Props) {
    const { t } = useTranslation();
    const screens = Grid.useBreakpoint();
    const isWide = screens.lg ?? false;
    const [form] = Form.useForm<BranchUpdateForm>();

    // Find the current organization by matching console_organization_id
    const currentOrg = organizations.find(
        (o) => o.console_organization_id === branch.console_organization_id,
    );

    const mutation = useFormMutation({
        form,
        mutationFn: (data: BranchUpdateForm) => api.put(`/admin/branches/${branch.id}`, data),
        redirectTo: '/admin/branches',
    });

    const breadcrumbs = [
        { title: t('nav.dashboard', 'Dashboard'), href: '/dashboard' },
        { title: t('admin.branches.title', 'Branches'), href: '/admin/branches' },
        { title: t('admin.branches.edit', 'Edit Branch'), href: `/admin/branches/${branch.id}/edit` },
    ];

    return (
        <PageContainer
            title={t('admin.branches.edit', 'Edit Branch')}
            subtitle={branch.name}
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
                            name: branch.name,
                            slug: branch.slug,
                            organization_id: currentOrg?.id,
                            is_active: branch.is_active,
                            is_headquarters: branch.is_headquarters,
                        }}
                        onFinish={(values) => mutation.mutate(values)}
                    >
                        <Flex vertical gap={24}>
                            <Card title={t('admin.branches.branchInfo', 'Branch Information')}>
                                <Flex vertical gap={12}>
                                    <Form.Item
                                        name="organization_id"
                                        label={t('admin.branches.organization', 'Organization')}
                                        rules={[{ required: true }]}
                                    >
                                        <Select
                                            placeholder={t('admin.branches.selectOrg', 'Select an organization...')}
                                            options={organizations.map((org) => ({
                                                value: org.id,
                                                label: org.name,
                                            }))}
                                        />
                                    </Form.Item>

                                    <Form.Item
                                        name="name"
                                        label={t('admin.branches.name', 'Name')}
                                        rules={[{ required: true }]}
                                    >
                                        <Input type="text" />
                                    </Form.Item>

                                    <Form.Item
                                        name="slug"
                                        label={t('admin.branches.slug', 'Slug')}
                                        rules={[{ required: true }]}
                                        extra={t('admin.branches.slugDesc', 'URL-friendly identifier.')}
                                    >
                                        <Input type="text" />
                                    </Form.Item>

                                    <Form.Item
                                        name="is_headquarters"
                                        label={t('admin.branches.isHeadquarters', 'Headquarters')}
                                        valuePropName="checked"
                                    >
                                        <Switch />
                                    </Form.Item>

                                    <Form.Item
                                        name="is_active"
                                        label={t('admin.branches.isActive', 'Active')}
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
