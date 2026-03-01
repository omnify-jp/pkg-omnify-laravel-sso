import { useOrganization } from '@omnify-core/hooks/use-organization';

/**
 * Returns the URL prefix for organization-scoped routes.
 *
 * When org URL routing is enabled: returns `/@{slug}` (e.g. `/@acme`).
 * When disabled (no org or cookie-only mode): returns empty string.
 *
 * Usage: `const prefix = useOrgPrefix(); router.visit(`${prefix}/dashboard`);`
 */
export function useOrgPrefix(): string {
    const { organization } = useOrganization();
    return organization?.slug ? `/@${organization.slug}` : '';
}
