# SSO Organization Sync - Full Implementation Guide

> **Status: ✅ IMPLEMENTED**
> 
> This feature is implemented in `@famgia/omnify-react-sso` package.
> - `getOrgIdForApi()`, `setOrgIdForApi()`, `clearOrgIdForApi()` are exported
> - `apiClient.ts` uses these functions to add `X-Organization-Id` header

## Overview

This document describes the complete solution for syncing organization data between SSO (auth-omnify) and local application, ensuring API calls work correctly with `X-Organization-Id` header.

## The Problem

### Issue 1: Frontend Race Condition
When using `@famgia/omnify-react-sso` package, the organization ID may not be available when API calls are made during initial render.

**Root Cause:**
1. `useOrganization()` hook provides `currentOrganization` in React state
2. API client needs the org ID to add `X-Organization-Id` header
3. React's `useEffect` runs AFTER render, so org ID isn't synced in time
4. API calls made during initial render fail with `MISSING_ORGANIZATION` error

### Issue 2: Backend ID Mismatch
The SSO package stores organization data with two IDs:
- `id`: Auto-generated local UUID (primary key)
- `console_organization_id`: SSO's organization ID

Frontend sends `currentOrganization.id` (SSO org ID) as `X-Organization-Id`, but backend lookup uses local `id` field, causing `ORGANIZATION_NOT_FOUND` error.

---

## Solution Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         FRONTEND                                 │
├─────────────────────────────────────────────────────────────────┤
│  OrganizationGate                                                │
│  - Sync org ID IMMEDIATELY (not in useEffect)                   │
│  - Block render until org selected                               │
│                           │                                      │
│                           ▼                                      │
│  orgApiSync Module                                               │
│  - setOrgIdForApi() → memory + localStorage                      │
│  - getOrgIdForApi() → for API requests                          │
│                           │                                      │
│                           ▼                                      │
│  apiClient                                                       │
│  - Adds X-Organization-Id header from getOrgIdForApi()          │
└─────────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                         BACKEND                                  │
├─────────────────────────────────────────────────────────────────┤
│  SsoOrganizationAccess Middleware                                │
│  - Validates X-Organization-Id header                           │
│  - Lookup by organizations.id                             │
│                           │                                      │
│                           ▼                                      │
│  organizations Table                                       │
│  - id = console_organization_id (synced by listener)                     │
│  - Ensures header matches local DB                              │
└─────────────────────────────────────────────────────────────────┘
```

---

## Frontend Implementation

### 1. orgApiSync Module

**File:** `resources/js/lib/orgApiSync.ts`

```typescript
/**
 * Organization API Sync Module
 * Ensures org ID is always available for API calls.
 */

let _currentOrganizationId: string | null = null;
const ORG_ID_STORAGE_KEY = 'api_current_org_id';

export function setOrgIdForApi(orgId: string | null): void {
    _currentOrganizationId = orgId;
    
    if (typeof window === 'undefined') return;
    
    if (orgId) {
        localStorage.setItem(ORG_ID_STORAGE_KEY, orgId);
    } else {
        localStorage.removeItem(ORG_ID_STORAGE_KEY);
    }
}

export function getOrgIdForApi(): string | null {
    if (_currentOrganizationId) return _currentOrganizationId;
    
    if (typeof window === 'undefined') return null;
    
    const stored = localStorage.getItem(ORG_ID_STORAGE_KEY);
    if (stored) {
        _currentOrganizationId = stored;
        return stored;
    }
    
    return null;
}

export function clearOrgIdForApi(): void {
    _currentOrganizationId = null;
    if (typeof window !== 'undefined') {
        localStorage.removeItem(ORG_ID_STORAGE_KEY);
    }
}

export function hasOrgIdForApi(): boolean {
    return getOrgIdForApi() !== null;
}
```

### 2. OrganizationGate Component

**File:** `resources/js/components/OrganizationGate.tsx`

```tsx
import { useOrganization, useSso } from '@famgia/omnify-react-sso';
import { setOrgIdForApi } from '@/lib/orgApiSync';

export function OrganizationGate({ children }: { children: React.ReactNode }) {
    const { isLoading, isAuthenticated } = useSso();
    const { organizations, currentOrganization, switchOrganization } = useOrganization();

    // CRITICAL: Sync org ID IMMEDIATELY (not in useEffect!)
    // This ensures the org ID is set BEFORE child components render
    if (currentOrganization?.id) {
        setOrgIdForApi(currentOrganization.id);
    }

    // Show loading while checking auth
    if (isLoading) {
        return <LoadingSpinner />;
    }

    // If no org selected, show org selector
    if (!currentOrganization) {
        return <OrganizationSelectorModal organizations={organizations} onSelect={switchOrganization} />;
    }

    // Org is selected, render children
    return <>{children}</>;
}
```

### 3. API Client Integration

**File:** `resources/js/lib/apiClient.ts`

```typescript
import { getOrgIdForApi } from './orgApiSync';

export async function apiRequest<T>(
    url: string,
    options: RequestInit = {}
): Promise<T> {
    const headers: Record<string, string> = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        ...options.headers,
    };

    // Add organization header
    const orgId = getOrgIdForApi();
    if (orgId) {
        headers['X-Organization-Id'] = orgId;
    }

    const response = await fetch(url, { ...options, headers });
    return response.json();
}
```

### 4. TanStack Query Integration

```tsx
// In components using ProTable or useQuery
<ProTable
    queryKey={['employees', currentOrganization?.id]}
    queryFn={fetchEmployees}
    queryEnabled={!!currentOrganization?.id}  // Don't query until org is ready
