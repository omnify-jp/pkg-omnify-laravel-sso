import { Head } from '@inertiajs/react';
import { useIamLayout } from '@omnify-core/contexts/iam-layout-context';
import { useFormMutation } from '@omnify-core/hooks';
import { api } from '@omnify-core/services/api';
import { Button, Card, Col, Flex, Form, Radio, Row, Select, Space, Typography } from 'antd';
import { useTranslation } from 'react-i18next';

import { IamBreadcrumb } from '../../../components/access/iam-breadcrumb';
import type { IamBranch, IamOrganization, IamRole, IamUser, ScopeType } from '../../../types/iam';

type Props = {
    users: IamUser[];
    roles: IamRole[];
    organizations: IamOrganization[];
    branches: IamBranch[];
    default_scope?: ScopeType;
    default_scope_id?: string | null;
};

type AssignmentFormData = {
    user_id: string;
    role_id: string;
    scope_type: ScopeType;
    organization_id: string;
    branch_id: string;
};

const SCOPE_OPTIONS: { value: ScopeType; label: string }[] = [
    { value: 'global', label: 'Global' },
    { value: 'org-wide', label: 'Organization' },
    { value: 'branch', label: 'Branch' },
];

export default function IamAssignmentCreate({
    users,
    roles,
    organizations,
    branches,
    default_scope = 'global',
    default_scope_id = null,
}: Props) {
    const Layout = useIamLayout();
    const { t } = useTranslation();
    const [form] = Form.useForm<AssignmentFormData>();
    const scopeType = Form.useWatch('scope_type', form);

    const mutation = useFormMutation({
        form,
        mutationFn: (data: AssignmentFormData) => api.post('/admin/iam/assignments', data),
        redirectTo: '/admin/iam/assignments',
    });

    const scopeEntities = () => {
        if (scopeType === 'org-wide') {
            return organizations.map((o) => ({ id: o.id, name: o.name }));
        }
        if (scopeType === 'branch') {
            return branches.map((b) => ({ id: b.id, name: b.name }));
        }
        return [];
    };

    return (
        <Layout
            breadcrumbs={[
                { title: t('iam.title', 'IAM'), href: '/admin/iam' },
                { title: t('iam.assignments', 'Assignments'), href: '/admin/iam/assignments' },
                {
                    title: t('iam.createAssignment', 'Create Assignment'),
                    href: '/admin/iam/assignments/create',
                },
            ]}
        >
            <Head title={t('iam.createAssignment', 'Create Assignment')} />

            <Flex vertical gap={24}>
                <Flex justify="space-between" align="center">
                    <Flex vertical>
                        <Typography.Title level={4}>
                            {t('iam.createAssignment', 'Create Assignment')}
                        </Typography.Title>
                        <Typography.Text type="secondary">
                            {t(
                                'iam.createAssignmentSubtitle',
                                'Assign a role to a user at a specific scope.',
                            )}
                        </Typography.Text>
                    </Flex>
                    <IamBreadcrumb
                        segments={[
                            {
                                label: t('iam.assignments', 'Assignments'),
                                href: '/admin/iam/assignments',
                            },
                            { label: t('iam.createAssignment', 'Create Assignment') },
                        ]}
                    />
                </Flex>

                <Form
                    form={form}
                    layout="vertical"
                    initialValues={{
                        user_id: undefined,
                        role_id: undefined,
                        scope_type: default_scope,
                        organization_id: default_scope === 'org-wide' ? (default_scope_id ?? undefined) : undefined,
                        branch_id: default_scope === 'branch' ? (default_scope_id ?? undefined) : undefined,
                    }}
                    onValuesChange={(changedValues) => {
                        if ('scope_type' in changedValues) {
                            form.setFieldsValue({ organization_id: undefined, branch_id: undefined });
                        }
                    }}
                    onFinish={(values) => mutation.mutate(values)}
                >
                    <Row>
                        <Col xs={24} lg={16}>
                            <Flex vertical gap={24}>
                                <Card title={t('iam.userAndRole', 'User & Role')}>
                                    <Flex vertical gap={16}>
                                        <Form.Item
                                            name="user_id"
                                            label={t('iam.selectUser', 'Select User')}
                                        >
                                            <Select
                                                placeholder={t('iam.selectUser', 'Select User')}
                                                options={users.map((user) => ({
                                                    value: user.id,
                                                    label: `${user.name} (${user.email})`,
                                                }))}
                                            />
                                        </Form.Item>

                                        <Form.Item
                                            name="role_id"
                                            label={t('iam.selectRole', 'Select Role')}
                                        >
                                            <Select
                                                placeholder={t('iam.selectRole', 'Select Role')}
                                                options={roles.map((role) => ({
                                                    value: role.id,
                                                    label: `Lv.${role.level} ${role.name}`,
                                                }))}
                                            />
                                        </Form.Item>
                                    </Flex>
                                </Card>

                                <Card title={t('iam.scopeType', 'Scope')}>
                                    <Flex vertical gap={16}>
                                        <Form.Item
                                            name="scope_type"
                                            label={t('iam.scopeType', 'Scope Type')}
                                        >
                                            <Radio.Group>
                                                <Space direction="horizontal" size="middle">
                                                    {SCOPE_OPTIONS.map((opt) => (
                                                        <Radio key={opt.value} value={opt.value}>
                                                            {opt.label}
                                                        </Radio>
                                                    ))}
                                                </Space>
                                            </Radio.Group>
                                        </Form.Item>

                                        {scopeType === 'org-wide' && (
                                            <Form.Item
                                                name="organization_id"
                                                label={t('iam.selectOrganization', 'Select Organization')}
                                            >
                                                <Select
                                                    placeholder={t('iam.selectScopeEntity', 'Select\u2026')}
                                                    options={scopeEntities().map((entity) => ({
                                                        value: entity.id,
                                                        label: entity.name,
                                                    }))}
                                                />
                                            </Form.Item>
                                        )}

                                        {scopeType === 'branch' && (
                                            <Form.Item
                                                name="branch_id"
                                                label={t('iam.selectBranch', 'Select Branch')}
                                            >
                                                <Select
                                                    placeholder={t('iam.selectScopeEntity', 'Select\u2026')}
                                                    options={scopeEntities().map((entity) => ({
                                                        value: entity.id,
                                                        label: entity.name,
                                                    }))}
                                                />
                                            </Form.Item>
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
                                    <Button type="primary" htmlType="submit" loading={mutation.isPending}>
                                        {t('iam.save', 'Save')}
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
