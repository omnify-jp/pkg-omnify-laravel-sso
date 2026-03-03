import { Head } from '@inertiajs/react';
import { Avatar, Button, Card, Empty, Flex, Input, Steps, Typography } from 'antd';
import { ArrowRight, Building2, Check, MapPin, Search } from 'lucide-react';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { usePage } from '@inertiajs/react';

import { useAuthLayout } from '@omnify-core/contexts/auth-layout-context';
import { useOrgSwitch } from '@omnify-core/hooks/use-org-switch';
import type { SsoBranch, SsoOrganization } from '@omnify-core/types/sso';

function getOrgInitials(name: string): string {
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return name.slice(0, 2).toUpperCase();
}

type SelectableItemProps = {
    avatar: React.ReactNode;
    title: string;
    description?: string;
    selected: boolean;
    onClick: () => void;
};

function SelectableItem({ avatar, title, description, selected, onClick }: SelectableItemProps) {
    return (
        <Card
            hoverable
            size="small"
            onClick={onClick}
            style={selected ? { borderColor: 'var(--ant-color-primary)', background: 'var(--ant-color-primary-bg)' } : undefined}
        >
            <Flex align="center" gap={12}>
                {avatar}
                <Flex vertical flex={1}>
                    <Typography.Text strong>{title}</Typography.Text>
                    {description && <Typography.Text type="secondary">{description}</Typography.Text>}
                </Flex>
                {selected && <Check size={16} color="var(--ant-color-primary)" />}
            </Flex>
        </Card>
    );
}

type OrganizationPageProps = {
    organization: {
        current: SsoOrganization | null;
        list: SsoOrganization[];
        branches: SsoBranch[];
    };
};

