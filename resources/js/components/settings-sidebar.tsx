import { router, usePage } from '@inertiajs/react';
import { Menu } from 'antd';
import type { MenuProps } from 'antd';
import { Lock, ShieldCheck } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export function SettingsSidebar() {
    const { t } = useTranslation();
    const { url } = usePage();

    const menuItems: MenuProps['items'] = [
        {
            key: '/settings/account',
            icon: <Lock size={16} />,
            label: t('settings.tabs.account', 'Account'),
        },
        {
            key: '/settings/security',
            icon: <ShieldCheck size={16} />,
            label: t('settings.tabs.security', 'Security'),
        },
    ];

    // Determine active key from current URL
    const getSelectedKey = (): string[] => {
        if (url.startsWith('/settings/security')) return ['/settings/security'];
        if (url.startsWith('/settings/account')) return ['/settings/account'];
        return ['/settings/account'];
    };

    const handleClick: MenuProps['onClick'] = ({ key }) => {
        router.visit(key);
    };

    return (
        <Menu
            mode="inline"
            selectedKeys={getSelectedKey()}
            items={menuItems}
            onClick={handleClick}
            style={{ borderInlineEnd: 'none' }}
        />
    );
}
