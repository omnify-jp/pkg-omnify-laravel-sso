import {
    Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList,
    BreadcrumbPage, BreadcrumbSeparator,
} from '@omnifyjp/ui';
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

    return (
        <Breadcrumb>
            <BreadcrumbList>
                <BreadcrumbItem>
                    <BreadcrumbLink asChild>
                        <Link href="/admin/iam">{t('iam.title', 'IAM')}</Link>
                    </BreadcrumbLink>
                </BreadcrumbItem>
                {segments.map((segment, index) => (
                    <span key={index} className="contents">
                        <BreadcrumbSeparator />
                        <BreadcrumbItem>
                            {segment.href ? (
                                <BreadcrumbLink asChild>
                                    <Link href={segment.href}>{segment.label}</Link>
                                </BreadcrumbLink>
                            ) : (
                                <BreadcrumbPage>{segment.label}</BreadcrumbPage>
                            )}
                        </BreadcrumbItem>
                    </span>
                ))}
            </BreadcrumbList>
        </Breadcrumb>
    );
}