export default function SelectOrganization() {
    const { t } = useTranslation();
    const AuthLayout = useAuthLayout();
    const switchOrg = useOrgSwitch();

    const { organization } = usePage<OrganizationPageProps>().props;
    const organizations: SsoOrganization[] = organization?.list ?? [];
    const branches: SsoBranch[] = organization?.branches ?? [];
    const hasBranches = branches.length > 0;

    const [step, setStep] = useState<'organization' | 'branch'>('organization');
    const [selectedOrgId, setSelectedOrgId] = useState('');
    const [selectedBranchId, setSelectedBranchId] = useState('');
    const [branchSearch, setBranchSearch] = useState('');

    const getBranchesByOrg = useCallback(
        (orgId: string) => branches.filter((b) => b.organization_id === orgId),
        [branches],
    );

    const selectedOrg = organizations.find((o) => o.id === selectedOrgId);

    const availableBranches = selectedOrgId ? getBranchesByOrg(selectedOrgId) : [];
    const filteredBranches = availableBranches.filter(
        (b) =>
            b.name.toLowerCase().includes(branchSearch.toLowerCase()) ||
            (typeof b.location === 'string' && b.location.toLowerCase().includes(branchSearch.toLowerCase())),
    );

    const handleOrgNext = useCallback(() => {
        if (!selectedOrg) return;

        if (!hasBranches || availableBranches.length === 0) {
            // No branches to select — go straight to dashboard
            switchOrg(selectedOrg.slug, selectedOrg.console_organization_id ?? selectedOrg.id);
            return;
        }

        setStep('branch');
        setSelectedBranchId('');
        setBranchSearch('');
    }, [selectedOrg, hasBranches, availableBranches.length, switchOrg]);

    const handleBranchComplete = useCallback(() => {
        const branch = availableBranches.find((b) => b.id === selectedBranchId);
        if (!selectedOrg || !branch) return;

        // Set branch cookie before navigating
        const consoleBranchId = String((branch as Record<string, unknown>)['console_branch_id'] ?? branch.id);
        document.cookie = `current_branch_id=${consoleBranchId};path=/;max-age=31536000;SameSite=Lax`;

        switchOrg(selectedOrg.slug, selectedOrg.console_organization_id ?? selectedOrg.id);
    }, [selectedOrg, selectedBranchId, availableBranches, switchOrg]);

    const showSteps = hasBranches && availableBranches.length > 0;

    return (
        <AuthLayout
            title={step === 'organization'
                ? t('sso.org.selectOrg', 'Select organization')
                : t('sso.org.selectBranch', 'Select branch')}
            description={step === 'organization'
                ? t('sso.org.selectOrgDesc', 'Choose the organization you want to work with')
                : t('sso.org.selectBranchDesc', 'Choose a branch within the organization')}
        >
            <Head title={t('sso.org.selectOrg', 'Select organization')} />

            <Flex vertical gap={16}>
                {showSteps && (
                    <Steps
                        size="small"
                        current={step === 'organization' ? 0 : 1}
                        items={[
                            { title: t('sso.org.orgStep', 'Organization'), icon: <Building2 size={16} /> },
                            { title: t('sso.org.branchStep', 'Branch'), icon: <MapPin size={16} /> },
                        ]}
                    />
                )}

                {step === 'organization' && (
                    <>
                        {organizations.length === 0 ? (
                            <Empty
                                image={Empty.PRESENTED_IMAGE_SIMPLE}
                                description={t('sso.org.noOrgAccess', 'You do not have access to any organization. Please contact your administrator.')}
                            />
                        ) : (
                            <Flex vertical gap={8}>
                                {organizations.map((org) => (
                                    <SelectableItem
                                        key={org.id}
                                        avatar={<Avatar size={32} shape="square">{getOrgInitials(org.name)}</Avatar>}
                                        title={org.name}
                                        description={
                                            showSteps
                                                ? t('sso.org.branchCount', {
                                                      count: getBranchesByOrg(org.id).length,
                                                      defaultValue: '{{count}} branches',
                                                  })
                                                : undefined
                                        }
                                        selected={selectedOrgId === org.id}
                                        onClick={() => setSelectedOrgId(org.id)}
                                    />
                                ))}
                            </Flex>
                        )}

                        {organizations.length > 0 && (
                            <Flex justify="end">
                                <Button
                                    type="primary"
                                    onClick={handleOrgNext}
                                    disabled={!selectedOrgId}
                                >
                                    <Flex align="center" gap={4}>
                                        {showSteps ? t('common.next', 'Next') : t('common.select', 'Select')}
                                        {showSteps && <ArrowRight size={14} />}
                                    </Flex>
                                </Button>
                            </Flex>
                        )}
                    </>
                )}

                {step === 'branch' && selectedOrg && (
                    <>
                        <Flex align="center" gap={8}>
                            <Avatar size={28} shape="square">
                                {getOrgInitials(selectedOrg.name)}
                            </Avatar>
                            <Flex vertical>
                                <Typography.Text type="secondary">
                                    {t('sso.org.selectedOrg', 'Selected organization')}
                                </Typography.Text>
                                <Typography.Text strong>{selectedOrg.name}</Typography.Text>
                            </Flex>
                        </Flex>

                        <Input
                            prefix={<Search size={14} />}
                            placeholder={t('sso.org.searchBranch', 'Search branch...')}
                            value={branchSearch}
                            onChange={(e) => setBranchSearch(e.target.value)}
                            allowClear
                        />

                        {filteredBranches.length === 0 ? (
                            <Empty
                                image={Empty.PRESENTED_IMAGE_SIMPLE}
                                description={t('sso.org.noBranchFound', 'No branch found')}
                            />
                        ) : (
                            <Flex vertical gap={8}>
                                {filteredBranches.map((branch) => (
                                    <SelectableItem
                                        key={branch.id}
                                        avatar={<Avatar size={32} shape="square" icon={<MapPin size={16} />} />}
                                        title={branch.name}
                                        description={typeof branch.location === 'string' ? branch.location : undefined}
                                        selected={selectedBranchId === branch.id}
                                        onClick={() => setSelectedBranchId(branch.id)}
                                    />
                                ))}
                            </Flex>
                        )}

                        <Flex justify="space-between">
                            <Button onClick={() => setStep('organization')}>
                                {t('common.back', 'Back')}
                            </Button>
                            <Button
                                type="primary"
                                onClick={handleBranchComplete}
                                disabled={!selectedBranchId}
                            >
                                {t('common.select', 'Select')}
                            </Button>
                        </Flex>
                    </>
                )}
            </Flex>
        </AuthLayout>
    );
}
