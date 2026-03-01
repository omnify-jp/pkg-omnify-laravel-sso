import { usePage } from '@inertiajs/react';

/**
 * Returns the URL prefix for organization-scoped routes.
 *
 * When org URL routing is enabled (org_url_mode=true): returns `/@{slug}`.
 * When disabled (cookie-only mode): returns empty string.
 *
 * Reads directly from Inertia page props (no React context required),
 * so it works at any level in the component tree.
 *
 * Usage: `const prefix = useOrgPrefix(); router.visit(`${prefix}/dashboard`);`
 */
export function useOrgPrefix(): string {
    const { org_url_mode, organization } = usePage<{
        org_url_mode: boolean;
        organization?: { slug?: string | null };
    }>().props;

    if (!org_url_mode) return '';
    return organization?.slug ? `/@${organization.slug}` : '';
}
