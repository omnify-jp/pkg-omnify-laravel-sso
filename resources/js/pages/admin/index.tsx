import { Link, usePage } from '@inertiajs/react';
import { PageContainer } from '@omnify-core/components/page-container';
import { useOrgRoute } from '@omnify-core/hooks/use-org-route';
import { Avatar, Card, Col, Flex, Row, Typography } from 'antd';
import type { LucideIcon } from 'lucide-react';
import {
    ArrowRight,
    Building2,
    MapPin,
    MapPinned,
    ShieldCheck,
    Tag,
    UserPlus,
} from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

interface AdminIndexProps {
    stats: {
        userCount: number;
        organizationCount: number;
        brandCount: number;
        branchCount: number;
        locationCount: number;
        roleCount: number;
    };
}

type Section = {
    title: string;
    description: string;
    href: string;
    icon: LucideIcon;
    stat?: number;
    statLabel?: string;
};

export default function AdminIndex({ stats }: AdminIndexProps) {
    const { t } = useTranslation();
    const { auth_mode } = usePage<{ auth_mode: 'standalone' | 'console' }>().props;
    const isStandalone = auth_mode === 'standalone';
    const orgRoute = useOrgRoute();

    const sections = useMemo<Section[]>(() => {
        const items: Section[] = [];

        if (isStandalone) {
            items.push(
                {
                    title: t('admin.hub.organizations', 'Organizations'),
                    description: t('admin.hub.organizationsDesc', 'Create and manage organizations'),
                    href: '/admin/organizations',
                    icon: Building2,
                    stat: stats.organizationCount,
                    statLabel: t('admin.hub.activeOrgs', 'active'),
                },
                {
                    title: t('admin.hub.brands', 'Brands'),
                    description: t('admin.hub.brandsDesc', 'Manage brands across organizations'),
                    href: '/admin/brands',
                    icon: Tag,
                    stat: stats.brandCount,
                    statLabel: t('admin.hub.activeBrands', 'active'),
                },
                {
                    title: t('admin.hub.branches', 'Branches'),
                    description: t('admin.hub.branchesDesc', 'Manage branches across organizations'),
                    href: '/admin/branches',
                    icon: MapPin,
                    stat: stats.branchCount,
                    statLabel: t('admin.hub.activeBranches', 'active'),
                },
                {
                    title: t('admin.hub.locations', 'Locations'),
                    description: t('admin.hub.locationsDesc', 'Manage locations across branches'),
                    href: '/admin/locations',
                    icon: MapPinned,
                    stat: stats.locationCount,
                    statLabel: t('admin.hub.activeLocations', 'active'),
                },
            );
        }

        items.push({
            title: t('admin.hub.iam', 'Users & Roles'),
            description: t('admin.hub.iamDesc', 'Manage users, roles, and permissions'),
            href: orgRoute('/settings/iam'),
            icon: ShieldCheck,
            stat: stats.userCount,
            statLabel: t('admin.hub.users', 'users'),
        });

        if (isStandalone) {
            items.push({
                title: t('admin.hub.createUser', 'Create User'),
                description: t('admin.hub.createUserDesc', 'Add a new user to this instance'),
                href: '/admin/users/create',
                icon: UserPlus,
            });
        }

        return items;
    }, [t, isStandalone, stats, orgRoute]);

    return (
        <PageContainer
            title={t('admin.hub.title', 'Administration')}
            subtitle={t('admin.hub.subtitle', 'System configuration and user management')}
            breadcrumbs={[{ title: t('nav.admin', 'Admin'), href: '/admin' }]}
        >
            <Row gutter={[16, 16]}>
                {sections.map((section) => {
                    const Icon = section.icon;
                    return (
                        <Col key={section.href} xs={24} md={12}>
                            <Link href={section.href}>
                                <Card hoverable>
                                    <Flex gap={12} align="center">
                                        <Avatar
                                            size={28}
                                            shape="square"
                                            icon={<Icon size={16} />}
                                        />
                                        <Flex vertical flex={1}>
                                            <Typography.Text strong>{section.title}</Typography.Text>
                                            <Typography.Text type="secondary">
                                                {section.description}
                                            </Typography.Text>
                                        </Flex>
                                        {section.stat !== undefined && (
                                            <Typography.Text type="secondary">
                                                <Typography.Text strong>{section.stat}</Typography.Text> {section.statLabel}
                                            </Typography.Text>
                                        )}
                                        <ArrowRight size={14} />
                                    </Flex>
                                </Card>
                            </Link>
                        </Col>
                    );
                })}
            </Row>
        </PageContainer>
    );
}
