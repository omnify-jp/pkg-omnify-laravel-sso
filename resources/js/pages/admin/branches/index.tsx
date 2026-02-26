import { Head, Link, router } from '@inertiajs/react';
import {
    Badge,
    Button,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@omnifyjp/ui';
import { PlusCircle } from 'lucide-react';
import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { Filters, FilterSearch, FilterSelect } from '@/components/filters';
import { DataTable } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, ColumnDef } from '@/types';
import type { Branch } from '@/types/models/Branch';
import type { Organization } from '@/types/models/Organization';

type Props = {
    branches: {
        data: Branch[];
        meta: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        };
        links: {
            first: string | null;
            last: string | null;
            prev: string | null;
            next: string | null;
        };
    };
    organizations: Pick<Organization, 'id' | 'console_organization_id' | 'name' | 'slug'>[];
    filters: {
        search?: string;
        organization_id?: string;
    };
};

export default function AdminBranchesIndex({ branches, organizations, filters }: Props) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('nav.dashboard', 'Dashboard'), href: '/dashboard' },
        { title: t('admin.branches.title', 'Branches'), href: '/admin/branches' },
    ];

    const handleDelete = useCallback((branch: Branch) => {
        if (confirm(t('admin.branches.deleteConfirm', 'Delete this branch?'))) {
            router.delete(`/admin/branches/${branch.id}`);
        }
    }, [t]);

    const getOrgName = useCallback(
        (consoleOrgId: string) => {
            const org = organizations.find((o) => o.console_organization_id === consoleOrgId);
            return org?.name ?? consoleOrgId;
        },
        [organizations],
    );

    const columns: ColumnDef<Branch>[] = [
        {
            accessorKey: 'name',
            header: t('admin.branches.name', 'Name'),
            enableSorting: true,
        },
        {
            accessorKey: 'slug',
            header: t('admin.branches.slug', 'Slug'),
            enableSorting: true,
        },
        {
            accessorKey: 'console_organization_id',
            header: t('admin.branches.organization', 'Organization'),
            enableSorting: false,
            cell: ({ row }) => getOrgName(row.original.console_organization_id),
        },
        {
            accessorKey: 'is_headquarters',
            header: t('admin.branches.headquarters', 'HQ'),
            enableSorting: false,
            cell: ({ row }) =>
                row.original.is_headquarters ? (
                    <Badge variant="default">{t('common.yes', 'Yes')}</Badge>
                ) : null,
        },
        {
            accessorKey: 'is_active',
            header: t('admin.branches.status', 'Status'),
            enableSorting: false,
            cell: ({ row }) => (
                <Badge variant={row.original.is_active ? 'default' : 'secondary'}>
                    {row.original.is_active
                        ? t('common.active', 'Active')
                        : t('common.inactive', 'Inactive')}
                </Badge>
            ),
        },
        {
            id: 'actions',
            header: t('common.actions', 'Actions'),
            enableSorting: false,
            cell: ({ row }) => (
                <div className="flex items-center gap-2">
                    <Button variant="outline" size="sm" asChild>
                        <Link href={`/admin/branches/${row.original.id}/edit`}>
                            {t('common.edit', 'Edit')}
                        </Link>
                    </Button>
                    <Button
                        variant="destructive"
                        size="sm"
                        onClick={() => handleDelete(row.original)}
                    >
                        {t('common.delete', 'Delete')}
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.branches.title', 'Branches')} />

            <div className="space-y-section">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-page-title font-semibold">
                            {t('admin.branches.title', 'Branches')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t('admin.branches.subtitle', 'Manage branches across organizations.')}
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/admin/branches/create">
                            <PlusCircle className="mr-2 h-4 w-4" />
                            {t('admin.branches.create', 'Create Branch')}
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader className="px-card pb-3 pt-card">
                        <CardTitle className="text-base">
                            {t('admin.branches.list', 'Branch List')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="px-card pb-card">
                        <div className="mb-4">
                            <Filters routeUrl="/admin/branches" currentFilters={filters}>
                                <FilterSearch
                                    filterKey="search"
                                    placeholder={t('admin.branches.searchPlaceholder', 'Search by name or slug...')}
                                />
                                <FilterSelect
                                    filterKey="organization_id"
                                    options={organizations.map(org => ({ value: org.id, label: org.name }))}
                                    allLabel={t('admin.branches.allOrganizations', 'All organizations')}
                                    className="w-48"
                                />
                            </Filters>
                        </div>

                        <DataTable
                            data={branches.data}
                            columns={columns}
                            meta={branches.meta}
                            routeUrl="/admin/branches"
                            extraParams={{
                                search: filters.search,
                                organization_id: filters.organization_id,
                            }}
                        />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
