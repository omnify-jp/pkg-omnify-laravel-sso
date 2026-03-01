import { Avatar, Button, Card, Empty, Flex, Input, Modal, Steps, Typography } from 'antd';
import { ArrowRight, Building2, Check, MapPin, Search } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import type { SsoBranch, SsoOrganization } from '@omnify-core/types/sso';

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

export type OrganizationSelectionModalProps = {
    open: boolean;
    onClose: () => void;
    current: SsoOrganization | null;
    organizations: SsoOrganization[];
    branches: SsoBranch[];
    requireBranch: boolean;
    closable?: boolean;
    onOrganizationChange?: (organization: SsoOrganization) => void;
    onBranchChange?: (branch: SsoBranch) => void;
};

export function OrganizationSelectionModal({
    open,
    onClose,
    current,
    organizations,
    branches,
    requireBranch,
    closable = true,
    onOrganizationChange,
    onBranchChange,
}: OrganizationSelectionModalProps) {
    const { t } = useTranslation();

    const [step, setStep] = useState<'organization' | 'branch'>('organization');
    const [tempOrgId, setTempOrgId] = useState('');
    const [tempBranchId, setTempBranchId] = useState('');
    const [branchSearch, setBranchSearch] = useState('');

    useEffect(() => {
        if (open) {
            setStep('organization');
            setTempOrgId(current?.id ?? '');
            setTempBranchId('');
            setBranchSearch('');
        }
    }, [open, current]);

    const getBranchesByOrg = useCallback(
        (orgId: string) => branches.filter((b) => b.organization_id === orgId),
        [branches],
    );

    const handleOrganizationNext = useCallback(() => {
        const org = organizations.find((o) => o.id === tempOrgId);
        if (!org) return;

        if (!requireBranch) {
            onClose();
            onOrganizationChange?.(org);
            return;
        }

        setStep('branch');
        setTempBranchId('');
        setBranchSearch('');
    }, [tempOrgId, organizations, requireBranch, onOrganizationChange, onClose]);

    const handleBranchComplete = useCallback(() => {
        const branch = getBranchesByOrg(tempOrgId).find((b) => b.id === tempBranchId);
        if (!branch) return;

        onClose();
        onBranchChange?.(branch);
    }, [tempOrgId, tempBranchId, getBranchesByOrg, onBranchChange, onClose]);

    const availableBranches = tempOrgId ? getBranchesByOrg(tempOrgId) : [];
    const filteredBranches = availableBranches.filter(
        (b) =>
            b.name.toLowerCase().includes(branchSearch.toLowerCase()) ||
            (typeof b.location === 'string' && b.location.toLowerCase().includes(branchSearch.toLowerCase())),
    );

    const selectedTempOrg = organizations.find((o) => o.id === tempOrgId);

    return (
        <Modal
            open={open}
            onCancel={onClose}
            title={
                step === 'organization'
                    ? t('sso.org.selectOrg', 'Select organization')
                    : t('sso.org.selectBranch', 'Select branch')
            }
            footer={null}
            width={520}
            closable={closable}
            mask={{ closable }}
            keyboard={closable}
        >
            <Flex vertical gap={16}>
                <Typography.Text type="secondary">
                    {step === 'organization'
                        ? t('sso.org.selectOrgDesc', 'Choose the organization you want to work with')
                        : t('sso.org.selectBranchDesc', 'Choose a branch within the organization')}
                </Typography.Text>

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

                {step === 'organization' && (
                    <>
                        <Flex vertical gap={8}>
                            {organizations.map((org) => (
                                <SelectableItem
                                    key={org.id}
                                    avatar={<Avatar size={32} shape="square">{getOrgInitials(org.name)}</Avatar>}
                                    title={org.name}
                                    description={
                                        requireBranch
                                            ? t('sso.org.branchCount', {
                                                  count: getBranchesByOrg(org.id).length,
                                                  defaultValue: '{{count}} branches',
                                              })
                                            : undefined
                                    }
                                    selected={tempOrgId === org.id}
                                    onClick={() => setTempOrgId(org.id)}
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

                {step === 'branch' && selectedTempOrg && (
                    <>
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
                                        selected={tempBranchId === branch.id}
                                        onClick={() => setTempBranchId(branch.id)}
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
                                disabled={!tempBranchId}
                            >
                                {t('common.select', 'Select')}
                            </Button>
                        </Flex>
                    </>
                )}
            </Flex>
        </Modal>
    );
}
