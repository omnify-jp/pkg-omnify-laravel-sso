import {
    Button, Card, CardContent, CardHeader,
    CardTitle, Label, Select, SelectContent,
    SelectItem, SelectTrigger, SelectValue, Textarea,
} from '@omnifyjp/ui';
import { Head, useForm } from '@inertiajs/react';
import { Mail, Send } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useIamLayout } from '@omnify-sso/contexts/iam-layout-context';

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

export default function IamInviteCreate({
    branches,
    invite_org,
    org_slug,
    available_roles,
}: Props) {
    const Layout = useIamLayout();
    const { t } = useTranslation();

    const { data, setData, post, processing, errors } = useForm<{
        org_slug: string;
        branch_id: string;
        emails_raw: string;
        role: string;
    }>({
        org_slug: org_slug ?? '',
        branch_id: '',
        emails_raw: '',
        role: 'member',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/iam/invite');
    };

    return (
        <Layout
            breadcrumbs={[
                { title: t('iam.title', 'IAM'), href: '/admin/iam' },
                { title: t('iam.invite', 'Invite'), href: '/admin/iam/invite/create' },
            ]}
        >
            <Head title={t('iam.inviteMembers', 'Invite Members')} />

            <div className="space-y-section">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-page-title font-semibold">
                            {t('iam.inviteMembers', 'Invite Members')}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
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
                        </p>
                    </div>
                    <IamBreadcrumb
                        segments={[
                            { label: t('iam.invite', 'Invite') },
                        ]}
                    />
                </div>

                <form onSubmit={handleSubmit} className="max-w-2xl space-y-section">
                    {/* Branch selection */}
                    <Card>
                        <CardHeader className="px-card pb-3 pt-card">
                            <CardTitle className="text-base">
                                {t('iam.inviteBranch', 'Branch')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 px-card pb-card">
                            {branches.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t(
                                        'iam.noBranchesAvailable',
                                        'No branches available. Make sure you are logged in with an organization selected.',
                                    )}
                                </p>
                            ) : (
                                <div className="space-y-2">
                                    <Label>{t('iam.selectBranch', 'Select Branch')}</Label>
                                    <Select
                                        value={data.branch_id}
                                        onValueChange={(v) => setData('branch_id', v)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue
                                                placeholder={t(
                                                    'iam.selectBranch',
                                                    'Select Branch',
                                                )}
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {branches.map((branch) => (
                                                <SelectItem key={branch.id} value={branch.id}>
                                                    <span className="flex items-center gap-2">
                                                        {branch.name}
                                                        {branch.is_headquarters && (
                                                            <span className="text-xs text-muted-foreground">
                                                                (HQ)
                                                            </span>
                                                        )}
                                                    </span>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.branch_id && (
                                        <p className="text-sm text-destructive">
                                            {errors.branch_id}
                                        </p>
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Emails + Role */}
                    <Card>
                        <CardHeader className="px-card pb-3 pt-card">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Mail className="h-4 w-4" />
                                {t('iam.inviteEmails', 'Email Addresses')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 px-card pb-card">
                            <div className="space-y-2">
                                <Label htmlFor="emails_raw">
                                    {t('iam.inviteEmailsLabel', 'Emails (one per line or comma-separated)')}
                                </Label>
                                <Textarea
                                    id="emails_raw"
                                    value={data.emails_raw}
                                    onChange={(e) => setData('emails_raw', e.target.value)}
                                    placeholder={t(
                                        'iam.inviteEmailsPlaceholder',
                                        'alice@example.com\nbob@example.com',
                                    )}
                                    rows={5}
                                    className="font-mono text-sm"
                                />
                                {errors.emails_raw && (
                                    <p className="text-sm text-destructive">{errors.emails_raw}</p>
                                )}
                                <p className="text-xs text-muted-foreground">
                                    {t('iam.inviteMaxEmails', 'Maximum 50 invitations per send.')}
                                </p>
                            </div>

                            <div className="space-y-2">
                                <Label>{t('iam.inviteRole', 'Role')}</Label>
                                <Select
                                    value={data.role}
                                    onValueChange={(v) => setData('role', v)}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {available_roles.map((role) => (
                                            <SelectItem key={role} value={role}>
                                                {role.charAt(0).toUpperCase() + role.slice(1)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.role && (
                                    <p className="text-sm text-destructive">{errors.role}</p>
                                )}
                            </div>

                            {errors.invite && (
                                <p className="text-sm text-destructive">{errors.invite}</p>
                            )}
                            {errors.session && (
                                <p className="text-sm text-destructive">{errors.session}</p>
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
                        <Button
                            type="submit"
                            disabled={processing || branches.length === 0}
                        >
                            <Send className="mr-2 h-4 w-4" />
                            {t('iam.sendInvitations', 'Send Invitations')}
                        </Button>
                    </div>
                </form>
            </div>
        </Layout>
    );
}
