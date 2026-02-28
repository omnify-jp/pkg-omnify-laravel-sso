<?php

namespace Omnify\Core\Database\Seeders\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Trait for fetching organization and branch data from SSO Console.
 *
 * This trait provides methods to dynamically fetch org/branch IDs from the SSO Console,
 * which is useful in seeders that need to create scoped role assignments without
 * hardcoding UUIDs that may change between environments.
 *
 * Features:
 * - API fallback: Tries Console API first, falls back to direct database query
 * - Auto-discovery: Tries common database names (auth_omnify, etc.)
 * - Configurable: Set SSO_CONSOLE_DATABASE env var for custom database name
 *
 * @example
 * ```php
 * class MySeeder extends Seeder
 * {
 *     use FetchesConsoleData;
 *
 *     public function run(): void
 *     {
 *         $organizationData = $this->fetchOrgDataFromConsole('company-abc');
 *
 *         if ($organizationData) {
 *             $organizationId = $organizationData['organization_id'];
 *             $branches = $organizationData['branches']; // ['HQ' => 'uuid', 'TOKYO' => 'uuid']
 *         }
 *     }
 * }
 * ```
 *
 * @see \Omnify\Core\Database\Seeders\Concerns\AssignsRoles
 * @see \Omnify\Core\Database\Seeders\SsoRolesSeeder
 */
trait FetchesConsoleData
{
    /**
     * Fetch organization and branch data from SSO Console.
     *
     * Tries Console API first, falls back to direct database query.
     *
     * @param  string  $organizationId  Organization slug (e.g., 'company-abc')
     * @return array|null ['organization_id' => string, 'organization_name' => string, 'branches' => array]
     */
    protected function fetchOrgDataFromConsole(string $organizationId): ?array
    {
        $consoleUrl = config('omnify-auth.console.url');

        if (! $consoleUrl) {
            $this->logWarning('SSO Console URL not configured');

            return $this->fetchOrgDataFromConsoleDb($organizationId);
        }

        try {
            // Try to fetch from Console's internal API
            $response = Http::timeout(5)
                ->get("{$consoleUrl}/api/internal/organizations/{$organizationId}");

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'organization_id' => $data['id'] ?? null,
                    'organization_name' => $data['name'] ?? $organizationId,
                    'branches' => collect($data['branches'] ?? [])->mapWithKeys(fn ($b) => [
                        $b['slug'] => $b['id'],
                    ])->toArray(),
                ];
            }

            $this->logWarning("Console API returned: {$response->status()} - trying database fallback");

            // Fallback: Try to connect to Console's database directly
            return $this->fetchOrgDataFromConsoleDb($organizationId);

        } catch (\Exception $e) {
            $this->logWarning("Could not connect to Console: {$e->getMessage()}");

            // Try database fallback
            return $this->fetchOrgDataFromConsoleDb($organizationId);
        }
    }

    /**
     * Fetch org data directly from Console database.
     * Works when Console (auth-omnify) is on the same MySQL server.
     */
    protected function fetchOrgDataFromConsoleDb(string $organizationId): ?array
    {
        try {
            // Console database name - can be configured via env or try common names
            $envDbName = env('SSO_CONSOLE_DATABASE');
            $possibleDbNames = array_filter([
                $envDbName,
                'auth_omnify',
                'auth_omnify_db',
                'omnify_console',
                'console',
            ]);

            foreach ($possibleDbNames as $consoleDb) {
                try {
                    // Check if we can query the console database
                    $org = DB::select(
                        "SELECT id, name, slug FROM {$consoleDb}.organizations WHERE slug = ? LIMIT 1",
                        [$organizationId]
                    );

                    if (! empty($org)) {
                        $org = $org[0];

                        $branches = DB::select(
                            "SELECT id, code, name FROM {$consoleDb}.branches WHERE organization_id = ?",
                            [$org->id]
                        );

                        $this->logInfo("Found org data in database: {$consoleDb}");

                        return [
                            'organization_id' => $org->id,
                            'organization_name' => $org->name,
                            'branches' => collect($branches)->mapWithKeys(fn ($b) => [$b->slug => $b->id])->toArray(),
                        ];
                    }
                } catch (\Exception $e) {
                    // Try next database name
                    continue;
                }
            }

            return null;

        } catch (\Exception $e) {
            $this->logWarning("Could not access Console database: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Get a specific branch ID by code.
     */
    protected function getBranchId(array $organizationData, string $branchCode): ?string
    {
        return $organizationData['branches'][$branchCode] ?? null;
    }

    /**
     * Log info message (works in seeder context).
     */
    protected function logInfo(string $message): void
    {
        if (property_exists($this, 'command') && $this->command) {
            $this->command->info($message);
        }
    }

    /**
     * Log warning message (works in seeder context).
     */
    protected function logWarning(string $message): void
    {
        if (property_exists($this, 'command') && $this->command) {
            $this->command->warn($message);
        }
    }
}
