import { PageContainer } from '@omnify-core/components/page-container';
import { useFormMutation } from '@omnify-core/hooks';
import { api } from '@omnify-core/services/api';
import { Button, Card, Col, Flex, Form, Grid, Input, Row, Select, Switch } from 'antd';
import { useTranslation } from 'react-i18next';

type BrandData = {
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
    brand: BrandData;
    organizations: OrganizationOption[];
};

type BrandUpdateForm = {
    name: string;
    slug: string;
    organization_id: string;
    description: string;
    is_active: boolean;
};

export default function AdminBrandsEdit({ brand, organizations }: Props) {
    const { t } = useTranslation();
    const screens = Grid.useBreakpoint();
    const isWide = screens.lg ?? false;
    const [form] = Form.useForm<BrandUpdateForm>();

    const currentOrg = organizations.find(
        (o) => o.console_organization_id === brand.console_organization_id,
    );

    const mutation = useFormMutation({
        form,
        mutationFn: (data: BrandUpdateForm) => api.put(`/admin/brands/${brand.id}`, data),
        redirectTo: '/admin/brands',
    });

    const breadcrumbs = [
        { title: t('nav.dashboard', 'Dashboard'), href: '/dashboard' },
        { title: t('admin.brands.title', 'Brands'), href: '/admin/brands' },
        { title: t('admin.brands.edit', 'Edit Brand'), href: `/admin/brands/${brand.id}/edit` },
    ];

    return (
        <PageContainer
            title={t('admin.brands.edit', 'Edit Brand')}
            subtitle={brand.name}
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
                            name: brand.name,
                            slug: brand.slug,
                            organization_id: currentOrg?.id,
                            description: brand.description ?? '',
                            is_active: brand.is_active,
                        }}
                        onFinish={(values) => mutation.mutate(values)}
                    >
                        <Flex vertical gap={24}>
                            <Card title={t('admin.brands.brandInfo', 'Brand Information')}>
                                <Flex vertical gap={12}>
                                    <Form.Item
                                        name="organization_id"
                                        label={t('admin.brands.organization', 'Organization')}
                                    >
                                        <Select
                                            placeholder={t('admin.brands.selectOrg', 'Select an organization...')}
                                            options={organizations.map((org) => ({
                                                value: org.id,
                                                label: org.name,
                                            }))}
                                        />
                                    </Form.Item>

                                    <Form.Item
                                        name="name"
                                        label={t('admin.brands.name', 'Name')}
                                        rules={[{ required: true }]}
                                    >
                                        <Input type="text" />
                                    </Form.Item>

                                    <Form.Item
                                        name="slug"
                                        label={t('admin.brands.slug', 'Slug')}
                                        rules={[{ required: true }]}
                                        extra={t('admin.brands.slugDesc', 'URL-friendly identifier.')}
                                    >
                                        <Input type="text" />
                                    </Form.Item>

                                    <Form.Item
                                        name="description"
                                        label={t('admin.brands.description', 'Description')}
                                    >
                                        <Input.TextArea rows={3} />
                                    </Form.Item>

                                    <Form.Item
                                        name="is_active"
                                        label={t('admin.brands.isActive', 'Active')}
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
