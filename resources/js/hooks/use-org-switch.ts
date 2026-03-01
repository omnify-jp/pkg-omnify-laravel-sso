import { useCallback } from 'react';
import { router, usePage } from '@inertiajs/react';

/**
 * Switch organization — set cookies + navigate.
 *
 * URL mode:    set cookies → router.visit('/@{slug}/dashboard')
 * Cookie-only: set cookies → router.reload()
 */
export function useOrgSwitch() {
    const { org_url_mode } = usePage<{ org_url_mode: boolean }>().props;

    return useCallback((slug: string, consoleOrgId: string, redirectPath = '/dashboard') => {
        document.cookie = `current_organization_id=${consoleOrgId};path=/;max-age=31536000;SameSite=Lax`;
        document.cookie = `current_organization_slug=${slug};path=/;max-age=31536000;SameSite=Lax`;

        if (org_url_mode) {
            router.visit(`/@${slug}${redirectPath}`);
        } else {
            router.reload();
        }
    }, [org_url_mode]);
}
