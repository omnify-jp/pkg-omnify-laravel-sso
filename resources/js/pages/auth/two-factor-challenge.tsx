import { Head, router } from '@inertiajs/react';
import { useAuthLayout } from '@omnify-core/contexts/auth-layout-context';
import { useFormMutation } from '@omnify-core/hooks';
import { authService, type TwoFactorChallengeData } from '@omnify-core/services/auth';
import { useMutation } from '@tanstack/react-query';
import { Button, Divider, Flex, Form, Input } from 'antd';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

export default function TwoFactorChallenge() {
    const { t } = useTranslation();
    const AuthLayout = useAuthLayout();
    const [useRecoveryCode, setUseRecoveryCode] = useState(false);
    const [form] = Form.useForm<TwoFactorChallengeData>();

    const mutation = useFormMutation({
        form,
        mutationFn: authService.twoFactorChallenge,
        onSuccess: (response) => {
            const url = response.request?.responseURL;
            router.visit(url ? new URL(url).pathname : '/dashboard');
        },
        onError: () => form.setFieldValue('code', ''),
    });

    const logoutMutation = useMutation({
        mutationFn: authService.logout,
        onSuccess: () => router.visit('/login'),
    });

    const toggleMode = () => {
        setUseRecoveryCode((prev) => !prev);
        form.setFieldValue('code', '');
    };

    return (
        <AuthLayout
            title={t('auth.twoFactor.title', 'Two-Factor Authentication')}
            description={
                useRecoveryCode
                    ? t('auth.twoFactor.recoveryDescription', 'Enter one of your recovery codes to access your account.')
                    : t('auth.twoFactor.description', 'Enter the 6-digit code from your authenticator app.')
            }
        >
            <Head title={t('auth.twoFactor.pageTitle', 'Two-Factor Authentication')} />

            <Form
                form={form}
                layout="vertical"
                initialValues={{ code: '' }}
                onFinish={(values) => mutation.mutate(values)}
            >
                {useRecoveryCode ? (
                    <Form.Item
                        name="code"
                        label={t('auth.twoFactor.recoveryCode', 'Recovery Code')}
                        rules={[{ required: true }]}
                    >
                        <Input
                            size="large"
                            placeholder="XXXX-XXXX-XXXX"
                            autoComplete="one-time-code"
                            autoFocus
                        />
                    </Form.Item>
                ) : (
                    <Form.Item
                        name="code"
                        label={t('auth.twoFactor.code', 'Authentication Code')}
                        rules={[{ required: true }]}
                        normalize={(value: string) => value.replace(/\D/g, '')}
                    >
                        <Input
                            size="large"
                            inputMode="numeric"
                            maxLength={6}
                            placeholder="000000"
                            autoComplete="one-time-code"
                            autoFocus
                        />
                    </Form.Item>
                )}

                <Form.Item>
                    <Button type="primary" htmlType="submit" size="large" block loading={mutation.isPending}>
                        {t('auth.twoFactor.submit', 'Verify')}
                    </Button>
                </Form.Item>

                <Flex justify="center">
                    <Button type="link" onClick={toggleMode}>
                        {useRecoveryCode
                            ? t('auth.twoFactor.useTotp', 'Use authenticator app instead')
                            : t('auth.twoFactor.useRecovery', 'Use recovery code instead')}
                    </Button>
                </Flex>

                <Divider />

                <Flex justify="center">
                    <Button type="link" onClick={() => logoutMutation.mutate()}>
                        {t('auth.twoFactor.logout', 'Log out')}
                    </Button>
                </Flex>
            </Form>
        </AuthLayout>
    );
}
