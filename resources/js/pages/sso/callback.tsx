import { Button } from '@omnifyjp/ui';
import AuthLayout from '@/layouts/auth-layout';
import { Head, router } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, LoaderCircle } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

type SsoCallbackProps = {
    callbackApiUrl: string;
};

type CallbackState = 'processing' | 'success' | 'error';

export default function SsoCallback({ callbackApiUrl }: SsoCallbackProps) {
    const { t } = useTranslation();
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

            <div className="flex flex-col items-center space-y-6 text-center">
                {state === 'processing' && (
                    <>
                        <div className="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10">
                            <LoaderCircle className="h-8 w-8 animate-spin text-primary" />
                        </div>
                        <div className="space-y-2">
                            <h2 className="text-page-title font-semibold">
                                {t('sso.callback.processing', 'Authenticating...')}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {t('sso.callback.processingDescription', 'Please wait while we verify your identity.')}
                            </p>
                        </div>
                    </>
                )}

                {state === 'success' && (
                    <>
                        <div className="flex h-16 w-16 items-center justify-center rounded-full bg-green-50 dark:bg-green-500/15">
                            <CheckCircle2 className="h-8 w-8 text-green-600 dark:text-green-400" />
                        </div>
                        <div className="space-y-2">
                            <h2 className="text-page-title font-semibold">
                                {t('sso.callback.success', 'Authentication successful')}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {t('sso.callback.successDescription', 'Redirecting to dashboard...')}
                            </p>
                        </div>
                    </>
                )}

                {state === 'error' && (
                    <>
                        <div className="flex h-16 w-16 items-center justify-center rounded-full bg-destructive/10">
                            <AlertCircle className="h-8 w-8 text-destructive" />
                        </div>
                        <div className="space-y-2">
                            <h2 className="text-page-title font-semibold">
                                {t('sso.callback.error', 'Authentication failed')}
                            </h2>
                            <p className="text-sm text-muted-foreground">{errorMessage}</p>
                        </div>
                        <Button variant="outline" size="lg" onClick={() => router.visit('/sso/login')}>
                            {t('sso.callback.tryAgain', 'Try again')}
                        </Button>
                    </>
                )}
            </div>
        </AuthLayout>
    );
}
