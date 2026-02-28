import { Head, router } from '@inertiajs/react';
import { Alert, Button, Flex, Spin, Typography } from 'antd';
import { CheckCircle2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useAuthLayout } from '@omnify-core/contexts/auth-layout-context';

type SsoCallbackProps = {
    callbackApiUrl: string;
};

type CallbackState = 'processing' | 'success' | 'error';

export default function SsoCallback({ callbackApiUrl }: SsoCallbackProps) {
    const { t } = useTranslation();
    const AuthLayout = useAuthLayout();
    const [state, setState] = useState<CallbackState>('processing');
    const [errorMessage, setErrorMessage] = useState('');
    const processedRef = useRef(false);

    useEffect(() => {
        if (processedRef.current) {
            return;
        }
        processedRef.current = true;

        const params = new URLSearchParams(window.location.search);
        const code = params.get('code');

        if (!code) {
            setState('error');
            setErrorMessage(t('sso.callback.noCode', 'No authorization code received.'));
            return;
        }

        exchangeCode(code);
    }, []);

    const exchangeCode = async (code: string) => {
        try {
            const response = await fetch(callbackApiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ code }),
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                throw new Error(data.message || `Authentication failed (${response.status})`);
            }

            setState('success');

            // Redirect to dashboard after successful auth
            setTimeout(() => {
                router.visit('/dashboard');
            }, 500);
        } catch (error) {
            setState('error');
            setErrorMessage(
                error instanceof Error ? error.message : t('sso.callback.unknownError', 'An unexpected error occurred.'),
            );
        }
    };

    const getCsrfToken = (): string => {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        return match ? decodeURIComponent(match[1]) : '';
    };

    return (
        <AuthLayout>
            <Head title={t('sso.callback.pageTitle', 'Authenticating...')} />

            <Flex vertical align="center" gap={24}>
                {state === 'processing' && (
                    <Flex vertical align="center" gap={16}>
                        <Spin size="large" />
                        <Flex vertical align="center" gap={8}>
                            <Typography.Title level={4}>
                                {t('sso.callback.processing', 'Authenticating...')}
                            </Typography.Title>
                            <Typography.Text type="secondary">
                                {t('sso.callback.processingDescription', 'Please wait while we verify your identity.')}
                            </Typography.Text>
                        </Flex>
                    </Flex>
                )}

                {state === 'success' && (
                    <Flex vertical align="center" gap={16}>
                        <CheckCircle2 size={48} />
                        <Flex vertical align="center" gap={8}>
                            <Typography.Title level={4}>
                                {t('sso.callback.success', 'Authentication successful')}
                            </Typography.Title>
                            <Typography.Text type="secondary">
                                {t('sso.callback.successDescription', 'Redirecting to dashboard...')}
                            </Typography.Text>
                        </Flex>
                    </Flex>
                )}

                {state === 'error' && (
                    <Flex vertical align="center" gap={16}>
                        <Alert
                            type="error"
                            showIcon
                            title={t('sso.callback.error', 'Authentication failed')}
                            description={errorMessage}
                        />
                        <Button size="large" onClick={() => router.visit('/sso/login')}>
                            {t('sso.callback.tryAgain', 'Try again')}
                        </Button>
                    </Flex>
                )}
            </Flex>
        </AuthLayout>
    );
}
