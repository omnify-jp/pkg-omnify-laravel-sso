import { Avatar, Button, Card, Empty, Flex, List, Tag, Typography } from 'antd';
import { Link } from '@inertiajs/react';
import { useOrgRoute } from '@omnify-core/hooks/use-org-route';
import { Plus } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import type { IamAssignment, IamBranch, IamOrganization, ScopeType } from '@omnify-core/types/iam';
import {
    getDirectAssignments,
    getInheritedAssignments,
    getScopeLabel,
    getScopeNodeName,
    toScopeBadgeType,
} from '@omnify-core/utils/scope-utils';
import { ScopeTypeBadge } from './scope-type-badge';

type Props = {
    scope: ScopeType;
    scopeId: string | null;
    assignments: IamAssignment[];
    organizations: IamOrganization[];
    branches: IamBranch[];
};

export function ScopeDetailPanel({ scope, scopeId, assignments, organizations, branches }: Props) {
    const { t } = useTranslation();
    const orgRoute = useOrgRoute();

    const name = getScopeNodeName(scope, scopeId, organizations, branches);

    const directAssignments = useMemo(
        () => getDirectAssignments(scope, scopeId, assignments),
        [scope, scopeId, assignments],
    );

    const inheritedAssignments = useMemo(
        () => getInheritedAssignments(scope, scopeId, assignments, organizations, branches),
        [scope, scopeId, assignments, organizations, branches],
    );

    const createUrl = orgRoute(`/settings/iam/assignments/create?scope=${scope}&scopeId=${scopeId ?? ''}`);

    return (
        <Flex vertical gap={24}>
            {/* Header */}
            <Flex justify="space-between" align="center">
                <Flex align="center" gap={12}>
                    <Typography.Title level={4}>{name}</Typography.Title>
                    <ScopeTypeBadge
                        type={toScopeBadgeType(scope)}
                        label={getScopeLabel(scope)}
                    />
                </Flex>
                <Link href={createUrl}>
                    <Button icon={<Plus size={16} />}>
                        {t('iam.addAssignment', 'Add Assignment')}
                    </Button>
                </Link>
            </Flex>

            {/* Direct Assignments */}
            <Card title={t('iam.directAssignments', 'Direct Assignments')}>
                {directAssignments.length === 0 ? (
                    <Empty description={t('iam.noAssignments', 'No assignments at this scope.')} />
                ) : (
                    <List
                        dataSource={directAssignments}
                        renderItem={(assignment) => (
                            <Link href={orgRoute(`/settings/iam/users/${assignment.user.id}`)}>
                                <List.Item
                                    extra={
                                        <Tag>
                                            Lv.{assignment.role.level} {assignment.role.name}
                                        </Tag>
                                    }
                                >
                                    <List.Item.Meta
                                        avatar={
                                            <Avatar size={32}>
                                                {assignment.user.name.slice(0, 2).toUpperCase()}
                                            </Avatar>
                                        }
                                        title={assignment.user.name}
                                        description={
                                            assignment.created_at
                                                ? new Date(assignment.created_at).toLocaleDateString()
                                                : undefined
                                        }
                                    />
                                </List.Item>
                            </Link>
                        )}
                    />
                )}
            </Card>

            {/* Inherited Assignments */}
            {inheritedAssignments.length > 0 && (
                <Card title={t('iam.inheritedAssignments', 'Inherited Assignments')}>
                    <List
                        dataSource={inheritedAssignments}
                        renderItem={({ assignment, fromName }) => (
                            <List.Item
                                extra={
                                    <Tag>
                                        Lv.{assignment.role.level} {assignment.role.name}
                                    </Tag>
                                }
                            >
                                <List.Item.Meta
                                    avatar={
                                        <Avatar size={32}>
                                            {assignment.user.name.slice(0, 2).toUpperCase()}
                                        </Avatar>
                                    }
                                    title={assignment.user.name}
                                    description={
                                        <Typography.Text type="secondary" italic>
                                            {t('iam.inheritedFrom', 'Inherited from {{name}}', {
                                                name: fromName,
                                            })}
                                        </Typography.Text>
                                    }
                                />
                            </List.Item>
                        )}
                    />
                </Card>
            )}
        </Flex>
    );
}
