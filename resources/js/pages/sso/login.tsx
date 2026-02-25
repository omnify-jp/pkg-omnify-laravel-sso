import { Button } from '@omnifyjp/ui';
import AuthLayout from '@/layouts/auth-layout';
import { Head } from '@inertiajs/react';
import { ExternalLink, LoaderCircle, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

type SsoLoginProps = {
    ssoAuthorizeUrl: string;
    consoleUrl: string;
};

export default function SsoLogin({ ssoAuthorizeUrl }: SsoLoginProps) {
    const { t } = useTranslation();
    const [isRedirecting, setIsRedirecting] = useState(false);

    const handleSsoLogin = () => {
        setIsRedirecting(true);
        window.location.href = ssoAuthorizeUrl;
    };

    return (
        <AuthLayout title={t('sso.login.title', 'SSO Login')} description={t('sso.login.description', 'Sign in with your organization account')}>
            <Head title={t('sso.login.pageTitle', 'Login')} />

            <div className="space-y-section">
                <div className="rounded-lg border border-border bg-muted/30 p-card">
                    <div className="flex items-start gap-3">
                        <div className="flex h-element-lg w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                            <ShieldCheck className="h-5 w-5 text-primary" />
                        </div>
                        <div className="space-y-1">
                            <p className="text-sm font-medium text-foreground">
                                {t('sso.login.secureAuth', 'Secure authentication')}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {t('sso.login.secureAuthDescription', 'You will be redirected to the Omnify Console to sign in securely. After authentication, you will be returned to this application.')}
                            </p>
                        </div>
                    </div>
                </div>

                <Button
                    size="xl"
                    className="w-full gap-2"
                    onClick={handleSsoLogin}
                    disabled={isRedirecting}
                >
                    {isRedirecting ? (
                        <LoaderCircle className="h-4 w-4 animate-spin" />
                    ) : (
                        <ExternalLink className="h-4 w-4" />
                    )}
                    {isRedirecting
                        ? t('sso.login.redirecting', 'Redirecting...')
                        : t('sso.login.submit', 'Sign in with Console SSO')}
                </Button>
            </div>
        </AuthLayout>
    );
}
