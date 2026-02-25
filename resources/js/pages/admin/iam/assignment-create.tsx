import {
    Button, Card, CardContent, CardHeader,
    CardTitle, Label, Select, SelectContent,
    SelectItem, SelectTrigger, SelectValue,
} from '@omnifyjp/ui';
import { Head, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useIamLayout } from '@omnify-sso/contexts/iam-layout-context';

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

    const { data, setData, post, processing, errors } = useForm<{
        user_id: string;
        role_id: string;
        scope_type: ScopeType;
        organization_id: string;
        branch_id: string;
    }>({
        user_id: '',
        role_id: '',
        scope_type: default_scope,
        organization_id: default_scope === 'org-wide' ? (default_scope_id ?? '') : '',
        branch_id: default_scope === 'branch' ? (default_scope_id ?? '') : '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/iam/assignments');
    };

    const scopeEntities = () => {
        if (data.scope_type === 'org-wide') {
            return organizations.map((o) => ({ id: o.id, name: o.name }));
        }
        if (data.scope_type === 'branch') {
            return branches.map((b) => ({ id: b.id, name: b.name }));
        }
        return [];
    };

    const handleScopeTypeChange = (value: string) => {
        setData({
            ...data,
            scope_type: value as ScopeType,
            organization_id: '',
            branch_id: '',
        });
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

            <div className="space-y-section">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-page-title font-semibold">
                            {t('iam.createAssignment', 'Create Assignment')}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t(
                                'iam.createAssignmentSubtitle',
                                'Assign a role to a user at a specific scope.',
                            )}
                        </p>
                    </div>
                    <IamBreadcrumb
                        segments={[
                            {
                                label: t('iam.assignments', 'Assignments'),
                                href: '/admin/iam/assignments',
                            },
                            { label: t('iam.createAssignment', 'Create Assignment') },
                        ]}
                    />
                </div>

                <form onSubmit={handleSubmit} className="max-w-2xl space-y-section">
                    {/* User & Role */}
                    <Card>
                        <CardHeader className="px-card pb-3 pt-card">
                            <CardTitle className="text-base">
                                {t('iam.userAndRole', 'User & Role')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 px-card pb-card">
                            <div className="space-y-2">
                                <Label>{t('iam.selectUser', 'Select User')}</Label>
                                <Select
                                    value={data.user_id}
                                    onValueChange={(v) => setData('user_id', v)}
                                >
                                    <SelectTrigger>
                                        <SelectValue
                                            placeholder={t('iam.selectUser', 'Select User')}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {users.map((user) => (
                                            <SelectItem key={user.id} value={user.id}>
                                                {user.name}{' '}
                                                <span className="text-muted-foreground">
                                                    ({user.email})
                                                </span>
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.user_id && (
                                    <p className="text-sm text-destructive">{errors.user_id}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label>{t('iam.selectRole', 'Select Role')}</Label>
                                <Select
                                    value={data.role_id}
                                    onValueChange={(v) => setData('role_id', v)}
                                >
                                    <SelectTrigger>
                                        <SelectValue
                                            placeholder={t('iam.selectRole', 'Select Role')}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {roles.map((role) => (
                                            <SelectItem key={role.id} value={role.id}>
                                                <span className="flex items-center gap-2">
                                                    <span className="text-xs text-muted-foreground">
                                                        Lv.{role.level}
                                                    </span>
                                                    {role.name}
                                                </span>
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.role_id && (
                                    <p className="text-sm text-destructive">{errors.role_id}</p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Scope */}
                    <Card>
                        <CardHeader className="px-card pb-3 pt-card">
                            <CardTitle className="text-base">
                                {t('iam.scopeType', 'Scope')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 px-card pb-card">
                            <div className="space-y-2">
                                <Label>{t('iam.scopeType', 'Scope Type')}</Label>
                                <div className="grid grid-cols-3 gap-3">
                                    {SCOPE_OPTIONS.map((opt) => (
                                        <label
                                            key={opt.value}
                                            className={`flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-colors hover:bg-accent ${
                                                data.scope_type === opt.value
                                                    ? 'border-primary bg-primary/5'
                                                    : 'border-border'
                                            }`}
                                        >
                                            <input
                                                type="radio"
                                                name="scope_type"
                                                value={opt.value}
                                                checked={data.scope_type === opt.value}
                                                onChange={() => handleScopeTypeChange(opt.value)}
                                                className="accent-primary"
                                            />
                                            <span className="text-sm font-medium">
                                                {opt.label}
                                            </span>
                                        </label>
                                    ))}
                                </div>
                                {errors.scope_type && (
                                    <p className="text-sm text-destructive">{errors.scope_type}</p>
                                )}
                            </div>

                            {data.scope_type !== 'global' && (
                                <div className="space-y-2">
                                    <Label>
                                        {data.scope_type === 'org-wide'
                                            ? t('iam.selectOrganization', 'Select Organization')
                                            : t('iam.selectBranch', 'Select Branch')}
                                    </Label>
                                    <Select
                                        value={
                                            data.scope_type === 'org-wide'
                                                ? data.organization_id
                                                : data.branch_id
                                        }
                                        onValueChange={(v) => {
                                            if (data.scope_type === 'org-wide') {
                                                setData('organization_id', v);
                                            } else {
                                                setData('branch_id', v);
                                            }
                                        }}
                                    >
                                        <SelectTrigger>
                                            <SelectValue
                                                placeholder={t(
                                                    'iam.selectScopeEntity',
                                                    'Select…',
                                                )}
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {scopeEntities().map((entity) => (
                                                <SelectItem key={entity.id} value={entity.id}>
                                                    {entity.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {(errors.organization_id || errors.branch_id) && (
                                        <p className="text-sm text-destructive">
                                            {errors.organization_id || errors.branch_id}
                                        </p>
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <div className="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => window.history.back()}
                        >
                            {t('iam.cancel', 'Cancel')}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {t('iam.save', 'Save')}
                        </Button>
                    </div>
                </form>
            </div>
        </Layout>
    );
}
