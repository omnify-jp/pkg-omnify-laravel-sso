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

type BranchCreateForm = {
    name: string;
    slug: string;
    organization_id: string;
    is_active: boolean;
    is_headquarters: boolean;
};

function toSlug(name: string): string {
    return name
        .toLowerCase()
        .trim()
        .replace(/[^\w\s-]/g, '')
        .replace(/[\s_-]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

export default function AdminBranchesCreate({ organizations }: Props) {
    const { t } = useTranslation();
    const screens = Grid.useBreakpoint();
    const isWide = screens.lg ?? false;
    const [form] = Form.useForm<BranchCreateForm>();
    const prevNameRef = useRef('');

    const mutation = useFormMutation({
        form,
        mutationFn: (data: BranchCreateForm) => api.post('/admin/branches', data),
        redirectTo: '/admin/branches',
    });

    const breadcrumbs = [
        { title: t('nav.dashboard', 'Dashboard'), href: '/dashboard' },
        { title: t('admin.branches.title', 'Branches'), href: '/admin/branches' },
        { title: t('admin.branches.create', 'Create Branch'), href: '/admin/branches/create' },
    ];

    return (
        <PageContainer
            title={t('admin.branches.create', 'Create Branch')}
            subtitle={t('admin.branches.createDesc', 'Add a new branch to an organization.')}
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
                            name: '',
                            slug: '',
                            organization_id: undefined,
                            is_active: true,
                            is_headquarters: false,
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
                                        <Input
                                            type="text"
                                            placeholder={t('admin.branches.namePlaceholder', 'e.g. Tokyo Office')}
                                        />
                                    </Form.Item>

                                    <Form.Item
                                        name="slug"
                                        label={t('admin.branches.slug', 'Slug')}
                                        rules={[{ required: true }]}
                                        extra={t('admin.branches.slugDesc', 'URL-friendly identifier. Auto-generated from name.')}
                                    >
                                        <Input
                                            type="text"
                                            placeholder="tokyo-office"
                                        />
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
                                    {t('admin.branches.createBranch', 'Create Branch')}
                                </Button>
                            </Flex>
                        </Flex>
                    </Form>
                </Col>
            </Row>
        </PageContainer>
    );
}
