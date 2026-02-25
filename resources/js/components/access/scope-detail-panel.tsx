import {
    Avatar, AvatarFallback, Badge, Button,
    ScopeTypeBadge,
} from '@omnifyjp/ui';
import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import type { IamAssignment, IamBranch, IamOrganization, ScopeType } from '../../types/iam';
import {
    getDirectAssignments,
    getInheritedAssignments,
    getScopeLabel,
    getScopeNodeName,
    toScopeBadgeType,
} from '../../utils/scope-utils';

type Props = {
    scope: ScopeType;
    scopeId: string | null;
    assignments: IamAssignment[];
    organizations: IamOrganization[];
    branches: IamBranch[];
};

export function ScopeDetailPanel({ scope, scopeId, assignments, organizations, branches }: Props) {
    const { t } = useTranslation();

    const name = getScopeNodeName(scope, scopeId, organizations, branches);

    const directAssignments = useMemo(
        () => getDirectAssignments(scope, scopeId, assignments),
        [scope, scopeId, assignments],
    );

    const inheritedAssignments = useMemo(
        () => getInheritedAssignments(scope, scopeId, assignments, organizations, branches),
        [scope, scopeId, assignments, organizations, branches],
    );

    const createUrl = `/admin/iam/assignments/create?scope=${scope}&scopeId=${scopeId ?? ''}`;

    return (
        <div className="space-y-section">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <div>
                        <h3 className="text-lg font-semibold">{name}</h3>
                    </div>
                    <ScopeTypeBadge
                        type={toScopeBadgeType(scope)}
                        label={getScopeLabel(scope)}
                    />
                </div>
                <Button size="sm" asChild>
                    <Link href={createUrl}>
                        <Plus className="h-4 w-4" />
                        {t('iam.addAssignment', 'Add Assignment')}
                    </Link>
                </Button>
            </div>

            {/* Direct Assignments */}
            <div>
                <h4 className="mb-3 text-sm font-medium">
                    {t('iam.directAssignments', 'Direct Assignments')}
                </h4>
                {directAssignments.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('iam.noAssignments', 'No assignments at this scope.')}
                    </p>
                ) : (
                    <div className="space-y-2">
                        {directAssignments.map((assignment) => (
                            <Link
                                key={assignment.id}
                                href={`/admin/iam/users/${assignment.user.id}`}
                                className="flex items-center gap-3 rounded-lg border border-border p-3 transition-colors hover:bg-accent"
                            >
                                <Avatar className="h-8 w-8">
                                    <AvatarFallback className="text-xs">
                                        {assignment.user.name.slice(0, 2).toUpperCase()}
                                    </AvatarFallback>
                                </Avatar>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium">
                                        {assignment.user.name}
                                    </p>
                                    {assignment.created_at && (
                                        <p className="text-xs text-muted-foreground">
                                            {new Date(assignment.created_at).toLocaleDateString()}
                                        </p>
                                    )}
                                </div>
                                <Badge variant="outline" className="shrink-0">
                                    <span className="mr-1 text-xs text-muted-foreground">
                                        Lv.{assignment.role.level}
                                    </span>
                                    {assignment.role.name}
                                </Badge>
                            </Link>
                        ))}
                    </div>
                )}
            </div>

            {/* Inherited Assignments */}
            {inheritedAssignments.length > 0 && (
                <div>
                    <h4 className="mb-3 text-sm font-medium">
                        {t('iam.inheritedAssignments', 'Inherited Assignments')}
                    </h4>
                    <div className="space-y-2">
                        {inheritedAssignments.map(({ assignment, fromName }) => (
                            <div
                                key={assignment.id}
                                className="flex items-center gap-3 rounded-lg border border-border p-3 opacity-60"
                            >
                                <Avatar className="h-8 w-8">
                                    <AvatarFallback className="text-xs">
                                        {assignment.user.name.slice(0, 2).toUpperCase()}
                                    </AvatarFallback>
                                </Avatar>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium">
                                        {assignment.user.name}
                                    </p>
                                    <p className="text-xs italic text-muted-foreground">
                                        {t('iam.inheritedFrom', 'Inherited from {{name}}', {
                                            name: fromName,
                                        })}
                                    </p>
                                </div>
                                <Badge variant="outline" className="shrink-0">
                                    <span className="mr-1 text-xs text-muted-foreground">
                                        Lv.{assignment.role.level}
                                    </span>
                                    {assignment.role.name}
                                </Badge>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
