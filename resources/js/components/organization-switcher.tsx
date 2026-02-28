import { router } from '@inertiajs/react';
import { Avatar, Button, Card, Empty, Flex, Input, Modal, Steps, Typography } from 'antd';
import { ArrowLeftRight, ArrowRight, Building2, Check, MapPin, Search } from 'lucide-react';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

type Organization = {
    id: string;
    console_organization_id: string;
    name: string;
    slug: string;
    color?: string;
    short_name?: string;
};

type Branch = {
    id: string;
    console_branch_id: string;
    console_organization_id: string;
    name: string;
    slug: string;
    is_headquarters: boolean;
    location?: string;
};

type OrganizationSwitcherProps = {
    current: Organization | null;
    organizations: Organization[];
    currentBranch?: Branch | null;
    branches?: Branch[];
    requireBranch?: boolean;
    onOrganizationChange?: (organization: Organization) => void;
    onBranchChange?: (branch: Branch) => void;
};

function getOrgInitials(name: string): string {
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return name.slice(0, 2).toUpperCase();
}

function SelectableItem({
    avatar,
    title,
    description,
    selected,
    onClick,
}: {
    avatar: React.ReactNode;
    title: string;
    description?: string;
    selected: boolean;
    onClick: () => void;
}) {
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

export function OrganizationSwitcher({
    current,
    organizations,
    currentBranch,
    branches = [],
    requireBranch = false,
    onOrganizationChange,
    onBranchChange,
}: OrganizationSwitcherProps) {
    const { t } = useTranslation();

    const [showModal, setShowModal] = useState(false);
    const [step, setStep] = useState<'organization' | 'branch'>('organization');
    const [tempOrgId, setTempOrgId] = useState('');
    const [tempBranchId, setTempBranchId] = useState('');
    const [branchSearch, setBranchSearch] = useState('');

    const handleOpen = useCallback(() => {
        setShowModal(true);
        setStep('organization');
        setTempOrgId(current?.console_organization_id ?? '');
        setTempBranchId('');
        setBranchSearch('');
    }, [current]);

    const getBranchesByOrg = useCallback(
        (orgConsoleId: string) => branches.filter((b) => b.console_organization_id === orgConsoleId),
        [branches],
    );

    const handleOrganizationNext = useCallback(() => {
        const org = organizations.find((o) => o.console_organization_id === tempOrgId);
        if (!org) return;

        document.cookie = `current_organization_id=${org.console_organization_id};path=/;max-age=31536000;SameSite=Lax`;

        if (!requireBranch) {
            setShowModal(false);
            if (onOrganizationChange) {
                onOrganizationChange(org);
            } else {
                router.reload();
            }
            return;
        }

        setStep('branch');
        setTempBranchId('');
        setBranchSearch('');
    }, [tempOrgId, organizations, requireBranch, onOrganizationChange]);

    const handleBranchComplete = useCallback(() => {
        const branch = getBranchesByOrg(tempOrgId).find((b) => b.console_branch_id === tempBranchId);
        if (!branch) return;

        document.cookie = `current_branch_id=${branch.console_branch_id};path=/;max-age=31536000;SameSite=Lax`;

        setShowModal(false);
        if (onBranchChange) {
            onBranchChange(branch);
        } else {
            router.reload();
        }
    }, [tempOrgId, tempBranchId, getBranchesByOrg, onBranchChange]);

    const availableBranches = tempOrgId ? getBranchesByOrg(tempOrgId) : [];
    const filteredBranches = availableBranches.filter(
        (b) =>
            b.name.toLowerCase().includes(branchSearch.toLowerCase()) ||
            b.location?.toLowerCase().includes(branchSearch.toLowerCase()),
    );

    if (organizations.length === 0) {
        return null;
    }

    const selectedTempOrg = organizations.find((o) => o.console_organization_id === tempOrgId);

    return (
        <>
            {/* Trigger */}
            <Button type="text" onClick={handleOpen} style={{ height: 'auto', padding: '4px 8px', justifyContent: 'flex-start' }}>
                <Flex align="center" gap={8}>
                    <Avatar size={26} shape="square">
                        {current ? getOrgInitials(current.name) : <Building2 size={14} />}
                    </Avatar>
                    <Flex vertical style={{ textAlign: 'left' }}>
                        <Typography.Text strong>
                            {current?.name ?? t('sso.org.noOrgSelected', 'Select organization')}
                        </Typography.Text>
                        {requireBranch && currentBranch && (
                            <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                                {currentBranch.name}
                            </Typography.Text>
                        )}
                    </Flex>
                    {organizations.length > 1 && <ArrowLeftRight size={14} />}
                </Flex>
            </Button>

            {/* Modal */}
            <Modal
                open={showModal}
                onCancel={() => setShowModal(false)}
                title={
                    step === 'organization'
                        ? t('sso.org.selectOrg', 'Select organization')
                        : t('sso.org.selectBranch', 'Select branch')
                }
                footer={null}
                width={520}
            >
                <Flex vertical gap={16}>
                    <Typography.Text type="secondary">
                        {step === 'organization'
                            ? t('sso.org.selectOrgDesc', 'Choose the organization you want to work with')
                            : t('sso.org.selectBranchDesc', 'Choose a branch within the organization')}
                    </Typography.Text>

                    {/* Step indicator */}
                    {requireBranch && (
                        <Steps
                            size="small"
                            current={step === 'organization' ? 0 : 1}
                            items={[
                                { title: t('sso.org.orgStep', 'Organization'), icon: <Building2 size={16} /> },
                                { title: t('sso.org.branchStep', 'Branch'), icon: <MapPin size={16} /> },
                            ]}
                        />
                    )}

                    {/* Organization step */}
                    {step === 'organization' && (
                        <>
                            <Flex vertical gap={8}>
                                {organizations.map((org) => (
                                    <SelectableItem
                                        key={org.console_organization_id}
                                        avatar={<Avatar size={32} shape="square">{getOrgInitials(org.name)}</Avatar>}
                                        title={org.name}
                                        description={
                                            requireBranch
                                                ? t('sso.org.branchCount', {
                                                      count: getBranchesByOrg(org.console_organization_id).length,
                                                      defaultValue: '{{count}} branches',
                                                  })
                                                : undefined
                                        }
                                        selected={tempOrgId === org.console_organization_id}
                                        onClick={() => setTempOrgId(org.console_organization_id)}
                                    />
                                ))}
                            </Flex>

                            <Flex justify="end">
                                <Button
                                    type="primary"
                                    onClick={handleOrganizationNext}
                                    disabled={!tempOrgId}
                                >
                                    <Flex align="center" gap={4}>
                                        {requireBranch ? t('common.next', 'Next') : t('common.select', 'Select')}
                                        {requireBranch && <ArrowRight size={14} />}
                                    </Flex>
                                </Button>
                            </Flex>
                        </>
                    )}

                    {/* Branch step */}
                    {step === 'branch' && selectedTempOrg && (
                        <>
                            {/* Selected org summary */}
                            <Flex align="center" gap={8}>
                                <Avatar size={28} shape="square">
                                    {getOrgInitials(selectedTempOrg.name)}
                                </Avatar>
                                <Flex vertical>
                                    <Typography.Text type="secondary">
                                        {t('sso.org.selectedOrg', 'Selected organization')}
                                    </Typography.Text>
                                    <Typography.Text strong>
                                        {selectedTempOrg.name}
                                    </Typography.Text>
                                </Flex>
                            </Flex>

                            {/* Search */}
                            <Input
                                prefix={<Search size={14} />}
                                placeholder={t('sso.org.searchBranch', 'Search branch...')}
                                value={branchSearch}
                                onChange={(e) => setBranchSearch(e.target.value)}
                                allowClear
                            />

                            {/* Branch list */}
                            {filteredBranches.length === 0 ? (
                                <Empty
                                    image={Empty.PRESENTED_IMAGE_SIMPLE}
                                    description={t('sso.org.noBranchFound', 'No branch found')}
                                />
                            ) : (
                                <Flex vertical gap={8}>
                                    {filteredBranches.map((branch) => (
                                        <SelectableItem
                                            key={branch.console_branch_id}
                                            avatar={<Avatar size={32} shape="square" icon={<MapPin size={16} />} />}
                                            title={branch.name}
                                            description={branch.location}
                                            selected={tempBranchId === branch.console_branch_id}
                                            onClick={() => setTempBranchId(branch.console_branch_id)}
                                        />
                                    ))}
                                </Flex>
                            )}

                            {/* Actions */}
                            <Flex justify="space-between">
                                <Button onClick={() => setStep('organization')}>
                                    {t('common.back', 'Back')}
                                </Button>
                                <Button
                                    type="primary"
                                    onClick={handleBranchComplete}
                                    disabled={!tempBranchId}
                                >
                                    {t('common.select', 'Select')}
                                </Button>
                            </Flex>
                        </>
                    )}
                </Flex>
            </Modal>
        </>
    );
}
