<?php

declare(strict_types=1);

namespace Omnify\Core\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Omnify\Core\Exceptions\ConsoleAccessDeniedException;
use Omnify\Core\Exceptions\ConsoleApiException;
use Omnify\Core\Exceptions\ConsoleAuthException;
use Omnify\Core\Exceptions\ConsoleNotFoundException;
use Omnify\Core\Exceptions\ConsoleServerException;

class ConsoleApiService
{
    public function __construct(
        private readonly string $consoleUrl,
        private readonly string $serviceSlug,
        private readonly int $timeout = 10,
        private readonly int $retry = 2
    ) {}

    /**
     * Exchange SSO code for tokens.
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int}|null
     */
    public function exchangeCode(string $code): ?array
    {
        $response = $this->request()
            ->post("{$this->consoleUrl}/api/sso/token", [
                'code' => $code,
                'service_slug' => $this->serviceSlug,
            ]);

        if (! $response->successful()) {
            $this->handleError($response->status(), $response->json());

            return null;
        }

        return $response->json();
    }

    /**
     * Refresh access token.
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int}|null
     */
    public function refreshToken(string $refreshToken): ?array
    {
        $response = $this->request()
            ->post("{$this->consoleUrl}/api/sso/refresh", [
                'refresh_token' => $refreshToken,
                'service_slug' => $this->serviceSlug,
            ]);

        if (! $response->successful()) {
            $this->handleError($response->status(), $response->json());

            return null;
        }

        return $response->json();
    }

    /**
     * Revoke refresh token.
     */
    public function revokeToken(string $refreshToken): bool
    {
        $response = $this->request()
            ->post("{$this->consoleUrl}/api/sso/revoke", [
                'refresh_token' => $refreshToken,
            ]);

        return $response->successful();
    }

    /**
     * Get user authorization for organization.
     *
     * @return array{organization_id: string, organization_slug: string, organization_role: string, service_role: string|null, service_role_level: int, all_branches_access: bool, branch_count: int, primary_branch: array{id: int, code: string, name: string}|null}|null
     */
    public function getAccess(string $accessToken, string $organizationId): ?array
    {
        $response = $this->request()
            ->withToken($accessToken)
            ->get("{$this->consoleUrl}/api/sso/access", [
                'organization_slug' => $organizationId,
            ]);

        if (! $response->successful()) {
            if ($response->status() === 403) {
                return null;
            }
            $this->handleError($response->status(), $response->json());

            return null;
        }

        return $response->json();
    }

    /**
     * Get organizations user has access to.
     *
     * @return array<array{organization_id: string, organization_slug: string, organization_name: string, organization_role: string, service_role: string|null}>
     */
    public function getOrganizations(string $accessToken): array
    {
        $response = $this->request()
            ->withToken($accessToken)
            ->get("{$this->consoleUrl}/api/sso/organizations");

        if (! $response->successful()) {
            $this->handleError($response->status(), $response->json());

            return [];
        }

        return $response->json() ?? [];
    }

    /**
     * Get user's teams in organization.
     *
     * @return array<array{id: int, name: string, path: string|null, parent_id: int|null, is_leader: bool}>
     */
    public function getUserTeams(string $accessToken, string $organizationId): array
    {
        $response = $this->request()
            ->withToken($accessToken)
            ->get("{$this->consoleUrl}/api/sso/teams", [
                'organization_slug' => $organizationId,
            ]);

        if (! $response->successful()) {
            if ($response->status() === 403 || $response->status() === 404) {
                return [];
            }
            $this->handleError($response->status(), $response->json());

            return [];
        }

        return $response->json('teams') ?? [];
    }

