import { Avatar, Button, Card, Flex, Form, Modal, Select, Typography } from 'antd';
import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import type { IamRole, IamUser, ScopeType } from '../../types/iam';
import { getScopeLabel } from '../../utils/scope-utils';

type AssignRoleDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    scopeType: ScopeType;
    scopeId: string | null;
    scopeLabel: string;
    users: IamUser[];
    roles: IamRole[];
    assignApiUrl: string;
};

export function AssignRoleDialog({
    open,
    onOpenChange,
    scopeType,
    scopeId,
    scopeLabel,
    users,
    roles,
    assignApiUrl,
}: AssignRoleDialogProps) {
    const { t } = useTranslation();
    const [userId, setUserId] = useState('');
    const [roleId, setRoleId] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const handleSubmit = () => {
        if (!userId || !roleId) {
            return;
        }

        setSubmitting(true);
        router.post(
            assignApiUrl,
            {
                user_id: userId,
                role_id: roleId,
                scope_type: scopeType,
                scope_id: scopeId,
            },
            {
                onSuccess: () => {
                    setUserId('');
                    setRoleId('');
                    onOpenChange(false);
                },
                onFinish: () => {
                    setSubmitting(false);
                },
            },
        );
    };

    const handleClose = () => {
        setUserId('');
        setRoleId('');
        onOpenChange(false);
    };

    return (
        <Modal
            open={open}
            onCancel={handleClose}
            title={t('iam.assignRole', 'Assign Role')}
            width={480}
            footer={
                <Flex justify="flex-end" gap={8}>
                    <Button onClick={handleClose}>
                        {t('common.cancel', 'Cancel')}
                    </Button>
                    <Button type="primary" onClick={handleSubmit} disabled={!userId || !roleId} loading={submitting}>
                        {t('common.assign', 'Assign')}
                    </Button>
                </Flex>
            }
        >
            <Typography.Text type="secondary">
                {t('iam.assignRoleDesc', 'Assign a role to a user at {{scope}}', {
                    scope: scopeLabel,
                })}
            </Typography.Text>

            {/* eslint-disable-next-line antd/require-form-prop -- layout-only Form, state managed by useState */}
            <Form layout="vertical">
                <Form.Item label={t('iam.selectUser', 'Select User')}>
                    <Select
                        value={userId || undefined}
                        onChange={setUserId}
                        placeholder={t('iam.selectUser', 'Select user')}
                        options={users.map((user) => ({
                            value: user.id,
                            label: (
                                <Flex align="center" gap={8}>
                                    <Avatar size={20}>
                                        {user.name.slice(0, 2).toUpperCase()}
                                    </Avatar>
                                    <span>{user.name}</span>
                                </Flex>
                            ),
                        }))}
                    />
                </Form.Item>

                <Form.Item label={t('iam.selectRole', 'Select Role')}>
                    <Select
                        value={roleId || undefined}
                        onChange={setRoleId}
                        placeholder={t('iam.selectRole', 'Select role')}
                        options={roles.map((role) => ({
                            value: role.id,
                            label: (
                                <Flex align="center" gap={8}>
                                    <Typography.Text type="secondary">Lv.{role.level}</Typography.Text>
                                    <span>{role.name}</span>
                                </Flex>
                            ),
                        }))}
                    />
                </Form.Item>
            </Form>

            <Card size="small">
                <Typography.Text type="secondary">{t('iam.scope', 'Scope')}: </Typography.Text>
                <Typography.Text strong>
                    {getScopeLabel(scopeType)} — {scopeLabel}
                </Typography.Text>
            </Card>
        </Modal>
    );
}
