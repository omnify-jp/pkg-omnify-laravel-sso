import { Head, Link } from '@inertiajs/react';
import { useIamLayout } from '@omnify-core/contexts/iam-layout-context';
import { useFormMutation } from '@omnify-core/hooks';
import { api } from '@omnify-core/services/api';
import { Button, Card, Col, Flex, Form, Input, InputNumber, Row, Typography } from 'antd';
import { isAxiosError } from 'axios';
import { ArrowLeft } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { IamBreadcrumb } from '../../../components/access/iam-breadcrumb';
import { PermissionGrid } from '../../../components/access/permission-grid';
import type { IamPermission, IamRole } from '../../../types/iam';
import { buildPermissionModules, fromGridIds, toGridIds } from '../../../utils/scope-utils';

type Props = {
    role: IamRole;
    permissions: IamPermission[];
    all_permissions: IamPermission[];
};

type RoleFormValues = {
    name: string;
    description: string;
    level: number;
};

type RolePayload = RoleFormValues & {
    permission_ids: string[];
};

export default function IamRoleEdit({ role, permissions, all_permissions }: Props) {
    const Layout = useIamLayout();
    const { t } = useTranslation();
    const [form] = Form.useForm<RoleFormValues>();

    const currentPermissionIds = permissions.map((p) => p.id);

    const permissionModules = useMemo(
        () => buildPermissionModules(all_permissions),
        [all_permissions],
    );

    const [selectedGridIds, setSelectedGridIds] = useState<string[]>(() =>
        toGridIds(all_permissions, currentPermissionIds),
    );
    const [permissionError, setPermissionError] = useState<string | null>(null);

    const handlePermissionChange = (gridIds: string[]) => {
        setSelectedGridIds(gridIds);
        setPermissionError(null);
    };

    const mutation = useFormMutation({
        form,
        mutationFn: (data: RolePayload) => api.put(`/admin/iam/roles/${role.id}`, data),
        redirectTo: `/admin/iam/roles/${role.id}`,
        onError: (error) => {
            if (isAxiosError(error) && error.response?.status === 422) {
                const errors = error.response.data.errors as Record<string, string[]>;
                if (errors.permission_ids) {
                    setPermissionError(errors.permission_ids[0]);
                }
            }
        },
    });

    const handleFinish = (values: RoleFormValues) => {
        mutation.mutate({
            ...values,
            permission_ids: fromGridIds(all_permissions, selectedGridIds),
        });
    };

    return (
        <Layout
            breadcrumbs={[
                { title: t('iam.title', 'IAM'), href: '/admin/iam' },
                { title: t('iam.roles', 'Roles'), href: '/admin/iam/roles' },
                { title: role.name, href: `/admin/iam/roles/${role.id}` },
                { title: t('iam.edit', 'Edit'), href: `/admin/iam/roles/${role.id}/edit` },
            ]}
        >
            <Head title={`${t('iam.edit', 'Edit')} — ${role.name}`} />

            <Flex vertical gap={24}>
                <Flex justify="space-between" align="center">
                    <Link href={`/admin/iam/roles/${role.id}`}>
                        <Button type="text" size="small" icon={<ArrowLeft size={16} />}>
                            {t('iam.backToRole', 'Back to Role')}
                        </Button>
                    </Link>
                    <IamBreadcrumb
                        segments={[
                            { label: t('iam.roles', 'Roles'), href: '/admin/iam/roles' },
                            { label: role.name, href: `/admin/iam/roles/${role.id}` },
                            { label: t('iam.edit', 'Edit') },
                        ]}
                    />
                </Flex>

                <Form
                    form={form}
                    layout="vertical"
                    initialValues={{
                        name: role.name,
                        description: role.description ?? '',
                        level: role.level,
                    }}
                    onFinish={handleFinish}
                >
                    <Flex vertical gap={24}>
                        <Row>
                            <Col xs={24} lg={16}>
                                <Card title={t('iam.roleInfo', 'Role Information')}>
                                    <Flex vertical gap={16}>
                                        <Form.Item
                                            name="name"
                                            label={t('iam.roleName', 'Role Name')}
                                        >
                                            <Input />
                                        </Form.Item>

                                        <Form.Item
                                            name="description"
                                            label={t('iam.description', 'Description')}
                                        >
                                            <Input.TextArea />
                                        </Form.Item>

                                        <Form.Item
                                            name="level"
                                            label={t('iam.level', 'Level')}
                                            extra={t(
                                                'iam.levelHelp',
                                                'Lower number = higher privilege (1 = Admin).',
                                            )}
                                        >
                                            <InputNumber min={1} max={10} style={{ width: '100%' }} />
                                        </Form.Item>
                                    </Flex>
                                </Card>
                            </Col>
                        </Row>

                        <Card title={t('iam.permissionMatrix', 'Permission Matrix')}>
                            <PermissionGrid
                                modules={permissionModules}
                                selectedIds={selectedGridIds}
                                onChange={handlePermissionChange}
                                labels={{
                                    moduleHeader: t('iam.module', 'Module'),
                                    selectAll: t('iam.selectAll', 'All'),
                                }}
                            />
                            {permissionError && (
                                <Typography.Text type="danger">
                                    {permissionError}
                                </Typography.Text>
                            )}
                        </Card>

                        <Flex justify="end" gap={8}>
                            <Button
                                type="default"
                                onClick={() => window.history.back()}
                            >
                                {t('iam.cancel', 'Cancel')}
                            </Button>
                            <Button type="primary" htmlType="submit" loading={mutation.isPending}>
                                {t('iam.saveChanges', 'Save Changes')}
                            </Button>
                        </Flex>
                    </Flex>
                </Form>
            </Flex>
        </Layout>
    );
}