    /**
     * Get locations for a branch (or all branches in an organization).
     *
     * @return array<array{id: string, code: string, name: string, type: string, is_active: bool, address: string|null, city: string|null, state_province: string|null, postal_code: string|null, country_code: string|null, latitude: float|null, longitude: float|null, phone: string|null, email: string|null, timezone: string|null, capacity: int|null, sort_order: int, description: string|null, settings: array|null, metadata: array|null}>
     */
    public function getLocations(string $accessToken, string $organizationId, ?string $branchId = null): array
    {
        $params = ['organization_slug' => $organizationId];

        if ($branchId) {
            $params['branch_id'] = $branchId;
        }

        $response = $this->request()
            ->withToken($accessToken)
            ->get("{$this->consoleUrl}/api/sso/locations", $params);

        if (! $response->successful()) {
            if ($response->status() === 403 || $response->status() === 404) {
                return [];
            }
            $this->handleError($response->status(), $response->json());

            return [];
        }

        return $response->json('locations') ?? [];
    }

    /**
     * Get user's branches in organization.
     *
     * @return array{all_branches_access: bool, branches: array<array{id: int, code: string, name: string, is_headquarters: bool, is_primary: bool, is_assigned: bool, access_type: string, timezone: string|null, currency: string|null, locale: string|null}>, primary_branch_id: int|null, organization: array{id: string, slug: string, name: string}}|null
     */
    public function getUserBranches(string $accessToken, string $organizationId): ?array
    {
        $response = $this->request()
            ->withToken($accessToken)
            ->get("{$this->consoleUrl}/api/sso/branches", [
                'organization_slug' => $organizationId,
            ]);

        if (! $response->successful()) {
            if ($response->status() === 403 || $response->status() === 404) {
                return null;
            }
            $this->handleError($response->status(), $response->json());

            return null;
        }

        return $response->json();
    }

    /**
     * Get JWKS for JWT verification.
     *
     * @return array<string, mixed>
     */
    public function getJwks(): array
    {
        $response = $this->request()
            ->get("{$this->consoleUrl}/.well-known/jwks.json");

        if (! $response->successful()) {
            $this->handleError($response->status(), $response->json());

            return [];
        }

        return $response->json() ?? [];
    }

    // =========================================================================
    // Service-to-Service API (dùng service secret, không cần user token)
    // Yêu cầu Console phải expose các endpoint tương ứng.
    // =========================================================================

    /**
     * Lấy danh sách users của tổ chức từ Console (service-to-service).
     *
     * Console cần expose: GET /api/sso/service/users
     * Auth header: X-Service-Slug + X-Service-Secret
     *
     * Response: { "users": [{ "id": "uuid", "email": "...", "name": "...", "is_active": true }], "total": 42 }
     *
     * @return array<array{id: string, email: string, name: string, is_active: bool}>
     */
    public function getServiceUsers(string $organizationSlug, int $page = 1, int $perPage = 100): array
    {
        $response = $this->serviceRequest()
            ->get("{$this->consoleUrl}/api/sso/service/users", [
                'organization_slug' => $organizationSlug,
                'page' => $page,
                'per_page' => $perPage,
            ]);

        if (! $response->successful()) {
            if ($response->status() === 403 || $response->status() === 404) {
                return [];
            }
            $this->handleError($response->status(), $response->json());

            return [];
        }

        return $response->json('users') ?? [];
    }

    /**
     * Lấy danh sách branches của tổ chức từ Console (service-to-service).
     *
     * Console cần expose: GET /api/sso/service/branches
     * Auth header: X-Service-Slug + X-Service-Secret
     *
     * Response: { "branches": [{ "id": "uuid", "slug": "...", "name": "...", "is_headquarters": true, "is_active": true }] }
     *
     * @return array<array{id: string, slug: string, name: string, is_headquarters: bool, is_active: bool}>
     */
    public function getServiceBranches(string $organizationSlug): array
    {
        $response = $this->serviceRequest()
            ->get("{$this->consoleUrl}/api/sso/service/branches", [
                'organization_slug' => $organizationSlug,
            ]);

        if (! $response->successful()) {
            if ($response->status() === 403 || $response->status() === 404) {
                return [];
            }
            $this->handleError($response->status(), $response->json());

            return [];
        }

        return $response->json('branches') ?? [];
    }

