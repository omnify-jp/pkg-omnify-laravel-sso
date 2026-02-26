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
import { Filters, FilterSearch } from '@/components/filters';
import { DataTable } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, ColumnDef } from '@/types';
import type { Organization } from '@/types/models/Organization';

type Props = {
    organizations: {
        data: Organization[];
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
    filters: {
        search?: string;
    };
};

export default function AdminOrganizationsIndex({ organizations, filters }: Props) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('nav.dashboard', 'Dashboard'), href: '/dashboard' },
        { title: t('admin.organizations.title', 'Organizations'), href: '/admin/organizations' },
    ];

    const handleDelete = useCallback((org: Organization) => {
        if (confirm(t('admin.organizations.deleteConfirm', 'Delete this organization?'))) {
            router.delete(`/admin/organizations/${org.id}`);
        }
    }, [t]);

    const columns: ColumnDef<Organization>[] = [
        {
            accessorKey: 'name',
            header: t('admin.organizations.name', 'Name'),
            enableSorting: true,
        },
        {
            accessorKey: 'slug',
            header: t('admin.organizations.slug', 'Slug'),
            enableSorting: true,
        },
        {
            accessorKey: 'is_active',
            header: t('admin.organizations.status', 'Status'),
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
                        <Link href={`/admin/organizations/${row.original.id}/edit`}>
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
            <Head title={t('admin.organizations.title', 'Organizations')} />

            <div className="space-y-section">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-page-title font-semibold">
                            {t('admin.organizations.title', 'Organizations')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t('admin.organizations.subtitle', 'Manage organizations in standalone mode.')}
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/admin/organizations/create">
                            <PlusCircle className="mr-2 h-4 w-4" />
                            {t('admin.organizations.create', 'Create Organization')}
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader className="px-card pb-3 pt-card">
                        <CardTitle className="text-base">
                            {t('admin.organizations.list', 'Organization List')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="px-card pb-card">
                        <div className="mb-4">
                            <Filters routeUrl="/admin/organizations" currentFilters={filters}>
                                <FilterSearch
                                    filterKey="search"
                                    placeholder={t('admin.organizations.searchPlaceholder', 'Search by name or slug...')}
                                />
                            </Filters>
                        </div>

                        <DataTable
                            data={organizations.data}
                            columns={columns}
                            meta={organizations.meta}
                            routeUrl="/admin/organizations"
                            extraParams={{ search: filters.search }}
                        />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
