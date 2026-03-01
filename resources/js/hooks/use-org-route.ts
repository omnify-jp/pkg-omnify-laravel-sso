import { useCallback } from 'react';
import { useOrgPrefix } from './use-org-prefix';

/**
 * Tạo org-scoped URL.
 *
 * URL mode:    orgRoute('/dashboard') → '/@acme/dashboard'
 * Cookie-only: orgRoute('/dashboard') → '/dashboard'
 */
export function useOrgRoute() {
    const orgPrefix = useOrgPrefix();
    return useCallback((path: string) => `${orgPrefix}${path}`, [orgPrefix]);
}
