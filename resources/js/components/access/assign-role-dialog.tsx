import {
    Avatar, AvatarFallback, Button, Dialog,
    DialogContent, DialogDescription, DialogHeader, DialogTitle,
    Label, Select, SelectContent, SelectItem,
    SelectTrigger, SelectValue,
} from '@omnifyjp/ui';
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

    const handleClose = (open: boolean) => {
        if (!open) {
            setUserId('');
            setRoleId('');
        }
        onOpenChange(open);
    };

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-[480px]">
                <DialogHeader>
                    <DialogTitle>{t('iam.assignRole', 'Assign Role')}</DialogTitle>
                    <DialogDescription>
                        {t('iam.assignRoleDesc', 'Assign a role to a user at {{scope}}', {
                            scope: scopeLabel,
                        })}
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    <div className="space-y-2">
                        <Label>{t('iam.selectUser', 'Select User')}</Label>
                        <Select value={userId} onValueChange={setUserId}>
                            <SelectTrigger>
                                <SelectValue placeholder={t('iam.selectUser', 'Select user')} />
                            </SelectTrigger>
                            <SelectContent>
                                {users.map((user) => (
                                    <SelectItem key={user.id} value={user.id}>
                                        <div className="flex items-center gap-2">
                                            <Avatar className="h-5 w-5">
                                                <AvatarFallback className="text-[10px]">
                                                    {user.name.slice(0, 2).toUpperCase()}
                                                </AvatarFallback>
                                            </Avatar>
                                            <span>{user.name}</span>
                                        </div>
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2">
                        <Label>{t('iam.selectRole', 'Select Role')}</Label>
                        <Select value={roleId} onValueChange={setRoleId}>
                            <SelectTrigger>
                                <SelectValue placeholder={t('iam.selectRole', 'Select role')} />
                            </SelectTrigger>
                            <SelectContent>
                                {roles.map((role) => (
                                    <SelectItem key={role.id} value={role.id}>
                                        <div className="flex items-center gap-2">
                                            <span className="text-xs text-muted-foreground">Lv.{role.level}</span>
                                            <span>{role.name}</span>
                                        </div>
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="rounded-lg border border-border bg-muted/50 p-3 text-sm">
                        <span className="text-muted-foreground">{t('iam.scope', 'Scope')}: </span>
                        <span className="font-medium">
                            {getScopeLabel(scopeType)} — {scopeLabel}
                        </span>
                    </div>
                </div>

                <div className="mt-4 flex justify-end gap-2">
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        {t('common.cancel', 'Cancel')}
                    </Button>
                    <Button onClick={handleSubmit} disabled={!userId || !roleId || submitting}>
                        {t('common.assign', 'Assign')}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
