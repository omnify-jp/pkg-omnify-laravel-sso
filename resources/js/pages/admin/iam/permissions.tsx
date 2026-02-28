import { Card, Col, Empty, Flex, Row, Tag, Typography } from 'antd';
import { ShieldCheck } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { PageContainer } from '@omnify-core/components/page-container';

import { IamBreadcrumb } from '../../../components/access/iam-breadcrumb';
import type { IamPermission } from '../../../types/iam';

type Props = {
    permissions: IamPermission[];
};

export default function IamPermissions({ permissions }: Props) {
    const { t } = useTranslation();

    const breadcrumbs = [
        { title: t('iam.title', 'IAM'), href: '/admin/iam' },
        { title: t('iam.permissions', 'Permissions'), href: '/admin/iam/permissions' },
    ];

    const grouped = useMemo(() => {
        const map = new Map<string, IamPermission[]>();
        for (const perm of permissions) {
            const group = perm.group ?? 'general';
            if (!map.has(group)) {
                map.set(group, []);
            }
            map.get(group)!.push(perm);
        }
        return Array.from(map.entries()).map(([group, perms]) => ({ group, perms }));
    }, [permissions]);

    return (
        <PageContainer
            title={t('iam.permissions', 'Permissions')}
            subtitle={t('iam.permissionsSubtitle', 'All permissions registered in this application.')}
            breadcrumbs={breadcrumbs}
            extra={<IamBreadcrumb segments={[{ label: t('iam.permissions', 'Permissions') }]} />}
        >
            {grouped.length === 0 ? (
                <Empty
                    image={<ShieldCheck size={40} />}
                    description={t('iam.noPermissions', 'No permissions found.')}
                />
            ) : (
                <Flex vertical gap={16}>
                    {grouped.map(({ group, perms }) => (
                        <Card
                            key={group}
                            title={
                                <Flex align="center" gap={8}>
                                    <Typography.Text strong>{group}</Typography.Text>
                                    <Tag>{perms.length}</Tag>
                                </Flex>
                            }
                        >
                            <Row gutter={[8, 8]}>
                                {perms.map((perm) => (
                                    <Col key={perm.id} xs={24} sm={12} lg={8}>
                                        <Card size="small">
                                            <Typography.Text strong>{perm.name}</Typography.Text>
                                            <br />
                                            <Typography.Text type="secondary" code>
                                                {perm.slug}
                                            </Typography.Text>
                                        </Card>
                                    </Col>
                                ))}
                            </Row>
                        </Card>
                    ))}
                </Flex>
            )}

            <Typography.Text type="secondary">
                {t('iam.totalPermissions', '{{count}} permissions across {{groups}} groups', {
                    count: permissions.length,
                    groups: grouped.length,
                })}
            </Typography.Text>
        </PageContainer>
    );
}