    /**
     * Get branches available for invitation (user-level access via JWT).
     *
     * Console endpoint: GET /api/sso/invite/branches
     * Auth: Bearer {accessToken} + X-Org-Id: {organizationSlug}
     *
     * @return array{organization: array{id: string, slug: string, name: string}, branches: array<array{id: string, code: string, name: string, is_headquarters: bool, timezone: string|null, currency: string|null, locale: string|null}>}|null
     */
    public function getInviteBranches(string $accessToken, string $organizationSlug): ?array
    {
        $response = $this->request()
            ->withToken($accessToken)
            ->withHeaders(['X-Org-Id' => $organizationSlug])
            ->get("{$this->consoleUrl}/api/sso/invite/branches");

        if (! $response->successful()) {
            if ($response->status() === 403 || $response->status() === 404) {
                return null;
            }
            $this->handleError($response->status(), $response->json());

            return null;
        }

        return $response->json();
    }

    /**
     * Invite members to an organization via the Console.
     *
     * Console endpoint: POST /api/sso/invite
     * Auth: Bearer {accessToken} + X-Org-Id: {organizationSlug}
     *
     * @param  string[]  $emails
     * @return array{sent: int, skipped: int, errors: list<string>}
     */
    public function inviteMembers(string $accessToken, string $organizationSlug, string $branchId, array $emails, string $role): array
    {
        $response = $this->request()
            ->withToken($accessToken)
            ->withHeaders(['X-Org-Id' => $organizationSlug])
            ->post("{$this->consoleUrl}/api/sso/invite", [
                'branch_id' => $branchId,
                'emails' => $emails,
                'role' => $role,
            ]);

        if (! $response->successful()) {
            $this->handleError($response->status(), $response->json());

            return ['sent' => 0, 'skipped' => 0, 'errors' => []];
        }

        return $response->json() ?? ['sent' => 0, 'skipped' => 0, 'errors' => []];
    }

    /**
     * HTTP request với service credentials (X-Service-Slug + X-Service-Secret).
     */
    private function serviceRequest(): PendingRequest
    {
        return $this->request()->withHeaders([
            'X-Service-Slug' => $this->serviceSlug,
            'X-Service-Secret' => config('omnify-auth.service.secret', ''),
        ]);
    }

    /**
     * Get Console URL.
     */
    public function getConsoleUrl(): string
    {
        return $this->consoleUrl;
    }

    /**
     * Get service slug.
     */
    public function getServiceSlug(): string
    {
        return $this->serviceSlug;
    }

    /**
     * Create HTTP request with common configuration.
     */
    private function request(): PendingRequest
    {
        $request = Http::timeout($this->timeout)
            ->retry($this->retry, 100, throw: false)
            ->acceptJson();

        // Add Accept-Language header if enabled
        if (config('omnify-auth.locale.enabled', true)) {
            $request->withHeaders([
                config('omnify-auth.locale.header', 'Accept-Language') => app()->getLocale(),
            ]);
        }

        return $request;
    }

    /**
     * Handle API error responses.
     *
     * @param  array<string, mixed>|null  $body
     *
     * @throws ConsoleApiException
     */
    private function handleError(int $status, ?array $body): void
    {
        $error = $body['error'] ?? 'UNKNOWN_ERROR';
        $message = $body['message'] ?? 'An error occurred';

        match ($status) {
            400 => throw new ConsoleApiException($message, $status, $error),
            401 => throw new ConsoleAuthException($message),
            403 => throw new ConsoleAccessDeniedException($message),
            404 => throw new ConsoleNotFoundException($message),
            default => $status >= 500
                ? throw new ConsoleServerException($message, $status)
                : throw new ConsoleApiException($message, $status, $error),
        };
    }
}
