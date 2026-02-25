import {
    Avatar, AvatarFallback, Button, Card,
    CardContent, CardHeader, CardTitle, ScopeTypeBadge,
} from '@omnifyjp/ui';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Globe, Network, Shield, ShieldCheck, Users } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useIamLayout } from '@omnify-sso/contexts/iam-layout-context';

import { IamBreadcrumb } from '../../../components/access/iam-breadcrumb';
import { formatScopeLocation, toScopeBadgeType } from '../../../utils/scope-utils';
import type { IamRoleAssignment } from '../../../types/iam';

type OverviewStats = {
    total_users: number;
    total_roles: number;
    total_permissions: number;
    global_roles: number;
    org_scoped_roles: number;
};

type RecentAssignment = {
    user: { id: string; name: string };
    role: { id: string; name: string };
    scope_type: 'global' | 'org-wide' | 'branch';
    organization_name?: string | null;
    branch_name?: string | null;
};

type Props = {
    stats: OverviewStats;
    recent_assignments: RecentAssignment[];
};

export default function IamOverview({ stats, recent_assignments }: Props) {
    const Layout = useIamLayout();
    const { t } = useTranslation();

    const statCards = [
        {
            label: t('iam.totalUsers', 'Total Users'),
            value: stats.total_users,
            icon: Users,
            color: 'text-blue-600 dark:text-blue-400',
            bg: 'bg-blue-50 dark:bg-blue-500/15',
        },
        {
            label: t('iam.totalRoles', 'Total Roles'),
            value: stats.total_roles,
            icon: Shield,
            color: 'text-purple-600 dark:text-purple-400',
            bg: 'bg-purple-50 dark:bg-purple-500/15',
        },
        {
            label: t('iam.globalRoles', 'Global Roles'),
            value: stats.global_roles,
            icon: Globe,
            color: 'text-amber-600 dark:text-amber-400',
            bg: 'bg-amber-50 dark:bg-amber-500/15',
        },
        {
            label: t('iam.totalPermissions', 'Permissions'),
            value: stats.total_permissions,
            icon: ShieldCheck,
            color: 'text-green-600 dark:text-green-400',
            bg: 'bg-green-50 dark:bg-green-500/15',
        },
    ];

    const quickLinks = [
        { href: '/admin/iam/users', label: t('iam.manageUsers', 'Manage Users'), icon: Users },
        { href: '/admin/iam/roles', label: t('iam.manageRoles', 'Manage Roles'), icon: Shield },
        { href: '/admin/iam/assignments', label: t('iam.manageAssignments', 'Assignments'), icon: ShieldCheck },
        { href: '/admin/iam/scope-explorer', label: t('iam.scopeExplorer', 'Scope Explorer'), icon: Network },
        { href: '/admin/iam/permissions', label: t('iam.managePermissions', 'Permissions'), icon: ShieldCheck },
    ];

    return (
        <Layout
            breadcrumbs={[
                { title: t('iam.title', 'IAM'), href: '/admin/iam' },
            ]}
        >
            <Head title={t('iam.title', 'IAM')} />

            <div className="space-y-section">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-page-title font-semibold">{t('iam.title', 'Identity & Access Management')}</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('iam.subtitle', 'Manage users, roles, and permissions across your organization.')}
                        </p>
                    </div>
                    <IamBreadcrumb segments={[]} />
                </div>

                {/* Stats */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {statCards.map((stat) => {
                        const Icon = stat.icon;
                        return (
                            <Card key={stat.label}>
                                <CardContent className="px-card pb-card pt-card">
                                    <div className="flex items-center gap-3">
                                        <div className={`rounded-lg p-2 ${stat.bg} ${stat.color}`}>
                                            <Icon className="h-5 w-5" />
                                        </div>
                                        <div>
                                            <p className="text-2xl font-semibold">{stat.value}</p>
                                            <p className="text-xs text-muted-foreground">{stat.label}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>

                <div className="grid grid-cols-1 gap-section lg:grid-cols-2">
                    {/* Quick Links */}
                    <Card>
                        <CardHeader className="px-card pb-3 pt-card">
                            <CardTitle className="text-base">{t('iam.quickLinks', 'Quick Links')}</CardTitle>
                        </CardHeader>
                        <CardContent className="px-card pb-card space-y-2">
                            {quickLinks.map((link) => {
                                const Icon = link.icon;
                                return (
                                    <Button key={link.href} variant="outline" className="w-full justify-between" asChild>
                                        <Link href={link.href}>
                                            <span className="flex items-center gap-2">
                                                <Icon className="h-4 w-4" />
                                                {link.label}
                                            </span>
                                            <ArrowRight className="h-4 w-4" />
                                        </Link>
                                    </Button>
                                );
                            })}
                        </CardContent>
                    </Card>

                    {/* Recent Assignments */}
                    <Card>
                        <CardHeader className="px-card pb-3 pt-card">
                            <CardTitle className="text-base">{t('iam.recentAssignments', 'Recent Assignments')}</CardTitle>
                        </CardHeader>
                        <CardContent className="px-card pb-card">
                            {recent_assignments.length === 0 ? (
                                <p className="text-sm text-muted-foreground">{t('iam.noAssignments', 'No assignments yet.')}</p>
                            ) : (
                                <div className="space-y-3">
                                    {recent_assignments.map((assignment, index) => (
                                        <Link
                                            key={index}
                                            href={`/admin/iam/users/${assignment.user.id}`}
                                            className="flex items-center gap-3 rounded-md p-2 transition-colors hover:bg-accent"
                                        >
                                            <Avatar className="h-8 w-8">
                                                <AvatarFallback className="text-xs">
                                                    {assignment.user.name.slice(0, 2).toUpperCase()}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-medium">{assignment.user.name}</p>
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {assignment.role.name} @ {formatScopeLocation(assignment)}
                                                </p>
                                            </div>
                                            <ScopeTypeBadge
                                                type={toScopeBadgeType(assignment.scope_type)}
                                                label={assignment.scope_type}
                                            />
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </Layout>
    );
}
