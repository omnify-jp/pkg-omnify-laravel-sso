import { Head } from '@inertiajs/react';
import { useAuthLayout } from '@omnify-core/contexts/auth-layout-context';
import { useFormMutation } from '@omnify-core/hooks';
import { authService, type ResetPasswordData } from '@omnify-core/services/auth';
import { Button, Form, Input } from 'antd';
import { useTranslation } from 'react-i18next';

type ResetPasswordProps = {
    token: string;
    email: string;
};

export default function ResetPassword({ token, email }: ResetPasswordProps) {
    const { t } = useTranslation();
    const AuthLayout = useAuthLayout();
    const [form] = Form.useForm<ResetPasswordData>();

    const mutation = useFormMutation({
        form,
        mutationFn: authService.resetPassword,
        redirectTo: '/login',
    });

    return (
        <AuthLayout title={t('auth.resetPassword.title')} description={t('auth.resetPassword.description')}>
            <Head title={t('auth.resetPassword.pageTitle')} />

            <Form
                form={form}
                layout="vertical"
                initialValues={{ token, email, password: '', password_confirmation: '' }}
                onFinish={(values) => mutation.mutate(values)}
            >
                <Form.Item name="token" hidden>
                    <Input />
                </Form.Item>

                <Form.Item
                    name="email"
                    label={t('auth.resetPassword.email')}
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
                    label={t('auth.resetPassword.newPassword')}
                    rules={[
                        { required: true },
                        { min: 8 },
                    ]}
                >
                    <Input.Password
                        size="large"
                        placeholder="••••••••"
                        autoComplete="new-password"
                        autoFocus
                    />
                </Form.Item>

                <Form.Item
                    name="password_confirmation"
                    label={t('auth.resetPassword.newPasswordConfirmation')}
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
                        {t('auth.resetPassword.submit')}
                    </Button>
                </Form.Item>
            </Form>
        </AuthLayout>
    );
}
