import { Head, Link, router } from '@inertiajs/react';
import { useAuthLayout } from '@omnify-core/contexts/auth-layout-context';
import { useFormMutation } from '@omnify-core/hooks';
import { authService, type RegisterData } from '@omnify-core/services/auth';
import { Button, Divider, Flex, Form, Input, Typography } from 'antd';
import { useTranslation } from 'react-i18next';

export default function Register() {
    const { t } = useTranslation();
    const AuthLayout = useAuthLayout();
    const [form] = Form.useForm<RegisterData>();

    const mutation = useFormMutation({
        form,
        mutationFn: authService.register,
        onSuccess: (response) => {
            const url = response.request?.responseURL;
            router.visit(url ? new URL(url).pathname : '/dashboard');
        },
    });

    return (
        <AuthLayout title={t('auth.register.title')} description={t('auth.register.description')}>
            <Head title={t('auth.register.pageTitle')} />

            <Form
                form={form}
                layout="vertical"
                initialValues={{ name: '', email: '', password: '', password_confirmation: '' }}
                onFinish={(values) => mutation.mutate(values)}
            >
                <Form.Item
                    name="name"
                    label={t('auth.register.name')}
                    rules={[{ required: true }]}
                >
                    <Input
                        size="large"
                        placeholder={t('auth.register.namePlaceholder')}
                        autoComplete="name"
                        autoFocus
                    />
                </Form.Item>

                <Form.Item
                    name="email"
                    label={t('auth.register.email')}
                    rules={[
                        { required: true },
                        { type: 'email' },
                    ]}
                >
                    <Input
                        size="large"
                        placeholder="name@company.com"
                        autoComplete="username"
                    />
                </Form.Item>

                <Form.Item
                    name="password"
                    label={t('auth.register.password')}
                    rules={[
                        { required: true },
                        { min: 8 },
                    ]}
                >
                    <Input.Password
                        size="large"
                        placeholder="••••••••"
                        autoComplete="new-password"
                    />
                </Form.Item>

                <Form.Item
                    name="password_confirmation"
                    label={t('auth.register.passwordConfirmation')}
                    dependencies={['password']}
                    rules={[
                        { required: true },
                        ({ getFieldValue }) => ({
                            validator(_, value) {
                                if (!value || getFieldValue('password') === value) {
                                    return Promise.resolve();
                                }
                                return Promise.reject(new Error('Passwords do not match'));
                            },
                        }),
                    ]}
                >
                    <Input.Password
                        size="large"
                        placeholder="••••••••"
                        autoComplete="new-password"
                    />
                </Form.Item>

                <Form.Item>
                    <Button type="primary" htmlType="submit" size="large" block loading={mutation.isPending}>
                        {t('auth.register.submit')}
                    </Button>
                </Form.Item>

                <Divider plain>
                    {t('common.or')}
                </Divider>

                <Flex justify="center">
                    <Typography.Text type="secondary">
                        {t('auth.register.hasAccount')}{' '}
                        <Link href="/login">
                            {t('auth.register.login')}
                        </Link>
                    </Typography.Text>
                </Flex>
            </Form>
        </AuthLayout>
    );
}
