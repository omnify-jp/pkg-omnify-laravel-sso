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
import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormWrapper } from '@/components/form';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Organization } from '@/types/models/Organization';

type Props = {
    organizations: Pick<Organization, 'id' | 'console_organization_id' | 'name' | 'slug'>[];
};

type BranchCreateForm = {
    name: string;
    slug: string;
    organization_id: string;
    is_active: boolean;
    is_headquarters: boolean;
};

function toSlug(name: string): string {
    return name
        .toLowerCase()
        .trim()
        .replace(/[^\w\s-]/g, '')
        .replace(/[\s_-]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

export default function AdminBranchesCreate({ organizations }: Props) {
    const { t } = useTranslation();

    const { data, setData, post, processing, errors } = useForm<BranchCreateForm>({
        name: '',
        slug: '',
        organization_id: '',
        is_active: true,
        is_headquarters: false,
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('nav.dashboard', 'Dashboard'), href: '/dashboard' },
        { title: t('admin.branches.title', 'Branches'), href: '/admin/branches' },
        { title: t('admin.branches.create', 'Create Branch'), href: '/admin/branches/create' },
    ];

    const handleNameChange = useCallback(
        (e: React.ChangeEvent<HTMLInputElement>) => {
            const name = e.target.value;
            setData((prev) => ({
                ...prev,
                name,
                slug: prev.slug === toSlug(prev.name) ? toSlug(name) : prev.slug,
            }));
        },
        [setData],
    );

    const handleSubmit = () => {
        post('/admin/branches');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.branches.create', 'Create Branch')} />

            <div className="space-y-section">
                <div>
                    <h1 className="text-page-title font-semibold">
                        {t('admin.branches.create', 'Create Branch')}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {t('admin.branches.createDesc', 'Add a new branch to an organization.')}
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
                                required
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
                                    onChange={handleNameChange}
                                    placeholder={t('admin.branches.namePlaceholder', 'e.g. Tokyo Office')}
                                />
                            </FormField>

                            <FormField
                                id="slug"
                                label={t('admin.branches.slug', 'Slug')}
                                required
                                error={errors.slug}
                                description={t('admin.branches.slugDesc', 'URL-friendly identifier. Auto-generated from name.')}
                            >
                                <Input
                                    id="slug"
                                    type="text"
                                    value={data.slug}
                                    onChange={(e) => setData('slug', e.target.value)}
                                    placeholder="tokyo-office"
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
                            {t('admin.branches.createBranch', 'Create Branch')}
                        </Button>
                    </div>
                </FormWrapper>
            </div>
        </AppLayout>
    );
}
