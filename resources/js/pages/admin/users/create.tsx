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
} from '@omnifyjp/ui';
import { LoaderCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { FormField, FormWrapper } from '@/components/form';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Role = {
    id: string;
    slug: string;
    name: string;
    level: number;
};

type Props = {
    roles: Role[];
};

type UserCreateForm = {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    role_id: string;
};

export default function AdminUserCreate({ roles }: Props) {
    const { t } = useTranslation();

    const { data, setData, post, processing, errors } = useForm<UserCreateForm>({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role_id: '',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('nav.dashboard', 'Dashboard'), href: '/dashboard' },
        { title: t('nav.iam', 'IAM'), href: '/admin/iam' },
        { title: t('admin.users.create', 'Create User'), href: '/admin/users/create' },
    ];

    const handleSubmit = () => {
        post('/admin/users');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.users.create', 'Create User')} />

            <div className="space-y-section">
                <div>
                    <h1 className="text-page-title font-semibold">
                        {t('admin.users.create', 'Create User')}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {t('admin.users.createDesc', 'Add a new user to this standalone instance.')}
                    </p>
                </div>

                <FormWrapper onSubmit={handleSubmit} processing={processing} className="max-w-2xl space-y-section">
                    <Card>
                        <CardHeader className="px-card pb-3 pt-card">
                            <CardTitle className="text-base">
                                {t('admin.users.userInfo', 'User Information')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 px-card pb-card">
                            {/* Name */}
                            <FormField
                                id="name"
                                label={t('admin.users.name', 'Name')}
                                required
                                error={errors.name}
                            >
                                <Input
                                    id="name"
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder={t('admin.users.namePlaceholder', 'e.g. Jane Smith')}
                                    autoComplete="name"
                                />
                            </FormField>

                            {/* Email */}
                            <FormField
                                id="email"
                                label={t('admin.users.email', 'Email')}
                                required
                                error={errors.email}
                            >
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    placeholder={t('admin.users.emailPlaceholder', 'user@example.com')}
                                    autoComplete="email"
                                />
                            </FormField>

                            {/* Password */}
                            <FormField
                                id="password"
                                label={t('admin.users.password', 'Password')}
                                required
                                error={errors.password}
                            >
                                <Input
                                    id="password"
                                    type="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    placeholder={t('admin.users.passwordPlaceholder', 'Minimum 8 characters')}
                                    autoComplete="new-password"
                                />
                            </FormField>

                            {/* Password Confirmation */}
                            <FormField
                                id="password_confirmation"
                                label={t('admin.users.passwordConfirmation', 'Confirm Password')}
                                required
                                error={errors.password_confirmation}
                            >
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    value={data.password_confirmation}
                                    onChange={(e) => setData('password_confirmation', e.target.value)}
                                    placeholder={t('admin.users.passwordConfirmationPlaceholder', 'Repeat password')}
                                    autoComplete="new-password"
                                />
                            </FormField>

                            {/* Role */}
                            <FormField
                                id="role_id"
                                label={t('admin.users.role', 'Role')}
                                description={t('common.optional', 'optional')}
                                error={errors.role_id}
                            >
                                <Select
                                    value={data.role_id}
                                    onValueChange={(value) => setData('role_id', value)}
                                >
                                    <SelectTrigger id="role_id">
                                        <SelectValue
                                            placeholder={t('admin.users.rolePlaceholder', 'Select a role...')}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {roles.map((role) => (
                                            <SelectItem key={role.id} value={role.id}>
                                                {role.name}
                                                <span className="ml-1.5 text-xs text-muted-foreground">
                                                    (level {role.level})
                                                </span>
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>
                        </CardContent>
                    </Card>

                    {/* Actions */}
                    <div className="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => window.history.back()}
                        >
                            {t('common.cancel', 'Cancel')}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && (
                                <LoaderCircle className="h-4 w-4 animate-spin" />
                            )}
                            {t('admin.users.createUser', 'Create User')}
                        </Button>
                    </div>
                </FormWrapper>
            </div>
        </AppLayout>
    );
}
