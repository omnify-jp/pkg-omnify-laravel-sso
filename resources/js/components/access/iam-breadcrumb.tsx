import { Breadcrumb } from 'antd';
import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export type BreadcrumbSegment = {
    label: string;
    href?: string;
};

type IamBreadcrumbProps = {
    segments: BreadcrumbSegment[];
};

export function IamBreadcrumb({ segments }: IamBreadcrumbProps) {
    const { t } = useTranslation();

    const items = [
        {
            title: <Link href="/admin/iam">{t('iam.title', 'IAM')}</Link>,
        },
        ...segments.map((segment) => ({
            title: segment.href
                ? <Link href={segment.href}>{segment.label}</Link>
                : segment.label,
        })),
    ];

    return <Breadcrumb items={items} />;
}
