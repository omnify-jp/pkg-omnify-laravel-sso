import { Head, router } from '@inertiajs/react';
import { useAuthLayout } from '@omnify-core/contexts/auth-layout-context';
import { useFormMutation } from '@omnify-core/hooks';
import { type LoginData } from '@omnify-core/services/auth';
import { api } from '@omnify-core/services/api';
import { Alert, Button, Form, Input } from 'antd';
import { useTranslation } from 'react-i18next';

type AdminLoginProps = {
    status?: string;
};

export default function AdminLogin({ status }: AdminLoginProps) {
    const { t } = useTranslation();
    const AuthLayout = useAuthLayout();
    const [form] = Form.useForm<LoginData>();

    const mutation = useFormMutation({
        form,
        mutationFn: (data: LoginData) => api.post('/admin/login', data),
        onSuccess: (response) => {
            const url = response.request?.responseURL;
            router.visit(url ? new URL(url).pathname : '/admin');
        },
    });

    return (
        <AuthLayout title={t('admin.login.title', '管理者ログイン')} description={t('admin.login.description', '管理者アカウントでサインインしてください')}>
            <Head title={t('admin.login.pageTitle', '管理者ログイン')} />

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
                    label={t('auth.login.email', 'メールアドレス')}
                    rules={[
                        { required: true },
                        { type: 'email' },
                    ]}
                >
                    <Input
                        size="large"
                        placeholder="admin@company.com"
                        autoComplete="username"
                        autoFocus
                    />
                </Form.Item>

                <Form.Item
                    name="password"
                    label={t('auth.login.password', 'パスワード')}
                    rules={[{ required: true }]}
                >
                    <Input.Password
                        size="large"
                        placeholder="••••••••"
                        autoComplete="current-password"
                    />
                </Form.Item>

                <Form.Item>
                    <Button type="primary" htmlType="submit" size="large" block loading={mutation.isPending}>
                        {t('admin.login.submit', '管理者ログイン')}
                    </Button>
                </Form.Item>
            </Form>
        </AuthLayout>
    );
}
