import {
    Badge, Button, Card, CardContent,
    Input, Select, SelectContent, SelectItem,
    SelectTrigger, SelectValue, ScopeTypeBadge, Table,
    TableBody, TableCell, TableHead, TableHeader,
    TableRow,
} from '@omnifyjp/ui';
import { Head, Link, router } from '@inertiajs/react';
import { Eye, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useIamLayout } from '@omnify-sso/contexts/iam-layout-context';

import { IamBreadcrumb } from '../../../components/access/iam-breadcrumb';
import type { IamAssignment, ScopeType } from '../../../types/iam';
import { formatScopeLocation, getScopeLabel, toScopeBadgeType } from '../../../utils/scope-utils';

type Props = {
    assignments: IamAssignment[];
};

export default function IamAssignments({ assignments }: Props) {
    const Layout = useIamLayout();
    const { t } = useTranslation();
    const [search, setSearch] = useState('');
    const [scopeFilter, setScopeFilter] = useState<string>('all');

    const filtered = assignments.filter((a) => {
        const matchesSearch =
            !search ||
            a.user.name.toLowerCase().includes(search.toLowerCase()) ||
            a.role.name.toLowerCase().includes(search.toLowerCase()) ||
            a.user.email.toLowerCase().includes(search.toLowerCase());
        const matchesScope = scopeFilter === 'all' || a.scope_type === scopeFilter;
        return matchesSearch && matchesScope;
    });

    const handleDelete = (assignment: IamAssignment) => {
        if (!confirm(t('iam.confirmDeleteAssignment', 'Remove this assignment?'))) {
            return;
        }
        router.delete(
            `/admin/iam/assignments/${assignment.user.id}/${assignment.role.id}`,
        );
    };

    return (
        <Layout
            breadcrumbs={[
                { title: t('iam.title', 'IAM'), href: '/admin/iam' },
                { title: t('iam.assignments', 'Assignments'), href: '/admin/iam/assignments' },
            ]}
        >
            <Head title={t('iam.assignments', 'Assignments')} />

            <div className="space-y-section">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-page-title font-semibold">
                            {t('iam.assignments', 'Assignments')}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t(
                                'iam.assignmentsSubtitle',
                                'All scoped role assignments across users.',
                            )}
                        </p>
                    </div>
                    <IamBreadcrumb
                        segments={[{ label: t('iam.assignments', 'Assignments') }]}
                    />
                </div>

                {/* Toolbar */}
                <div className="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
                    <div className="flex w-full flex-1 gap-3 sm:w-auto">
                        <Input
                            placeholder={t('iam.searchAssignments', 'Search by user or role…')}
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="max-w-xs"
                        />
                        <Select value={scopeFilter} onValueChange={setScopeFilter}>
                            <SelectTrigger className="w-40">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    {t('iam.allScopes', 'All Scopes')}
                                </SelectItem>
                                <SelectItem value="global">
                                    {t('iam.global', 'Global')}
                                </SelectItem>
                                <SelectItem value="org-wide">
                                    {t('iam.orgWide', 'Organization')}
                                </SelectItem>
                                <SelectItem value="branch">
                                    {t('iam.branch', 'Branch')}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <Button asChild>
                        <Link href="/admin/iam/assignments/create">
                            <Plus className="h-4 w-4" />
                            {t('iam.createAssignment', 'Create Assignment')}
                        </Link>
                    </Button>
                </div>

                {/* Table */}
                <Card>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('iam.user', 'User')}</TableHead>
                                    <TableHead>{t('iam.role', 'Role')}</TableHead>
                                    <TableHead>{t('iam.scopeType', 'Scope Type')}</TableHead>
                                    <TableHead>{t('iam.scopeEntity', 'Scope')}</TableHead>
                                    <TableHead>{t('iam.assignedAt', 'Assigned')}</TableHead>
                                    <TableHead className="w-24">
                                        {t('iam.actions', 'Actions')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filtered.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={6}
                                            className="py-8 text-center text-sm text-muted-foreground"
                                        >
                                            {t('iam.noAssignments', 'No assignments found.')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    filtered.map((assignment) => (
                                        <TableRow key={assignment.id}>
                                            <TableCell>
                                                <div>
                                                    <p className="text-sm font-medium">
                                                        {assignment.user.name}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {assignment.user.email}
                                                    </p>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">
                                                    <span className="mr-1 text-xs text-muted-foreground">
                                                        Lv.{assignment.role.level}
                                                    </span>
                                                    {assignment.role.name}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <ScopeTypeBadge
                                                    type={toScopeBadgeType(
                                                        assignment.scope_type as ScopeType,
                                                    )}
                                                    label={getScopeLabel(
                                                        assignment.scope_type as ScopeType,
                                                    )}
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <span className="text-sm">
                                                    {formatScopeLocation(assignment)}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                <span className="text-sm text-muted-foreground">
                                                    {assignment.created_at
                                                        ? new Date(
                                                              assignment.created_at,
                                                          ).toLocaleDateString()
                                                        : '—'}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-8 w-8"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={`/admin/iam/users/${assignment.user.id}`}
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-8 w-8 text-destructive hover:text-destructive"
                                                        onClick={() => handleDelete(assignment)}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <p className="text-sm text-muted-foreground">
                    {t('iam.showingCount', 'Showing {{filtered}} of {{total}}', {
                        filtered: filtered.length,
                        total: assignments.length,
                    })}
                </p>
            </div>
        </Layout>
    );
}
