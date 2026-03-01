import { Link, router, usePage } from '@inertiajs/react';
import { DownOutlined } from '@ant-design/icons';
import { Avatar, Breadcrumb, Button, ConfigProvider, Drawer, Dropdown, Flex, Grid, Layout, Menu, Tag, Typography, theme } from 'antd';
import type { MenuProps } from 'antd';
import {
    Building2,
    ChevronLeft,
    ChevronRight,
    LayoutDashboard,
    LogOut,
    MapPin,
    Menu as MenuIcon,
    Palette,
    Settings,
    ShieldAlert,
    Store,
    Users,
} from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { adminColors, adminTheme } from '@omnify-core/lib/antd-theme';
import type { PageLayoutProps } from '@omnify-core/contexts/page-layout-context';
import { api } from '@omnify-core/services/api';

const SIDER_WIDTH = 240;
const SIDER_COLLAPSED_WIDTH = 64;

function getInitials(name: string): string {
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return name.slice(0, 2).toUpperCase();
}

function isPathActive(url: string, href: string): boolean {
    const basePath = href.replace(/\/overview$/, '');
    return url === href || url.startsWith(basePath + '/') || url === basePath;
}

function SiderContent({ collapsed, toggleSidebar, onNavigate }: {
    collapsed: boolean;
    toggleSidebar: () => void;
    onNavigate: () => void;
}) {
    const { t } = useTranslation();
    const { url } = usePage();
    const { token } = theme.useToken();

    const menuItems: MenuProps['items'] = useMemo(() => [
        {
            key: '/admin',
            icon: <LayoutDashboard size={16} />,
            label: <Link href="/admin">{t('admin.nav.dashboard', 'Dashboard')}</Link>,
        },
        { type: 'divider' as const },
        {
            key: 'org',
            icon: <Building2 size={16} />,
            label: t('admin.nav.orgManagement', 'Organizations'),
            children: [
                { key: '/admin/organizations', icon: <Building2 size={16} />, label: <Link href="/admin/organizations">{t('admin.nav.organizations', 'Organizations')}</Link> },
                { key: '/admin/branches', icon: <Store size={16} />, label: <Link href="/admin/branches">{t('admin.nav.branches', 'Branches')}</Link> },
                { key: '/admin/locations', icon: <MapPin size={16} />, label: <Link href="/admin/locations">{t('admin.nav.locations', 'Locations')}</Link> },
                { key: '/admin/brands', icon: <Palette size={16} />, label: <Link href="/admin/brands">{t('admin.nav.brands', 'Brands')}</Link> },
            ],
        },
        {
            key: '/admin/users',
            icon: <Users size={16} />,
            label: <Link href="/admin/users/create">{t('admin.nav.users', 'Users')}</Link>,
        },
    ], [t]);

    const allPaths = useMemo(() => {
        const paths: string[] = [];
        const extract = (items: MenuProps['items']) => {
            items?.forEach((item) => {
                if (item && 'key' in item && typeof item.key === 'string' && item.key.startsWith('/')) {
                    paths.push(item.key);
                }
                if (item && 'children' in item && item.children) {
                    extract(item.children);
                }
            });
        };
        extract(menuItems);
        return paths;
    }, [menuItems]);

    const selectedKeys = allPaths.filter((path) => isPathActive(url, path));

    const defaultOpenKeys = useMemo(() => {
        const openKeys: string[] = [];
        menuItems?.forEach((item) => {
            if (item && 'children' in item && item.children) {
                const hasActive = item.children.some(
                    (child) => child && 'key' in child && typeof child.key === 'string' && isPathActive(url, child.key),
                );
                if (hasActive && 'key' in item && typeof item.key === 'string') {
                    openKeys.push(item.key);
                }
            }
        });
        return openKeys;
    }, [menuItems, url]);

    return (
        <Flex vertical style={{ height: '100%' }}>
            {/* Logo */}
            <Link href="/admin" style={{ textDecoration: 'none' }}>
                <Flex
                    align="center"
                    gap="small"
                    style={{
                        flexShrink: 0,
                        height: token.Layout?.headerHeight ?? 48,
                        padding: collapsed ? '0' : '0 16px',
                        justifyContent: collapsed ? 'center' : 'flex-start',
                        borderBottom: '1px solid rgba(255, 255, 255, 0.08)',
                    }}
                >
                    <Avatar size={32} shape="square" icon={<ShieldAlert size={16} />} style={{ backgroundColor: adminColors.primary, flexShrink: 0 }} />
                    {!collapsed && (
                        <Flex align="center" gap="small">
                            <Typography.Text strong style={{ color: adminColors.text }}>Admin</Typography.Text>
                            <Tag color={adminColors.primary}>GOD</Tag>
                        </Flex>
                    )}
                </Flex>
            </Link>

            {/* Navigation */}
            <Flex flex={1} style={{ overflow: 'auto', width: '100%' }}>
                <Menu
                    mode="inline"
                    theme="dark"
                    selectedKeys={selectedKeys}
                    defaultOpenKeys={defaultOpenKeys}
                    items={menuItems}
                    style={{ borderInlineEnd: 'none', width: '100%' }}
                    onClick={onNavigate}
                />
            </Flex>

            {/* Collapse toggle */}
            <div style={{ flexShrink: 0 }}>
                <Button
                    type="text"
                    block
                    onClick={toggleSidebar}
                    style={{ color: adminColors.textMuted }}
                    icon={collapsed ? <ChevronRight size={16} /> : <ChevronLeft size={16} />}
                />
            </div>
        </Flex>
    );
}

