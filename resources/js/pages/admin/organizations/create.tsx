import { Head, useForm } from '@inertiajs/react';
import {
    Button,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    Input,
    Switch,
} from '@omnifyjp/ui';
import { LoaderCircle } from 'lucide-react';
import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormWrapper } from '@/components/form';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type OrgCreateForm = {
    name: string;
    slug: string;
    is_active: boolean;
};

function toSlug(name: string): string {
    return name
        .toLowerCase()
        .trim()
        .replace(/[^\w\s-]/g, '')
        .replace(/[\s_-]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

export default function AdminOrganizationsCreate() {
    const { t } = useTranslation();

    const { data, setData, post, processing, errors } = useForm<OrgCreateForm>({
        name: '',
        slug: '',
        is_active: true,
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('nav.dashboard', 'Dashboard'), href: '/dashboard' },
        { title: t('admin.organizations.title', 'Organizations'), href: '/admin/organizations' },
        { title: t('admin.organizations.create', 'Create Organization'), href: '/admin/organizations/create' },
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
        post('/admin/organizations');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.organizations.create', 'Create Organization')} />

            <div className="space-y-section">
                <div>
                    <h1 className="text-page-title font-semibold">
                        {t('admin.organizations.create', 'Create Organization')}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {t('admin.organizations.createDesc', 'Add a new organization to this standalone instance.')}
                    </p>
                </div>

                <FormWrapper onSubmit={handleSubmit} processing={processing} className="max-w-2xl space-y-section">
                    <Card>
                        <CardHeader className="px-card pb-3 pt-card">
                            <CardTitle className="text-base">
                                {t('admin.organizations.orgInfo', 'Organization Information')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 px-card pb-card">
                            <FormField
                                id="name"
                                label={t('admin.organizations.name', 'Name')}
                                required
                                error={errors.name}
                            >
                                <Input
                                    id="name"
                                    type="text"
                                    value={data.name}
                                    onChange={handleNameChange}
                                    placeholder={t('admin.organizations.namePlaceholder', 'e.g. Acme Corporation')}
                                />
                            </FormField>

                            <FormField
                                id="slug"
                                label={t('admin.organizations.slug', 'Slug')}
                                required
                                error={errors.slug}
                                description={t('admin.organizations.slugDesc', 'URL-friendly identifier. Auto-generated from name.')}
                            >
                                <Input
                                    id="slug"
                                    type="text"
                                    value={data.slug}
                                    onChange={(e) => setData('slug', e.target.value)}
                                    placeholder="acme-corporation"
                                />
                            </FormField>

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
                                    {t('admin.organizations.isActive', 'Active')}
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
                            {t('admin.organizations.createOrg', 'Create Organization')}
                        </Button>
                    </div>
                </FormWrapper>
            </div>
        </AppLayout>
    );
}
