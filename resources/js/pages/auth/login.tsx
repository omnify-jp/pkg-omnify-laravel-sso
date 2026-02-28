import { Head, Link, router } from '@inertiajs/react';
import { useAuthLayout } from '@omnify-core/contexts/auth-layout-context';
import { useFormMutation } from '@omnify-core/hooks';
import { authService, type LoginData } from '@omnify-core/services/auth';
import { Alert, Button, Checkbox, Divider, Flex, Form, Input, Typography } from 'antd';
import { useTranslation } from 'react-i18next';

type LoginProps = {
    canResetPassword: boolean;
    status?: string;
};

export default function Login({ canResetPassword, status }: LoginProps) {
    const { t } = useTranslation();
    const AuthLayout = useAuthLayout();
    const [form] = Form.useForm<LoginData>();

    const mutation = useFormMutation({
        form,
        mutationFn: authService.login,
        onSuccess: (response) => {
            const url = response.request?.responseURL;
            router.visit(url ? new URL(url).pathname : '/dashboard');
        },
    });

    return (
        <AuthLayout title={t('auth.login.title')} description={t('auth.login.description')}>
            <Head title={t('auth.login.pageTitle')} />

            {status && (
                <Alert type="success" title={status} showIcon />
            )}

            <Form
                form={form}
                layout="vertical"
                initialValues={{ email: '', password: '', remember: false }}
                onFinish={(values) => mutation.mutate(values)}
            >
                <Form.Item
                    name="email"
                    label={t('auth.login.email')}
                    rules={[
                        { required: true },
                        { type: 'email' },
                    ]}
                >
                    <Input
                        size="large"
                        placeholder="name@company.com"
                        autoComplete="username"
                        autoFocus
                    />
                </Form.Item>

                <Form.Item
                    label={
                        <Flex justify="space-between" align="center" flex={1}>
                            <span>{t('auth.login.password')}</span>
                            {canResetPassword && (
                                <Link href="/forgot-password">
                                    {t('auth.login.forgotPassword')}
                                </Link>
                            )}
                        </Flex>
                    }
                    name="password"
                    rules={[{ required: true }]}
                >
                    <Input.Password
                        size="large"
                        placeholder="••••••••"
                        autoComplete="current-password"
                    />
                </Form.Item>

                <Form.Item name="remember" valuePropName="checked">
                    <Checkbox>{t('auth.login.rememberMe')}</Checkbox>
                </Form.Item>

                <Form.Item>
                    <Button type="primary" htmlType="submit" size="large" block loading={mutation.isPending}>
                        {t('auth.login.submit')}
                    </Button>
                </Form.Item>

                <Divider plain>
                    {t('common.or')}
                </Divider>

                <Flex justify="center">
                    <Typography.Text type="secondary">
                        {t('auth.login.noAccount')}{' '}
                        <Link href="/register">
                            {t('auth.login.register')}
                        </Link>
                    </Typography.Text>
                </Flex>
            </Form>
        </AuthLayout>
    );
}
