import { Head } from '@inertiajs/react';
import { Alert, Button, Flex } from 'antd';
import { ExternalLink, LoaderCircle, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useAuthLayout } from '@omnify-core/contexts/auth-layout-context';

type SsoLoginProps = {
    ssoAuthorizeUrl: string;
    consoleUrl: string;
};

export default function SsoLogin({ ssoAuthorizeUrl }: SsoLoginProps) {
    const { t } = useTranslation();
    const AuthLayout = useAuthLayout();
    const [isRedirecting, setIsRedirecting] = useState(false);

    const handleSsoLogin = () => {
        setIsRedirecting(true);
        window.location.href = ssoAuthorizeUrl;
    };

    return (
        <AuthLayout title={t('sso.login.title', 'SSO Login')} description={t('sso.login.description', 'Sign in with your organization account')}>
            <Head title={t('sso.login.pageTitle', 'Login')} />

            <Flex vertical gap={24}>
                <Alert
                    type="info"
                    showIcon
                    icon={<ShieldCheck size={16} />}
                    title={t('sso.login.secureAuth', 'Secure authentication')}
                    description={t('sso.login.secureAuthDescription', 'You will be redirected to the Omnify Console to sign in securely. After authentication, you will be returned to this application.')}
                />

                <Button
                    type="primary"
                    size="large"
                    block
                    icon={isRedirecting ? <LoaderCircle size={16} /> : <ExternalLink size={16} />}
                    onClick={handleSsoLogin}
                    disabled={isRedirecting}
                >
                    {isRedirecting
                        ? t('sso.login.redirecting', 'Redirecting...')
                        : t('sso.login.submit', 'Sign in with Console SSO')}
                </Button>
            </Flex>
        </AuthLayout>
    );
}
