import { router } from '@inertiajs/react';
import { PageContainer } from '@omnify-core/components/page-container';
import { useFormMutation } from '@omnify-core/hooks';
import { securityService, type TwoFactorCodeData } from '@omnify-core/services/security';
import { useMutation } from '@tanstack/react-query';
import { Alert, Button, Card, Col, Flex, Form, Input, Row, Tag, Typography, theme } from 'antd';
import { Download, RefreshCw, ShieldCheck, ShieldOff } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

// =============================================================================
// Types
// =============================================================================

type SecurityProps = {
    twoFactorEnabled: boolean;
    qrCodeUrl?: string;
    secret?: string;
    recoveryCodes?: string[];
};

// =============================================================================
// Recovery Codes
// =============================================================================

function RecoveryCodesSection({ codes }: { codes: string[] }) {
    const { t } = useTranslation();
    const { token } = theme.useToken();

    const regenerateMutation = useMutation({
        mutationFn: () => securityService.regenerateRecoveryCodes(),
        onSuccess: () => router.reload(),
    });

    const handleDownload = () => {
        const content = codes.join('\n');
        const blob = new Blob([content], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'recovery-codes.txt';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    };

    return (
        <Card title={t('security.recoveryCodes.title', 'Recovery Codes')}>
            <Flex vertical gap={token.padding}>
                <Alert
                    type="warning"
                    showIcon
                    message={t(
                        'security.recoveryCodes.warning',
                        'Save these codes in a secure location. Each code can only be used once.',
                    )}
                />
                <Row gutter={[8, 8]}>
                    {codes.map((code) => (
                        <Col key={code} span={12}>
                            <Typography.Text code>{code}</Typography.Text>
                        </Col>
                    ))}
                </Row>
                <Flex gap="small">
                    <Button icon={<Download size={14} />} onClick={handleDownload}>
                        {t('security.recoveryCodes.download', 'Download')}
                    </Button>
                    <Button
                        icon={<RefreshCw size={14} />}
                        onClick={() => regenerateMutation.mutate()}
                        loading={regenerateMutation.isPending}
                    >
                        {t('security.recoveryCodes.regenerate', 'Regenerate')}
                    </Button>
                </Flex>
            </Flex>
        </Card>
    );
}

// =============================================================================
// QR Setup Wizard
// =============================================================================

function QrSetupWizard({ qrCodeUrl, secret }: { qrCodeUrl: string; secret: string }) {
    const { t } = useTranslation();
    const { token } = theme.useToken();
    const [form] = Form.useForm<TwoFactorCodeData>();
    const codeValue = Form.useWatch('code', form) ?? '';

    const mutation = useFormMutation({
        form,
        mutationFn: securityService.enable2fa,
        onSuccess: () => router.reload(),
    });

    return (
        <Card title={t('security.setup.title', 'Set Up Two-Factor Authentication')}>
            <Flex vertical gap="large">
                <Typography.Text type="secondary">
                    {t(
                        'security.setup.description',
                        'Scan the QR code below with your authenticator app (e.g. Google Authenticator, Authy), then enter the 6-digit code to verify.',
                    )}
                </Typography.Text>
                <Flex vertical align="center" gap={token.padding}>
                    <img
                        src={qrCodeUrl}
                        alt={t('security.setup.qrAlt', 'Scan with authenticator app')}
                        width={192}
                        height={192}
                    />
                    <Flex vertical gap={token.paddingXXS}>
                        <Typography.Text type="secondary">
                            {t('security.setup.manualEntry', 'Or enter this secret manually:')}
                        </Typography.Text>
                        <Typography.Text code copyable>{secret}</Typography.Text>
                    </Flex>
                </Flex>
                <Form
                    form={form}
                    layout="vertical"
                    initialValues={{ code: '' }}
                    onFinish={(values) => mutation.mutate(values)}
                >
                    <Form.Item
                        name="code"
                        label={t('security.setup.verifyCode', 'Verification Code')}
                        rules={[{ required: true }, { len: 6, message: 'Code must be 6 digits' }]}
                        normalize={(value: string) => value.replace(/\D/g, '')}
                    >
                        <Input inputMode="numeric" maxLength={6} placeholder="000000" autoFocus />
                    </Form.Item>
                    <Form.Item>
                        <Button
                            type="primary"
                            htmlType="submit"
                            loading={mutation.isPending}
                            disabled={codeValue.length !== 6}
                        >
                            {t('security.setup.verifyAndEnable', 'Verify and Enable')}
                        </Button>
                    </Form.Item>
                </Form>
            </Flex>
        </Card>
    );
}

// =============================================================================
// Disable 2FA Confirm
// =============================================================================

function DisableConfirmForm() {
    const { t } = useTranslation();
    const { token } = theme.useToken();
    const [showConfirm, setShowConfirm] = useState(false);
    const [form] = Form.useForm<TwoFactorCodeData>();
    const codeValue = Form.useWatch('code', form) ?? '';

    const mutation = useFormMutation({
        form,
        mutationFn: securityService.disable2fa,
        onSuccess: () => {
            setShowConfirm(false);
            router.reload();
        },
    });

    if (!showConfirm) {
        return (
            <Button danger icon={<ShieldOff size={16} />} onClick={() => setShowConfirm(true)}>
                {t('security.twoFactor.disable', 'Disable Two-Factor Authentication')}
            </Button>
        );
    }

    return (
        <Card>
            <Flex vertical gap={token.padding}>
                <Alert
                    type="error"
                    showIcon
                    message={t('security.twoFactor.disableConfirmTitle', 'Disable Two-Factor Authentication')}
                    description={t(
                        'security.twoFactor.disableConfirmDescription',
                        'Enter your current 6-digit TOTP code to confirm disabling 2FA.',
                    )}
                />
                <Form
                    form={form}
                    layout="vertical"
                    initialValues={{ code: '' }}
                    onFinish={(values) => mutation.mutate(values)}
                >
                    <Form.Item
                        name="code"
                        label={t('security.twoFactor.currentCode', 'Current TOTP Code')}
                        rules={[{ required: true }, { len: 6, message: 'Code must be 6 digits' }]}
                        normalize={(value: string) => value.replace(/\D/g, '')}
                    >
                        <Input inputMode="numeric" maxLength={6} placeholder="000000" autoFocus />
                    </Form.Item>
                    <Form.Item>
                        <Flex gap="small">
                            <Button
                                danger
                                type="primary"
                                htmlType="submit"
                                loading={mutation.isPending}
                                disabled={codeValue.length !== 6}
                            >
                                {t('security.twoFactor.confirmDisable', 'Disable 2FA')}
                            </Button>
                            <Button onClick={() => setShowConfirm(false)}>
                                {t('common.cancel', 'Cancel')}
                            </Button>
                        </Flex>
                    </Form.Item>
                </Form>
            </Flex>
        </Card>
    );
}

// =============================================================================
// Security Page
// =============================================================================

export default function SecurityPage({
    twoFactorEnabled,
    qrCodeUrl,
    secret,
    recoveryCodes,
}: SecurityProps) {
    const { t } = useTranslation();
    const { token } = theme.useToken();

    const setupMutation = useMutation({
        mutationFn: () => securityService.setup2fa(),
        onSuccess: () => router.reload(),
    });

    return (
        <PageContainer
            title={t('security.twoFactor.title', 'Two-Factor Authentication')}
            subtitle={t('security.twoFactor.subtitle', 'Manage your two-factor authentication settings.')}
            breadcrumbs={[
                { title: t('settings.tabs.security', 'Security'), href: '/settings/security' },
            ]}
        >
            <Flex vertical gap={token.padding}>
                <Card
                    title={
                        <Flex align="center" gap="small">
                            <ShieldCheck size={18} />
                            {t('security.twoFactor.title', 'Two-Factor Authentication')}
                        </Flex>
                    }
                    extra={
                        twoFactorEnabled
                            ? <Tag color="success">{t('security.twoFactor.enabled', 'Enabled')}</Tag>
                            : <Tag>{t('security.twoFactor.disabled', 'Disabled')}</Tag>
                    }
                >
                    <Flex vertical gap={token.padding}>
                        <Typography.Text type="secondary">
                            {twoFactorEnabled
                                ? t('security.twoFactor.enabledDescription', 'Two-factor authentication is active. Your account has an extra layer of security.')
                                : t('security.twoFactor.disabledDescription', 'Add an extra layer of security to your account by requiring a TOTP code at login.')}
                        </Typography.Text>
                        {twoFactorEnabled ? (
                            <DisableConfirmForm />
                        ) : (
                            !qrCodeUrl && (
                                <Button
                                    type="primary"
                                    icon={<ShieldCheck size={16} />}
                                    onClick={() => setupMutation.mutate()}
                                    loading={setupMutation.isPending}
                                >
                                    {t('security.twoFactor.enable', 'Enable Two-Factor Authentication')}
                                </Button>
                            )
                        )}
                    </Flex>
                </Card>

                {qrCodeUrl && secret && <QrSetupWizard qrCodeUrl={qrCodeUrl} secret={secret} />}
                {recoveryCodes && recoveryCodes.length > 0 && <RecoveryCodesSection codes={recoveryCodes} />}
            </Flex>
        </PageContainer>
    );
}
