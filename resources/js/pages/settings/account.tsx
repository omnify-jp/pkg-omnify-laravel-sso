import { PageContainer } from '@omnify-core/components/page-container';
import { useFormMutation } from '@omnify-core/hooks';
import { api } from '@omnify-core/services/api';
import { Button, Card, Flex, Form, Input, theme } from 'antd';
import { useTranslation } from 'react-i18next';

type PasswordFormData = {
    current_password: string;
    password: string;
    password_confirmation: string;
};

export default function SettingsAccount() {
    const { t } = useTranslation();
    const { token } = theme.useToken();
    const [form] = Form.useForm<PasswordFormData>();

    const mutation = useFormMutation({
        form,
        mutationFn: (data: PasswordFormData) => api.put('/settings/password', data),
        onSuccess: () => form.resetFields(),
    });

    return (
        <PageContainer
            title={t('settings.account.title', 'Account')}
            subtitle={t('settings.account.description', 'Change your password and manage account credentials.')}
            breadcrumbs={[
                { title: t('settings.account.title', 'Account'), href: '/settings/account' },
            ]}
        >
            <Card title={t('settings.account.changePassword', 'Change Password')}>
                <Form
                    form={form}
                    layout="vertical"
                    initialValues={{ current_password: '', password: '', password_confirmation: '' }}
                    onFinish={(values) => mutation.mutate(values)}
                    style={{ maxWidth: token.screenSM }}
                >
                    <Form.Item
                        name="current_password"
                        label={t('settings.account.currentPassword', 'Current Password')}
                        rules={[{ required: true }]}
                    >
                        <Input.Password />
                    </Form.Item>
                    <Form.Item
                        name="password"
                        label={t('settings.account.newPassword', 'New Password')}
                        rules={[{ required: true }, { min: 8 }]}
                    >
                        <Input.Password />
                    </Form.Item>
                    <Form.Item
                        name="password_confirmation"
                        label={t('settings.account.confirmPassword', 'Confirm New Password')}
                        rules={[
                            { required: true },
                            ({ getFieldValue }) => ({
                                validator(_, value) {
                                    if (!value || getFieldValue('password') === value) {
                                        return Promise.resolve();
                                    }
                                    return Promise.reject(new Error(t('validation.passwordMismatch', 'Passwords do not match.')));
                                },
                            }),
                        ]}
                    >
                        <Input.Password />
                    </Form.Item>
                    <Form.Item>
                        <Flex justify="end">
                            <Button type="primary" htmlType="submit" loading={mutation.isPending}>
                                {t('settings.account.updatePassword', 'Update Password')}
                            </Button>
                        </Flex>
                    </Form.Item>
                </Form>
            </Card>
        </PageContainer>
    );
}
