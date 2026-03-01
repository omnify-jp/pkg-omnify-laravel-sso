import { PageContainer } from '@omnify-core/components/page-container';
import { useFormMutation } from '@omnify-core/hooks';
import { api } from '@omnify-core/services/api';
import { Button, Card, Col, Flex, Form, Grid, Input, Row, Select, Switch } from 'antd';
import { useRef } from 'react';
import { useTranslation } from 'react-i18next';

type OrganizationOption = {
    id: string;
    console_organization_id: string;
    name: string;
    slug: string;
};

type Props = {
    organizations: OrganizationOption[];
};

type BrandCreateForm = {
    name: string;
    slug: string;
    organization_id: string;
    description: string;
    is_active: boolean;
};

function toSlug(name: string): string {
    return name
        .toLowerCase()
        .trim()
        .replace(/[^\w\s-]/g, '')
        .replace(/[\s_-]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

export default function AdminBrandsCreate({ organizations }: Props) {
    const { t } = useTranslation();
    const screens = Grid.useBreakpoint();
    const isWide = screens.lg ?? false;
    const [form] = Form.useForm<BrandCreateForm>();
    const prevNameRef = useRef('');

    const mutation = useFormMutation({
        form,
        mutationFn: (data: BrandCreateForm) => api.post('/admin/brands', data),
        redirectTo: '/admin/brands',
    });

    const breadcrumbs = [
        { title: t('nav.dashboard', 'Dashboard'), href: '/dashboard' },
        { title: t('admin.brands.title', 'Brands'), href: '/admin/brands' },
        { title: t('admin.brands.create', 'Create Brand'), href: '/admin/brands/create' },
    ];

    return (
        <PageContainer
            title={t('admin.brands.create', 'Create Brand')}
            subtitle={t('admin.brands.createDesc', 'Add a new brand to an organization.')}
            breadcrumbs={breadcrumbs}
        >
            <Form
                form={form}
                layout={isWide ? 'horizontal' : 'vertical'}
                labelCol={isWide ? { span: 6 } : undefined}
                wrapperCol={isWide ? { span: 18 } : undefined}
                initialValues={{
                    name: '',
                    slug: '',
                    organization_id: undefined,
                    description: '',
                    is_active: true,
                }}
                onValuesChange={(changedValues) => {
                    if ('name' in changedValues) {
                        const newName = changedValues.name as string;
                        const currentSlug = form.getFieldValue('slug') as string;
                        if (currentSlug === toSlug(prevNameRef.current)) {
                            form.setFieldValue('slug', toSlug(newName));
                        }
                        prevNameRef.current = newName;
                    }
                }}
                onFinish={(values) => mutation.mutate(values)}
            >
                <Row gutter={[0, 24]}>
                    <Col xs={24} lg={16}>
                        <Card title={t('admin.brands.brandInfo', 'Brand Information')}>
                            <Form.Item
                                name="organization_id"
                                label={t('admin.brands.organization', 'Organization')}
                                rules={[{ required: true }]}
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
                                <Input
                                    type="text"
                                    placeholder={t('admin.brands.namePlaceholder', 'e.g. Highland Coffee')}
                                />
                            </Form.Item>

                            <Form.Item
                                name="slug"
                                label={t('admin.brands.slug', 'Slug')}
                                rules={[{ required: true }]}
                                extra={t('admin.brands.slugDesc', 'URL-friendly identifier. Auto-generated from name.')}
                            >
                                <Input
                                    type="text"
                                    placeholder="highland-coffee"
                                />
                            </Form.Item>

                            <Form.Item
                                name="description"
                                label={t('admin.brands.description', 'Description')}
                            >
                                <Input.TextArea
                                    rows={3}
                                    placeholder={t('admin.brands.descriptionPlaceholder', 'Brief description of the brand...')}
                                />
                            </Form.Item>

                            <Form.Item
                                name="is_active"
                                label={t('admin.brands.isActive', 'Active')}
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
                                {t('admin.brands.createBrand', 'Create Brand')}
                            </Button>
                        </Flex>
                    </Col>
                </Row>
            </Form>
        </PageContainer>
    );
}
