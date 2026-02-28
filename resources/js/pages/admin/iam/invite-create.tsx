import { Head } from '@inertiajs/react';
import { useIamLayout } from '@omnify-core/contexts/iam-layout-context';
import { useFormMutation } from '@omnify-core/hooks';
import { api } from '@omnify-core/services/api';
import { Button, Card, Col, Flex, Form, Input, Row, Select, Typography } from 'antd';
import { isAxiosError } from 'axios';
import { Mail, Send } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { IamBreadcrumb } from '../../../components/access/iam-breadcrumb';

type ConsoleBranch = {
    id: string;
    code: string;
    name: string;
    is_headquarters: boolean;
    timezone: string | null;
    currency: string | null;
    locale: string | null;
};

type ConsoleOrganization = {
    id: string;
    slug: string;
    name: string;
};

type Props = {
    branches: ConsoleBranch[];
    invite_org: ConsoleOrganization | null;
    org_slug: string | null;
    available_roles: string[];
};

type InviteFormData = {
    branch_id: string;
    emails_raw: string;
    role: string;
};

export default function IamInviteCreate({
    branches,
    invite_org,
    org_slug,
    available_roles,
}: Props) {
    const Layout = useIamLayout();
    const { t } = useTranslation();
    const [form] = Form.useForm<InviteFormData>();
    const [nonFieldErrors, setNonFieldErrors] = useState<Record<string, string>>({});

    const mutation = useFormMutation({
        form,
        mutationFn: (data: InviteFormData) => api.post('/admin/iam/invite', { ...data, org_slug: org_slug ?? '' }),
        redirectTo: '/admin/iam',
        onError: (error) => {
            if (isAxiosError(error) && error.response?.status === 422) {
                const errors = error.response.data.errors as Record<string, string[]>;
                const extra: Record<string, string> = {};
                if (errors.invite) extra.invite = errors.invite[0];
                if (errors.session) extra.session = errors.session[0];
                setNonFieldErrors(extra);
            }
        },
    });

    return (
        <Layout
            breadcrumbs={[
                { title: t('iam.title', 'IAM'), href: '/admin/iam' },
                { title: t('iam.invite', 'Invite'), href: '/admin/iam/invite/create' },
            ]}
        >
            <Head title={t('iam.inviteMembers', 'Invite Members')} />

            <Flex vertical gap={24}>
                <Flex justify="space-between" align="center">
                    <Flex vertical>
                        <Typography.Title level={4}>
                            {t('iam.inviteMembers', 'Invite Members')}
                        </Typography.Title>
                        <Typography.Text type="secondary">
                            {invite_org
                                ? t(
                                      'iam.inviteSubtitleOrg',
                                      'Invite people to join {{org}}.',
                                      { org: invite_org.name },
                                  )
                                : t(
                                      'iam.inviteSubtitle',
                                      'Send email invitations to new members.',
                                  )}
                        </Typography.Text>
                    </Flex>
                    <IamBreadcrumb
                        segments={[
                            { label: t('iam.invite', 'Invite') },
                        ]}
                    />
                </Flex>

                <Form
                    form={form}
                    layout="vertical"
                    initialValues={{ branch_id: undefined, emails_raw: '', role: 'member' }}
                    onFinish={(values) => mutation.mutate(values)}
                >
                    <Row>
                        <Col xs={24} lg={16}>
                            <Flex vertical gap={24}>
                                <Card title={t('iam.inviteBranch', 'Branch')}>
                                    <Flex vertical gap={16}>
                                        {branches.length === 0 ? (
                                            <Typography.Text type="secondary">
                                                {t(
                                                    'iam.noBranchesAvailable',
                                                    'No branches available. Make sure you are logged in with an organization selected.',
                                                )}
                                            </Typography.Text>
                                        ) : (
                                            <Form.Item
                                                name="branch_id"
                                                label={t('iam.selectBranch', 'Select Branch')}
                                            >
                                                <Select
                                                    placeholder={t('iam.selectBranch', 'Select Branch')}
                                                    options={branches.map((branch) => ({
                                                        value: branch.id,
                                                        label: `${branch.name}${branch.is_headquarters ? ' (HQ)' : ''}`,
                                                    }))}
                                                />
                                            </Form.Item>
                                        )}
                                    </Flex>
                                </Card>

                                <Card
                                    title={
                                        <Flex align="center" gap={8}>
                                            <Mail size={16} />
                                            <span>{t('iam.inviteEmails', 'Email Addresses')}</span>
                                        </Flex>
                                    }
                                >
                                    <Flex vertical gap={16}>
                                        <Form.Item
                                            name="emails_raw"
                                            label={t('iam.inviteEmailsLabel', 'Emails (one per line or comma-separated)')}
                                            extra={t('iam.inviteMaxEmails', 'Maximum 50 invitations per send.')}
                                        >
                                            <Input.TextArea
                                                placeholder={t(
                                                    'iam.inviteEmailsPlaceholder',
                                                    'alice@example.com\nbob@example.com',
                                                )}
                                                rows={5}
                                            />
                                        </Form.Item>

                                        <Form.Item
                                            name="role"
                                            label={t('iam.inviteRole', 'Role')}
                                        >
                                            <Select
                                                options={available_roles.map((role) => ({
                                                    value: role,
                                                    label: role.charAt(0).toUpperCase() + role.slice(1),
                                                }))}
                                            />
                                        </Form.Item>

                                        {nonFieldErrors.invite && (
                                            <Typography.Text type="danger">{nonFieldErrors.invite}</Typography.Text>
                                        )}
                                        {nonFieldErrors.session && (
                                            <Typography.Text type="danger">{nonFieldErrors.session}</Typography.Text>
                                        )}
                                    </Flex>
                                </Card>

                                <Flex justify="end" gap={8}>
                                    <Button
                                        type="default"
                                        onClick={() => window.history.back()}
                                    >
                                        {t('iam.cancel', 'Cancel')}
                                    </Button>
                                    <Button
                                        type="primary"
                                        htmlType="submit"
                                        disabled={branches.length === 0}
                                        loading={mutation.isPending}
                                        icon={<Send size={16} />}
                                    >
                                        {t('iam.sendInvitations', 'Send Invitations')}
                                    </Button>
                                </Flex>
                            </Flex>
                        </Col>
                    </Row>
                </Form>
            </Flex>
        </Layout>
    );
}
