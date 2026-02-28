import { Head, Link, router } from '@inertiajs/react';
import { useAuthLayout } from '@omnify-core/contexts/auth-layout-context';
import { useFormMutation } from '@omnify-core/hooks';
import { authService, type ForgotPasswordData } from '@omnify-core/services/auth';
import { Alert, Button, Divider, Flex, Form, Input, Typography } from 'antd';
import { useTranslation } from 'react-i18next';

type ForgotPasswordProps = {
    status?: string;
};

export default function ForgotPassword({ status }: ForgotPasswordProps) {
    const { t } = useTranslation();
    const AuthLayout = useAuthLayout();
    const [form] = Form.useForm<ForgotPasswordData>();

    const mutation = useFormMutation({
        form,
        mutationFn: authService.forgotPassword,
        onSuccess: () => router.reload(),
    });

    return (
        <AuthLayout title={t('auth.forgotPassword.title')} description={t('auth.forgotPassword.description')}>
            <Head title={t('auth.forgotPassword.pageTitle')} />

            {status && (
                <Alert type="success" title={status} showIcon />
            )}

            <Form
                form={form}
                layout="vertical"
                initialValues={{ email: '' }}
                onFinish={(values) => mutation.mutate(values)}
            >
                <Form.Item
                    name="email"
                    label={t('auth.forgotPassword.email')}
                    rules={[
                        { required: true },
                        { type: 'email' },
                    ]}
                >
                    <Input
                        size="large"
                        placeholder="name@company.com"
                        autoFocus
                    />
                </Form.Item>

                <Form.Item>
                    <Button type="primary" htmlType="submit" size="large" block loading={mutation.isPending}>
                        {t('auth.forgotPassword.submit')}
                    </Button>
                </Form.Item>

                <Divider plain>
                    {t('common.or')}
                </Divider>

                <Flex justify="center">
                    <Typography.Text type="secondary">
                        {t('auth.forgotPassword.rememberedPassword')}{' '}
                        <Link href="/login">
                            {t('auth.forgotPassword.backToLogin')}
                        </Link>
                    </Typography.Text>
                </Flex>
            </Form>
        </AuthLayout>
    );
}
