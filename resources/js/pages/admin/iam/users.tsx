import {
    Avatar, AvatarFallback, Badge, Button,
    Card, CardContent, Input, Table,
    TableBody, TableCell, TableHead, TableHeader,
    TableRow,
} from '@omnifyjp/ui';
import { Head, Link, router } from '@inertiajs/react';
import { Eye, Search } from 'lucide-react';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useIamLayout } from '@omnify-sso/contexts/iam-layout-context';

import { IamBreadcrumb } from '../../../components/access/iam-breadcrumb';
import type { IamUser, PaginatedData } from '../../../types/iam';

type Props = {
    users: PaginatedData<IamUser & { roles_count: number }>;
    filters: { search?: string };
};

export default function IamUsers({ users, filters }: Props) {
    const Layout = useIamLayout();
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.search ?? '');

    const handleSearch = useCallback(
        (value: string) => {
            router.get(
                '/admin/iam/users',
                { search: value || undefined },
                { preserveState: true, replace: true },
            );
        },
        [],
    );

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        handleSearch(search);
    };

    return (
        <Layout
            breadcrumbs={[
                { title: t('iam.title', 'IAM'), href: '/admin/iam' },
                { title: t('iam.users', 'Users'), href: '/admin/iam/users' },
            ]}
        >
            <Head title={t('iam.users', 'Users')} />

            <div className="space-y-section">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-page-title font-semibold">{t('iam.users', 'Users')}</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('iam.usersSubtitle', 'Manage user accounts and role assignments.')}
                        </p>
                    </div>
                    <IamBreadcrumb segments={[{ label: t('iam.users', 'Users') }]} />
                </div>

                {/* Search */}
                <form onSubmit={handleSearchSubmit} className="flex items-center gap-3">
                    <div className="relative max-w-sm flex-1">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder={t('iam.searchUsers', 'Search users...')}
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="pl-9"
                        />
                    </div>
                    <Button type="submit" variant="outline">
                        {t('common.search', 'Search')}
                    </Button>
                </form>

                {/* Table */}
                <Card>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('iam.user', 'User')}</TableHead>
                                    <TableHead>{t('iam.email', 'Email')}</TableHead>
                                    <TableHead className="text-center">{t('iam.roles', 'Roles')}</TableHead>
                                    <TableHead>{t('iam.joined', 'Joined')}</TableHead>
                                    <TableHead className="w-20">{t('common.actions', 'Actions')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {users.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={5} className="py-8 text-center text-muted-foreground">
                                            {t('iam.noUsersFound', 'No users found.')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    users.data.map((user) => (
                                        <TableRow key={user.id}>
                                            <TableCell>
                                                <div className="flex items-center gap-3">
                                                    <Avatar className="h-8 w-8">
                                                        <AvatarFallback className="text-xs">
                                                            {user.name.slice(0, 2).toUpperCase()}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <span className="font-medium">{user.name}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">{user.email}</TableCell>
                                            <TableCell className="text-center">
                                                <Badge variant="secondary">{user.roles_count}</Badge>
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {user.created_at
                                                    ? new Date(user.created_at).toLocaleDateString()
                                                    : '—'}
                                            </TableCell>
                                            <TableCell>
                                                <Button variant="ghost" size="icon" className="h-8 w-8" asChild>
                                                    <Link href={`/admin/iam/users/${user.id}`}>
                                                        <Eye className="h-4 w-4" />
                                                        <span className="sr-only">{t('common.view', 'View')}</span>
                                                    </Link>
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                {/* Pagination info */}
                <div className="flex items-center justify-between text-sm text-muted-foreground">
                    <span>
                        {t('iam.showingUsers', 'Showing {{from}}–{{to}} of {{total}} users', {
                            from: (users.meta.current_page - 1) * users.meta.per_page + 1,
                            to: Math.min(users.meta.current_page * users.meta.per_page, users.meta.total),
                            total: users.meta.total,
                        })}
                    </span>
                    <div className="flex items-center gap-2">
                        {users.links.prev && (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={users.links.prev}>{t('common.previous', 'Previous')}</Link>
                            </Button>
                        )}
                        {users.links.next && (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={users.links.next}>{t('common.next', 'Next')}</Link>
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </Layout>
    );
}
