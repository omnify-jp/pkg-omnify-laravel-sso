import { Avatar, Button, Card, Col, Flex, Row, Typography } from 'antd';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Globe, Network, Shield, ShieldCheck, Users } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useIamLayout } from '@omnify-core/contexts/iam-layout-context';

import { IamBreadcrumb } from '../../../components/access/iam-breadcrumb';
import { ScopeTypeBadge } from '../../../components/access/scope-type-badge';
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
        },
        {
            label: t('iam.totalRoles', 'Total Roles'),
            value: stats.total_roles,
            icon: Shield,
        },
        {
            label: t('iam.globalRoles', 'Global Roles'),
            value: stats.global_roles,
            icon: Globe,
        },
        {
            label: t('iam.totalPermissions', 'Permissions'),
            value: stats.total_permissions,
            icon: ShieldCheck,
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

            <Flex vertical gap={16}>
                <Flex justify="space-between" align="start">
                    <Flex vertical gap={2}>
                        <Typography.Title level={4}>
                            {t('iam.title', 'Identity & Access Management')}
                        </Typography.Title>
                        <Typography.Text type="secondary">
                            {t('iam.subtitle', 'Manage users, roles, and permissions across your organization.')}
                        </Typography.Text>
                    </Flex>
                    <IamBreadcrumb segments={[]} />
                </Flex>

                <Row gutter={[16, 16]}>
                    {statCards.map((stat) => {
                        const Icon = stat.icon;
                        return (
                            <Col key={stat.label} xs={24} sm={12} lg={6}>
                                <Card>
                                    <Flex align="center" gap={8}>
                                        <Icon size={16} />
                                        <Flex vertical>
                                            <Typography.Text type="secondary">{stat.label}</Typography.Text>
                                            <Typography.Title level={4} style={{ margin: 0 }}>{stat.value}</Typography.Title>
                                        </Flex>
                                    </Flex>
                                </Card>
                            </Col>
                        );
                    })}
                </Row>

                <Row gutter={[16, 16]}>
                    <Col xs={24} lg={12}>
                        <Card title={t('iam.quickLinks', 'Quick Links')}>
                            <Flex vertical gap={4}>
                                {quickLinks.map((link) => {
                                    const Icon = link.icon;
                                    return (
                                        <Link key={link.href} href={link.href}>
                                            <Button type="text" block style={{ height: 'auto', padding: '8px 12px', justifyContent: 'flex-start' }}>
                                                <Flex align="center" gap={8} style={{ width: '100%' }}>
                                                    <Icon size={16} />
                                                    <Typography.Text style={{ flex: 1, textAlign: 'left' }}>{link.label}</Typography.Text>
                                                    <ArrowRight size={14} />
                                                </Flex>
                                            </Button>
                                        </Link>
                                    );
                                })}
                            </Flex>
                        </Card>
                    </Col>

                    <Col xs={24} lg={12}>
                        <Card title={t('iam.recentAssignments', 'Recent Assignments')}>
                            {recent_assignments.length === 0 ? (
                                <Typography.Text type="secondary">
                                    {t('iam.noAssignments', 'No assignments yet.')}
                                </Typography.Text>
                            ) : (
                                <Flex vertical gap={4}>
                                    {recent_assignments.map((assignment, index) => (
                                        <Link
                                            key={index}
                                            href={`/admin/iam/users/${assignment.user.id}`}
                                        >
                                            <Button type="text" block style={{ height: 'auto', padding: '8px 12px', justifyContent: 'flex-start' }}>
                                                <Flex align="center" gap={8} style={{ width: '100%' }}>
                                                    <Avatar size={28}>
                                                        {assignment.user.name.slice(0, 2).toUpperCase()}
                                                    </Avatar>
                                                    <Flex vertical style={{ flex: 1, textAlign: 'left', minWidth: 0 }}>
                                                        <Typography.Text strong ellipsis>
                                                            {assignment.user.name}
                                                        </Typography.Text>
                                                        <Typography.Text type="secondary" ellipsis>
                                                            {assignment.role.name} @ {formatScopeLocation(assignment)}
                                                        </Typography.Text>
                                                    </Flex>
                                                    <ScopeTypeBadge
                                                        type={toScopeBadgeType(assignment.scope_type)}
                                                        label={assignment.scope_type}
                                                    />
                                                </Flex>
                                            </Button>
                                        </Link>
                                    ))}
                                </Flex>
                            )}
                        </Card>
                    </Col>
                </Row>
            </Flex>
        </Layout>
    );
}
