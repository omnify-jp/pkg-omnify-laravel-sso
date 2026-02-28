import { PageContainer } from '@omnify-core/components/page-container';
import { useFormMutation } from '@omnify-core/hooks';
import { api } from '@omnify-core/services/api';
import { Button, Card, Col, Flex, Form, Grid, Input, Row, Select } from 'antd';
import { useTranslation } from 'react-i18next';

type Role = {
    id: string;
    slug: string;
    name: string;
    level: number;
};

type Props = {
    roles: Role[];
};

type UserCreateForm = {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    role_id: string;
};

export default function AdminUserCreate({ roles }: Props) {
    const { t } = useTranslation();
    const screens = Grid.useBreakpoint();
    const isWide = screens.lg ?? false;
    const [form] = Form.useForm<UserCreateForm>();

    const mutation = useFormMutation({
        form,
        mutationFn: (data: UserCreateForm) => api.post('/admin/users', data),
        redirectTo: '/admin/iam',
    });

    const breadcrumbs = [
        { title: t('nav.dashboard', 'Dashboard'), href: '/dashboard' },
        { title: t('nav.iam', 'IAM'), href: '/admin/iam' },
        { title: t('admin.users.create', 'Create User'), href: '/admin/users/create' },
    ];

    return (
        <PageContainer
            title={t('admin.users.create', 'Create User')}
            subtitle={t('admin.users.createDesc', 'Add a new user to this standalone instance.')}
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
                            email: '',
                            password: '',
                            password_confirmation: '',
                            role_id: undefined,
                        }}
                        onFinish={(values) => mutation.mutate(values)}
                    >
                        <Flex vertical gap={24}>
                            <Card title={t('admin.users.userInfo', 'User Information')}>
                                <Flex vertical gap={12}>
                                    <Form.Item
                                        name="name"
                                        label={t('admin.users.name', 'Name')}
                                        rules={[{ required: true }]}
                                    >
                                        <Input
                                            type="text"
                                            placeholder={t('admin.users.namePlaceholder', 'e.g. Jane Smith')}
                                            autoComplete="name"
                                        />
                                    </Form.Item>

                                    <Form.Item
                                        name="email"
                                        label={t('admin.users.email', 'Email')}
                                        rules={[{ required: true, type: 'email' }]}
                                    >
                                        <Input
                                            type="email"
                                            placeholder={t('admin.users.emailPlaceholder', 'user@example.com')}
                                            autoComplete="email"
                                        />
                                    </Form.Item>

                                    <Form.Item
                                        name="password"
                                        label={t('admin.users.password', 'Password')}
                                        rules={[{ required: true }]}
                                    >
                                        <Input.Password
                                            placeholder={t('admin.users.passwordPlaceholder', 'Minimum 8 characters')}
                                            autoComplete="new-password"
                                        />
                                    </Form.Item>

                                    <Form.Item
                                        name="password_confirmation"
                                        label={t('admin.users.passwordConfirmation', 'Confirm Password')}
                                        rules={[{ required: true }]}
                                    >
                                        <Input.Password
                                            placeholder={t('admin.users.passwordConfirmationPlaceholder', 'Repeat password')}
                                            autoComplete="new-password"
                                        />
                                    </Form.Item>

                                    <Form.Item
                                        name="role_id"
                                        label={t('admin.users.role', 'Role')}
                                        extra={t('common.optional', 'optional')}
                                    >
                                        <Select
                                            placeholder={t('admin.users.rolePlaceholder', 'Select a role...')}
                                            options={roles.map((role) => ({
                                                value: role.id,
                                                label: `${role.name} (level ${role.level})`,
                                            }))}
                                        />
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
                                    {t('admin.users.createUser', 'Create User')}
                                </Button>
                            </Flex>
                        </Flex>
                    </Form>
                </Col>
            </Row>
        </PageContainer>
    );
}