export default function AdminAppLayout({ children, breadcrumbs, title, subtitle, extra }: PageLayoutProps) {
    const screens = Grid.useBreakpoint();
    const isMobile = !screens.lg;
    const { token } = theme.useToken();
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const user = auth as { user?: { name: string; email: string }; is_admin?: boolean };
    const admin = user?.user;

    const [collapsed, setCollapsed] = useState(() => {
        try {
            const stored = localStorage.getItem('admin_sidebar_collapsed');
            if (stored !== null) return stored === '1';
        } catch { /* localStorage unavailable */ }
        return false;
    });
    const [mobileOpen, setMobileOpen] = useState(false);

    const toggleSidebar = useCallback(() => {
        setCollapsed((prev) => {
            const next = !prev;
            try {
                localStorage.setItem('admin_sidebar_collapsed', next ? '1' : '0');
            } catch { /* localStorage unavailable */ }
            return next;
        });
    }, []);

    const handleLogout = useCallback(() => {
        api.post('/admin/logout').then(() => {
            router.visit('/admin/login');
        });
    }, []);

    const userMenuItems: MenuProps['items'] = admin
        ? [
              {
                  key: 'header',
                  type: 'group',
                  label: (
                      <div>
                          <Typography.Text strong>{admin.name}</Typography.Text>
                          <br />
                          <Typography.Text type="secondary">{admin.email}</Typography.Text>
                      </div>
                  ),
              },
              { type: 'divider' },
              {
                  key: 'settings',
                  icon: <Settings size={16} />,
                  label: t('admin.layout.settings', 'Settings'),
              },
              { type: 'divider' },
              {
                  key: 'logout',
                  icon: <LogOut size={16} />,
                  label: t('admin.layout.logout', 'Logout'),
                  danger: true,
              },
          ]
        : [];

    const handleUserMenuClick: MenuProps['onClick'] = ({ key }) => {
        if (key === 'settings') router.visit('/settings');
        if (key === 'logout') handleLogout();
    };

    const breadcrumbItems = breadcrumbs?.map((crumb, i) => ({
        key: crumb.href,
        title: i === (breadcrumbs.length - 1)
            ? crumb.title
            : <Link href={crumb.href}>{crumb.title}</Link>,
    }));

    return (
        <ConfigProvider theme={adminTheme}>
            <Layout style={{ minHeight: '100vh' }}>
                {!isMobile && (
                    <Layout.Sider
                        collapsed={collapsed}
                        width={SIDER_WIDTH}
                        collapsedWidth={SIDER_COLLAPSED_WIDTH}
                        trigger={null}
                        style={{
                            position: 'fixed',
                            inset: 0,
                            right: 'auto',
                            zIndex: 30,
                        }}
                    >
                        <SiderContent
                            collapsed={collapsed}
                            toggleSidebar={toggleSidebar}
                            onNavigate={() => {}}
                        />
                    </Layout.Sider>
                )}

                <Layout style={{
                    marginLeft: isMobile ? 0 : (collapsed ? SIDER_COLLAPSED_WIDTH : SIDER_WIDTH),
                    transition: 'margin-left 0.2s',
                }}>
                    <Layout.Header style={{
                        position: 'sticky',
                        top: 0,
                        zIndex: 20,
                        borderBottom: `1px solid ${token.colorBorderSecondary}`,
                    }}>
                        <Flex align="center" justify="space-between" gap="middle" style={{ height: '100%' }}>
                            <Flex align="center" gap="small">
                                {isMobile && (
                                    <Button
                                        type="text"
                                        icon={<MenuIcon size={20} />}
                                        onClick={() => setMobileOpen(true)}
                                    />
                                )}
                                <Tag icon={<ShieldAlert size={12} />} color={adminColors.primary}>
                                    {t('admin.layout.godModeHeaderBadge', 'GOD MODE')}
                                </Tag>
                            </Flex>
                            {admin && (
                                <Dropdown menu={{ items: userMenuItems, onClick: handleUserMenuClick }} trigger={['click']}>
                                    <Flex align="center" gap="small" style={{ cursor: 'pointer' }}>
                                        <Avatar size={24}>{getInitials(admin.name)}</Avatar>
                                        <Typography.Text>{admin.name}</Typography.Text>
                                        <DownOutlined style={{ fontSize: 10 }} />
                                    </Flex>
                                </Dropdown>
                            )}
                        </Flex>
                    </Layout.Header>

                    <Layout.Content style={{ padding: '16px 24px' }}>
                        {breadcrumbItems && breadcrumbItems.length > 0 && (
                            <Breadcrumb items={breadcrumbItems} style={{ marginBottom: 16 }} />
                        )}
                        <Flex vertical gap="large">
                            {(title || extra) && (
                                <Flex justify="space-between" align="start">
                                    <Flex vertical gap={token.paddingXXS / 2}>
                                        {title && <Typography.Title level={4}>{title}</Typography.Title>}
                                        {subtitle && <Typography.Text type="secondary">{subtitle}</Typography.Text>}
                                    </Flex>
                                    {extra}
                                </Flex>
                            )}
                            {children}
                        </Flex>
                    </Layout.Content>
                </Layout>

                <Drawer
                    open={mobileOpen}
                    onClose={() => setMobileOpen(false)}
                    placement="left"
                    size="default"
                    styles={{ body: { padding: 0, backgroundColor: adminColors.bg }, header: { display: 'none' } }}
                    closable={false}
                >
                    <SiderContent
                        collapsed={false}
                        toggleSidebar={toggleSidebar}
                        onNavigate={() => setMobileOpen(false)}
                    />
                </Drawer>
            </Layout>
        </ConfigProvider>
    );
}
