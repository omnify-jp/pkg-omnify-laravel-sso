import { Avatar, Button, Card, Col, Empty, Flex, Row, Statistic, Typography, theme } from 'antd';
import { Link } from '@inertiajs/react';
import { useOrgRoute } from '@omnify-core/hooks/use-org-route';
import { ArrowRight, Globe, Network, Shield, ShieldCheck, Users } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { PageContainer } from '@omnify-core/components/page-container';

import { ScopeTypeBadge } from '@omnify-core/components/access/scope-type-badge';
import { formatScopeLocation, toScopeBadgeType } from '@omnify-core/utils/scope-utils';
import type { IamRoleAssignment } from '@omnify-core/types/iam';

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
    const { t } = useTranslation();
    const { token } = theme.useToken();
    const orgRoute = useOrgRoute();
    const iamBase = orgRoute('/settings/iam');

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
        { href: `${iamBase}/users`, label: t('iam.manageUsers', 'Manage Users'), icon: Users },
        { href: `${iamBase}/roles`, label: t('iam.manageRoles', 'Manage Roles'), icon: Shield },
        { href: `${iamBase}/assignments`, label: t('iam.manageAssignments', 'Assignments'), icon: ShieldCheck },
        { href: `${iamBase}/scope-explorer`, label: t('iam.scopeExplorer', 'Scope Explorer'), icon: Network },
        { href: `${iamBase}/permissions`, label: t('iam.managePermissions', 'Permissions'), icon: ShieldCheck },
    ];

    return (
        <PageContainer
            title={t('iam.title', 'Identity & Access Management')}
            subtitle={t('iam.subtitle', 'Manage users, roles, and permissions across your organization.')}
        >
            <Flex vertical gap={token.padding}>
                <Row gutter={[16, 16]}>
                    {statCards.map((stat) => {
                        const Icon = stat.icon;
                        return (
                            <Col key={stat.label} xs={24} sm={12} lg={6}>
                                <Card>
                                    <Statistic
                                        title={stat.label}
                                        value={stat.value}
                                        prefix={<Icon size={16} />}
                                    />
                                </Card>
                            </Col>
                        );
                    })}
                </Row>

                <Row gutter={[16, 16]}>
                    <Col xs={24} lg={12}>
                        <Card title={t('iam.quickLinks', 'Quick Links')}>
                            <Flex vertical gap={token.paddingXXS}>
                                {quickLinks.map((link) => {
                                    const Icon = link.icon;
                                    return (
                                        <Link key={link.href} href={link.href}>
                                            <Button type="text" block>
                                                <Flex align="center" gap="small" flex={1} justify="space-between">
                                                    <Flex align="center" gap="small">
                                                        <Icon size={16} />
                                                        <Typography.Text>{link.label}</Typography.Text>
                                                    </Flex>
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
                                <Empty description={t('iam.noAssignments', 'No assignments yet.')} />
                            ) : (
                                <Flex vertical gap={token.paddingXXS}>
                                    {recent_assignments.map((assignment, index) => (
                                        <Link
                                            key={index}
                                            href={`${iamBase}/users/${assignment.user.id}`}
                                        >
                                            <Button type="text" block>
                                                <Flex align="center" gap="small" flex={1}>
                                                    <Avatar size={28}>
                                                        {assignment.user.name.slice(0, 2).toUpperCase()}
                                                    </Avatar>
                                                    <Flex vertical flex={1} align="start" style={{ minWidth: 0 }}>
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
        </PageContainer>
    );
}
