import { useLocale } from '@omnify-core/providers/omnify-provider';
import { Button, Dropdown, Flex, Typography } from 'antd';
import type { MenuProps } from 'antd';
import { GlobeIcon } from 'lucide-react';

const LOCALE_TO_FLAG: Record<string, string> = {
    en: '\u{1F1FA}\u{1F1F8}',
    vi: '\u{1F1FB}\u{1F1F3}',
    ja: '\u{1F1EF}\u{1F1F5}',
    ko: '\u{1F1F0}\u{1F1F7}',
    zh: '\u{1F1E8}\u{1F1F3}',
};

function deriveFlag(code: string): string {
    const base = code.split('-')[0].toLowerCase();
    return LOCALE_TO_FLAG[base] ?? '\u{1F310}';
}

export interface LocaleSwitcherProps {
    showLabel?: boolean;
    showFlag?: boolean;
}

export function LocaleSwitcher({ showLabel = true, showFlag = false }: LocaleSwitcherProps) {
    const { currentLocale, setLocale, locales } = useLocale();

    const entries = Object.entries(locales);
    if (entries.length === 0) return null;

    const items: MenuProps['items'] = entries.map(([code, label]) => ({
        key: code,
        label: (
            <Flex align="center" gap={8}>
                {showFlag && <span>{deriveFlag(code)}</span>}
                <span>{label}</span>
                {code === currentLocale && <Typography.Text type="success">*</Typography.Text>}
            </Flex>
        ),
    }));

    return (
        <Dropdown
            menu={{ items, selectedKeys: [currentLocale], onClick: ({ key }) => setLocale(key) }}
            trigger={['click']}
            placement="bottomRight"
        >
            <Button type="text" shape={showLabel ? undefined : 'circle'} icon={<GlobeIcon size={16} />}>
                {showLabel && <span>{locales[currentLocale] ?? currentLocale}</span>}
            </Button>
        </Dropdown>
    );
}
