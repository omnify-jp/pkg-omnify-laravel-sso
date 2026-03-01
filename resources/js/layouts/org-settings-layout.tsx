import { router, usePage } from '@inertiajs/react';
import { useAppLayout } from '@omnify-core/contexts/app-layout-context';
import { useOrgRoute } from '@omnify-core/hooks/use-org-route';
import { Tabs } from 'antd';
import type { TabsProps } from 'antd';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import type { ReactNode } from 'react';

type BreadcrumbItem = { title: string; href: string };

type TabConfig = {
    suffix: string;
    label_key: string;
    label_default: string;
};

type SectionConfig = {
    key: string;
    path_prefix: string;
    tabs: TabConfig[];
};

type Props = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    title?: string;
    subtitle?: string;
    extra?: ReactNode;
};

export default function OrgSettingsLayout({ children, breadcrumbs, title, subtitle, extra }: Props) {
    const { t } = useTranslation();
    const { url, props } = usePage<{
        org_settings_sections?: SectionConfig[];
    }>();

    const AppLayout = useAppLayout();
    const orgRoute = useOrgRoute();

    const sections: SectionConfig[] = props.org_settings_sections ?? [];

    // Find matching section based on current URL
    const matchedSection = useMemo(() => {
        for (const section of sections) {
            const sectionPath = orgRoute(`/${section.path_prefix}`);
            if (url.startsWith(sectionPath)) {
                return { section, basePath: sectionPath };
            }
        }
        return null;
    }, [url, orgRoute, sections]);

    const tabItems: TabsProps['items'] = useMemo(() => {
        if (!matchedSection) return [];
        const { section, basePath } = matchedSection;
        return section.tabs.map((tab) => ({
            key: `${basePath}${tab.suffix}`,
            label: t(tab.label_key, tab.label_default),
        }));
    }, [matchedSection, t]);

    const activeKey = useMemo(() => {
        if (!matchedSection) return '';
        const { section, basePath } = matchedSection;
        // Match longest suffix first
        const sorted = [...section.tabs].sort((a, b) => b.suffix.length - a.suffix.length);
        for (const tab of sorted) {
            const tabPath = `${basePath}${tab.suffix}`;
            if (tab.suffix === '' ? url === basePath || url === `${basePath}/` : url.startsWith(tabPath)) {
                return tabPath;
            }
        }
        return `${basePath}${section.tabs[0]?.suffix ?? ''}`;
    }, [url, matchedSection]);

    const sectionLabel = matchedSection?.section.key.toUpperCase() ?? '';
    const sectionHref = matchedSection?.basePath ?? '';

    return (
        <AppLayout
            breadcrumbs={[
                { title: t('orgSettings.title', 'Organization Settings'), href: orgRoute('/settings') },
                ...(sectionLabel ? [{ title: sectionLabel, href: sectionHref }] : []),
                ...(breadcrumbs ?? []),
            ]}
            title={title}
            subtitle={subtitle}
            extra={extra}
        >
            {tabItems.length > 0 && (
                <Tabs
                    activeKey={activeKey}
                    items={tabItems}
                    onChange={(key) => router.visit(key)}
                    style={{ marginBottom: -16 }}
                />
            )}
            {children}
        </AppLayout>
    );
}