/>
```

---

## Backend Implementation

### 1. Organization Cache Seeder

**File:** `database/seeders/OrganizationSeeder.php`

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrganizationSeeder extends Seeder
{
    /**
     * Development/test organization data.
     *
     * IMPORTANT: The 'id' field MUST match the SSO org ID from auth-omnify.
     * This ensures the X-Organization-Id header sent from frontend matches the local DB.
     *
     * To get SSO org IDs:
     * 1. Login to the app
     * 2. Open browser console
     * 3. Run: localStorage.getItem('api_current_org_id')
     */
    private array $organizations = [
        [
            'id' => '019bdb7a-7413-7072-a53f-76d5cac58be1',
            'console_organization_id' => '019bdb7a-7413-7072-a53f-76d5cac58be1',
            'code' => 'company-abc',
            'name' => 'Company ABC',
            'is_active' => true,
        ],
        [
            'id' => '019bdb7a-7417-7195-b96b-4962c7ebcd0d',
            'console_organization_id' => '019bdb7a-7417-7195-b96b-4962c7ebcd0d',
            'code' => 'company-xyz',
            'name' => 'Company XYZ',
            'is_active' => true,
        ],
    ];

    public function run(): void
    {
        foreach ($this->organizations as $org) {
            DB::table('organizations')->updateOrInsert(
                ['code' => $org['code']],
                [
                    'id' => $org['id'],
                    'console_organization_id' => $org['console_organization_id'],
                    'name' => $org['name'],
                    'is_active' => $org['is_active'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info('Organization caches seeded with SSO org IDs.');
    }
}
```

### 2. Organization Sync Listener

**File:** `app/Listeners/SetupOrganizationDefaults.php`

```php
<?php

namespace App\Listeners;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Omnify\SsoClient\Events\OrganizationCreated;

class SetupOrganizationDefaults
{
    public function handle(OrganizationCreated $event): void
    {
        $org = $event->organization;

        Log::info('Organization cached', [
            'organization_id' => $org->console_organization_id,
            'name' => $org->name,
        ]);

        // Sync the local ID with SSO org ID
        $this->syncOrganizationId($org);

        // Create org-specific roles if needed
        $this->createOrgSpecificRoles($org->console_organization_id);
    }

    /**
     * Sync organizations.id with console_organization_id.
     *
     * The frontend sends SSO's org ID as X-Organization-Id header.
     * We need the local DB's `id` to match for proper lookup.
     */
    protected function syncOrganizationId(mixed $org): void
    {
        if ($org->id !== $org->console_organization_id) {
            DB::table('organizations')
                ->where('id', $org->id)
                ->update(['id' => $org->console_organization_id]);

            Log::info('Synced organization ID with SSO', [
                'old_id' => $org->id,
                'new_id' => $org->console_organization_id,
            ]);
        }
    }

    // ... other methods
}
```

### 3. Register Listener

**File:** `app/Providers/AppServiceProvider.php`

```php
use Illuminate\Support\Facades\Event;
use Omnify\SsoClient\Events\OrganizationCreated;
use App\Listeners\SetupOrganizationDefaults;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(
            OrganizationCreated::class,
            SetupOrganizationDefaults::class
        );
    }
}
```

---

## Database Schema

**Table:** `organizations`

| Column | Type | Description |
|--------|------|-------------|
| id | uuid | Primary key - **MUST match SSO org ID** |
| console_organization_id | uuid | SSO org ID (same as id after sync) |
| code | string | Organization slug |
| name | string | Organization name |
| is_active | boolean | Active status |

---

## Key Points

### Frontend
1. **NEVER use useEffect for org sync** - It runs after render, causing race conditions
2. **Sync during render phase** - Set org ID before children render
3. **Persist to localStorage** - For page reload persistence
4. **Gate queries with org ID** - Use `queryEnabled={!!currentOrganization?.id}`

### Backend
1. **Seeder uses SSO org IDs** - Not auto-generated UUIDs
2. **Listener syncs new orgs** - When user logs in with new org
3. **ID = console_organization_id** - Frontend header matches DB lookup

---

## Troubleshooting

### Error: MISSING_ORGANIZATION
**Cause:** Org ID not synced to localStorage before API call
**Fix:** Ensure `setOrgIdForApi()` is called in render phase (not useEffect)

### Error: ORGANIZATION_NOT_FOUND
**Cause:** DB `id` doesn't match SSO org ID
**Fix:** 
1. Run seeder: `php artisan db:seed --class=OrganizationSeeder`
2. Or manually sync: 
```php
DB::table('organizations')
    ->where('code', 'company-abc')
    ->update(['id' => 'sso-org-id-here']);
```

### How to Get SSO Org ID
1. Login to the app
2. Open browser DevTools > Console
3. Run: `localStorage.getItem('api_current_org_id')`

---

## Future: Integration into @famgia/omnify-react-sso

To prevent this issue in all applications, the `orgApiSync` module should be integrated into the SSO package:

1. Copy `orgApiSync.ts` → `src/lib/orgApiSync.ts`
2. Update `SsoProvider` to call `setOrgIdForApi(currentOrganization.id)` during render
3. Export functions from package index
4. Update documentation

See the `orgApiSync.ts` section above for the full implementation.
