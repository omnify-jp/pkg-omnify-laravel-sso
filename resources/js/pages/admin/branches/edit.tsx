import { Head, useForm } from '@inertiajs/react';
import {
    Button,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    Input,
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
    Switch,
} from '@omnifyjp/ui';
import { LoaderCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { FormField, FormWrapper } from '@/components/form';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Branch } from '@/types/models/Branch';
import type { Organization } from '@/types/models/Organization';

type Props = {
    branch: Branch;
    organizations: Pick<Organization, 'id' | 'console_organization_id' | 'name' | 'slug'>[];
};

type BranchUpdateForm = {
    name: string;
    slug: string;
    organization_id: string;
    is_active: boolean;
    is_headquarters: boolean;
};

export default function AdminBranchesEdit({ branch, organizations }: Props) {
    const { t } = useTranslation();

    // Find the current organization by matching console_organization_id
    const currentOrg = organizations.find(
        (o) => o.console_organization_id === branch.console_organization_id,
    );

    const { data, setData, put, processing, errors } = useForm<BranchUpdateForm>({
        name: branch.name,
        slug: branch.slug,
        organization_id: currentOrg?.id ?? '',
        is_active: branch.is_active,
        is_headquarters: branch.is_headquarters,
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('nav.dashboard', 'Dashboard'), href: '/dashboard' },
        { title: t('admin.branches.title', 'Branches'), href: '/admin/branches' },
        { title: t('admin.branches.edit', 'Edit Branch'), href: `/admin/branches/${branch.id}/edit` },
    ];

    const handleSubmit = () => {
        put(`/admin/branches/${branch.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.branches.edit', 'Edit Branch')} />

            <div className="space-y-section">
                <div>
                    <h1 className="text-page-title font-semibold">
                        {t('admin.branches.edit', 'Edit Branch')}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {branch.name}
                    </p>
                </div>

                <FormWrapper onSubmit={handleSubmit} processing={processing} className="max-w-2xl space-y-section">
                    <Card>
                        <CardHeader className="px-card pb-3 pt-card">
                            <CardTitle className="text-base">
                                {t('admin.branches.branchInfo', 'Branch Information')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 px-card pb-card">
                            <FormField
                                id="organization_id"
                                label={t('admin.branches.organization', 'Organization')}
                                error={errors.organization_id}
                            >
                                <Select
                                    value={data.organization_id}
                                    onValueChange={(value) => setData('organization_id', value)}
                                >
                                    <SelectTrigger id="organization_id">
                                        <SelectValue
                                            placeholder={t('admin.branches.selectOrg', 'Select an organization...')}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {organizations.map((org) => (
                                            <SelectItem key={org.id} value={org.id}>
                                                {org.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField
                                id="name"
                                label={t('admin.branches.name', 'Name')}
                                required
                                error={errors.name}
                            >
                                <Input
                                    id="name"
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                />
                            </FormField>

                            <FormField
                                id="slug"
                                label={t('admin.branches.slug', 'Slug')}
                                required
                                error={errors.slug}
                                description={t('admin.branches.slugDesc', 'URL-friendly identifier.')}
                            >
                                <Input
                                    id="slug"
                                    type="text"
                                    value={data.slug}
                                    onChange={(e) => setData('slug', e.target.value)}
                                />
                            </FormField>

                            <div className="flex items-center gap-3">
                                <Switch
                                    id="is_headquarters"
                                    checked={data.is_headquarters}
                                    onCheckedChange={(checked) =>
                                        setData('is_headquarters', checked)
                                    }
                                />
                                <label
                                    htmlFor="is_headquarters"
                                    className="cursor-pointer text-sm font-medium"
                                >
                                    {t('admin.branches.isHeadquarters', 'Headquarters')}
                                </label>
                            </div>

                            <div className="flex items-center gap-3">
                                <Switch
                                    id="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked) => setData('is_active', checked)}
                                />
                                <label
                                    htmlFor="is_active"
                                    className="cursor-pointer text-sm font-medium"
                                >
                                    {t('admin.branches.isActive', 'Active')}
                                </label>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => window.history.back()}
                        >
                            {t('common.cancel', 'Cancel')}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                            {t('common.save', 'Save Changes')}
                        </Button>
                    </div>
                </FormWrapper>
            </div>
        </AppLayout>
    );
}
