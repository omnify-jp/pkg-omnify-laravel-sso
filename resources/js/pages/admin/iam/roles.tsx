import {
    Badge, Button, Card, CardContent,
    DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger,
    Input, Table, TableBody, TableCell,
    TableHead, TableHeader, TableRow,
} from '@omnifyjp/ui';
import { Head, Link, router } from '@inertiajs/react';
import { Eye, MoreVertical, Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useIamLayout } from '@omnify-sso/contexts/iam-layout-context';

import { IamBreadcrumb } from '../../../components/access/iam-breadcrumb';
import type { IamRole } from '../../../types/iam';

type Props = {
    roles: IamRole[];
};

export default function IamRoles({ roles }: Props) {
    const Layout = useIamLayout();
    const { t } = useTranslation();
    const [search, setSearch] = useState('');

    const filtered = useMemo(
        () =>
            roles.filter(
                (r) =>
                    r.name.toLowerCase().includes(search.toLowerCase()) ||
                    r.slug.toLowerCase().includes(search.toLowerCase()),
            ),
        [roles, search],
    );

    const levelLabel = (level: number) => {
        if (level >= 100) return { label: 'Admin', color: 'destructive' as const };
        if (level >= 50) return { label: 'Manager', color: 'warning' as const };
        if (level >= 10) return { label: 'Member', color: 'info' as const };
        return { label: 'Viewer', color: 'secondary' as const };
    };

    return (
        <Layout
            breadcrumbs={[
                { title: t('iam.title', 'IAM'), href: '/admin/iam' },
                { title: t('iam.roles', 'Roles'), href: '/admin/iam/roles' },
            ]}
        >
            <Head title={t('iam.roles', 'Roles')} />

            <div className="space-y-section">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-page-title font-semibold">{t('iam.roles', 'Roles')}</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('iam.rolesSubtitle', 'Define roles and their permissions.')}
                        </p>
                    </div>
                    <IamBreadcrumb segments={[{ label: t('iam.roles', 'Roles') }]} />
                </div>

                <div className="flex items-center justify-between gap-4">
                    <div className="relative max-w-sm flex-1">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder={t('iam.searchRoles', 'Search roles...')}
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="pl-9"
                        />
                    </div>
                </div>

                <Card>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('iam.roleName', 'Role Name')}</TableHead>
                                    <TableHead>{t('iam.level', 'Level')}</TableHead>
                                    <TableHead>{t('iam.description', 'Description')}</TableHead>
                                    <TableHead className="text-center">{t('iam.permissions', 'Permissions')}</TableHead>
                                    <TableHead className="text-center">{t('iam.assignments', 'Assignments')}</TableHead>
                                    <TableHead>{t('iam.scope', 'Scope')}</TableHead>
                                    <TableHead className="w-12">{t('common.actions', 'Actions')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filtered.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={7} className="py-8 text-center text-muted-foreground">
                                            {t('iam.noRolesFound', 'No roles found.')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    filtered.map((role) => {
                                        const { label, color } = levelLabel(role.level);
                                        return (
                                            <TableRow key={role.id}>
                                                <TableCell>
                                                    <span className="font-medium">{role.name}</span>
                                                    <p className="text-xs text-muted-foreground">{role.slug}</p>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="soft" color={color}>
                                                        Lv.{role.level} · {label}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="max-w-xs truncate text-sm text-muted-foreground">
                                                    {role.description ?? '—'}
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    {role.permissions_count ?? '—'}
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    {role.assignments_count ?? '—'}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="outline">
                                                        {role.is_global ? t('iam.global', 'Global') : t('iam.orgScoped', 'Org-scoped')}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button variant="ghost" size="icon" className="h-8 w-8">
                                                                <MoreVertical className="h-4 w-4" />
                                                                <span className="sr-only">{t('common.actions', 'Actions')}</span>
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem asChild>
                                                                <Link href={`/admin/iam/roles/${role.id}`}>
                                                                    <Eye className="h-4 w-4" />
                                                                    {t('common.view', 'View')}
                                                                </Link>
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <p className="text-sm text-muted-foreground">
                    {t('iam.showingRoles', 'Showing {{filtered}} of {{total}} roles', {
                        filtered: filtered.length,
                        total: roles.length,
                    })}
                </p>
            </div>
        </Layout>
    );
}
