import {
    Button, Dialog, DialogContent, DialogDescription,
    DialogHeader, DialogTitle, Input,
} from '@omnifyjp/ui';
import { router } from '@inertiajs/react';
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
        (b) => b.name.toLowerCase().includes(branchSearch.toLowerCase()) || b.location?.toLowerCase().includes(branchSearch.toLowerCase()),
    );

    if (organizations.length === 0) {
        return null;
    }

    const selectedTempOrg = organizations.find((o) => o.console_organization_id === tempOrgId);

    return (
        <>
            <Button variant="outline" onClick={handleOpen} className="gap-2 px-3">
                <div className="flex h-5 w-5 shrink-0 items-center justify-center rounded bg-primary text-[10px] font-bold text-primary-foreground">
                    {current ? getOrgInitials(current.name) : <Building2 className="h-3 w-3" />}
                </div>
                <div className="flex min-w-0 items-center gap-1.5">
                    <span className="max-w-[120px] truncate text-sm font-medium">{current?.name ?? t('sso.org.noOrgSelected', 'Select organization')}</span>
                    {requireBranch && currentBranch && (
                        <>
                            <span className="text-muted-foreground">/</span>
                            <span className="max-w-[100px] truncate text-sm text-muted-foreground">{currentBranch.name}</span>
                        </>
                    )}
                </div>
                {organizations.length > 1 && <ArrowLeftRight className="h-3.5 w-3.5 shrink-0 text-muted-foreground" />}
            </Button>

            <Dialog open={showModal} onOpenChange={setShowModal}>
                <DialogContent className="sm:max-w-[520px]">
                    <DialogHeader>
                        <DialogTitle className="text-xl font-semibold">
                            {step === 'organization' ? t('sso.org.selectOrg', 'Select organization') : t('sso.org.selectBranch', 'Select branch')}
                        </DialogTitle>
                        <DialogDescription className="text-sm">
                            {step === 'organization'
                                ? t('sso.org.selectOrgDesc', 'Choose the organization you want to work with')
                                : t('sso.org.selectBranchDesc', 'Choose a branch within the organization')}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="mt-4">
                        {requireBranch && (
                            <div className="mb-6 flex items-center gap-2">
                                <div
                                    className={`flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium ${
                                        step === 'organization' ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground'
                                    }`}
                                >
                                    <Building2 className="h-4 w-4" />
                                    {t('sso.org.orgStep', 'Organization')}
                                </div>
                                <ArrowRight className="h-4 w-4 text-muted-foreground" />
                                <div
                                    className={`flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium ${
                                        step === 'branch' ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground'
                                    }`}
                                >
                                    <MapPin className="h-4 w-4" />
                                    {t('sso.org.branchStep', 'Branch')}
                                </div>
                            </div>
                        )}

                        {step === 'organization' && (
                            <div>
                                <div className="space-y-2">
                                    {organizations.map((org) => (
                                        <div
                                            key={org.console_organization_id}
                                            className={`relative flex cursor-pointer items-center gap-3 rounded-lg border-2 p-4 transition-all ${
                                                tempOrgId === org.console_organization_id
                                                    ? 'border-primary bg-primary/5'
                                                    : 'border-border hover:border-muted-foreground/30 hover:bg-accent'
                                            }`}
                                            onClick={() => setTempOrgId(org.console_organization_id)}
                                        >
                                            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary text-sm font-bold text-primary-foreground">
                                                {getOrgInitials(org.name)}
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <div className="text-base font-semibold">{org.name}</div>
                                                {requireBranch && (
                                                    <div className="mt-1 text-xs text-muted-foreground">
                                                        {t('sso.org.branchCount', { count: getBranchesByOrg(org.console_organization_id).length, defaultValue: '{{count}} branches' })}
                                                    </div>
                                                )}
                                            </div>
                                            {tempOrgId === org.console_organization_id && (
                                                <div className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary">
                                                    <Check className="h-4 w-4 text-primary-foreground" />
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>

                                <div className="mt-6 flex justify-end">
                                    <Button onClick={handleOrganizationNext} disabled={!tempOrgId} size="lg" className="gap-2">
                                        {requireBranch ? t('common.next', 'Next') : t('common.select', 'Select')}
                                        <ArrowRight className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        )}

                        {step === 'branch' && selectedTempOrg && (
                            <div>
                                <div className="mb-4 rounded-lg border border-border bg-muted p-3">
                                    <div className="mb-1.5 text-xs text-muted-foreground">{t('sso.org.selectedOrg', 'Selected organization')}</div>
                                    <div className="flex items-center gap-2">
                                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-xs font-bold text-primary-foreground">
                                            {getOrgInitials(selectedTempOrg.name)}
                                        </div>
                                        <span className="font-semibold">{selectedTempOrg.name}</span>
                                    </div>
                                </div>

                                <div className="relative mb-3">
                                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        placeholder={t('sso.org.searchBranch', 'Search branch...')}
                                        value={branchSearch}
                                        onChange={(e) => setBranchSearch(e.target.value)}
                                        className="pl-9"
                                    />
                                </div>

                                <div className="max-h-[280px] space-y-2 overflow-y-auto">
                                    {filteredBranches.length === 0 ? (
                                        <div className="py-6 text-center text-sm text-muted-foreground">{t('sso.org.noBranchFound', 'No branch found')}</div>
                                    ) : (
                                        filteredBranches.map((branch) => (
                                            <div
                                                key={branch.console_branch_id}
                                                className={`relative flex cursor-pointer items-center gap-3 rounded-lg border-2 p-4 transition-all ${
                                                    tempBranchId === branch.console_branch_id
                                                        ? 'border-primary bg-primary/5'
                                                        : 'border-border hover:border-muted-foreground/30 hover:bg-accent'
                                                }`}
                                                onClick={() => setTempBranchId(branch.console_branch_id)}
                                            >
                                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-muted">
                                                    <MapPin className="h-5 w-5 text-muted-foreground" />
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <div className="text-base font-semibold">{branch.name}</div>
                                                    {branch.location && <div className="text-sm text-muted-foreground">{branch.location}</div>}
                                                </div>
                                                {tempBranchId === branch.console_branch_id && (
                                                    <div className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary">
                                                        <Check className="h-4 w-4 text-primary-foreground" />
                                                    </div>
                                                )}
                                            </div>
                                        ))
                                    )}
                                </div>

                                <div className="mt-6 flex justify-between">
                                    <Button
                                        variant="outline"
                                        onClick={() => {
                                            setStep('organization');
                                        }}
                                    >
                                        {t('common.back', 'Back')}
                                    </Button>
                                    <Button onClick={handleBranchComplete} disabled={!tempBranchId} size="lg" className="gap-2">
                                        {t('common.select', 'Select')}
                                        <ArrowRight className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
