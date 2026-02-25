<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Omnify\SsoClient\Models\Branch;
use Omnify\SsoClient\Models\Organization;
use Omnify\SsoClient\Services\ConsoleApiService;
use Omnify\SsoClient\Services\ConsoleTokenService;

/**
 * Controller for Invite (member invitation via Console API) pages.
 */
class InvitePageController extends Controller
{
    public function __construct(
        private readonly ConsoleApiService $consoleApi,
        private readonly ConsoleTokenService $tokenService
    ) {}

    /**
     * Get the base path for IAM pages.
     */
    protected function getPagePath(string $page): string
    {
        $basePath = config('omnify-auth.routes.access_pages_path', 'admin/iam');

        return "{$basePath}/{$page}";
    }

    /**
     * Invite create form page.
     *
     * Gets branches from Console API (with local fallback).
     */
    public function inviteCreate(Request $request): Response
    {
        $user = $request->user();
        $accessToken = $user ? $this->tokenService->getAccessToken($user) : null;

        // Resolve organization slug from header, query param, or user's current org
        $orgSlug = $request->header('X-Organization-Id')
            ?? $request->query('org')
            ?? ($user?->sso_current_org_id);

        $branches = [];
        $organization = null;

        if ($accessToken && $orgSlug) {
            try {
                $result = $this->consoleApi->getInviteBranches($accessToken, (string) $orgSlug);

                if ($result) {
                    $branches = $result['branches'] ?? [];
                    $organization = $result['organization'] ?? null;
                }
            } catch (\Throwable) {
                // Fallback to local branch cache
            }
        }

        // Fallback: use local branch cache if Console API returned nothing
        if (empty($branches)) {
            $query = Branch::query()->where('is_active', true);

            if ($orgSlug) {
                $org = Organization::query()
                    ->where('id', $orgSlug)
                    ->orWhere('slug', $orgSlug)
                    ->first();

                if ($org) {
                    $query->where('console_organization_id', $org->console_organization_id);
                    $organization ??= [
                        'id' => $org->console_organization_id,
                        'slug' => $org->slug,
                        'name' => $org->name,
                    ];
                }
            }

            $branches = $query->orderBy('name')->get()->map(fn ($b) => [
                'id' => $b->console_branch_id ?? $b->id,
                'code' => $b->slug,
                'name' => $b->name,
                'is_headquarters' => (bool) $b->is_headquarters,
                'timezone' => null,
                'currency' => null,
                'locale' => null,
            ])->values()->all();
        }

        return Inertia::render($this->getPagePath('invite-create'), [
            'branches' => $branches,
            'invite_org' => $organization,
            'org_slug' => $orgSlug,
            'available_roles' => ['owner', 'admin', 'member'],
        ]);
    }

    /**
     * Send invitations via Console API.
     */
    public function inviteStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'org_slug' => ['required', 'string', 'max:255'],
            'branch_id' => ['required', 'string', 'max:255'],
            'emails_raw' => ['required', 'string'],
            'role' => ['required', 'in:owner,admin,member'],
        ]);

        // Parse emails from textarea (newlines or commas)
        $emails = array_values(array_filter(
            array_map(
                fn ($e) => strtolower(trim($e)),
                preg_split('/[\s,;]+/', $data['emails_raw']) ?: [],
            ),
            fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL) !== false,
        ));

        if (empty($emails)) {
            return back()->withErrors(['emails_raw' => 'Please enter at least one valid email address.']);
        }

        if (count($emails) > 50) {
            return back()->withErrors(['emails_raw' => 'You may invite at most 50 people at once.']);
        }

        $user = $request->user();
        $accessToken = $user ? $this->tokenService->getAccessToken($user) : null;

        if (! $accessToken) {
            return back()->withErrors(['session' => 'Session expired. Please log in again.']);
        }

        try {
            $result = $this->consoleApi->inviteMembers(
                $accessToken,
                $data['org_slug'],
                $data['branch_id'],
                $emails,
                $data['role'],
            );

            $sent = $result['sent'] ?? 0;
            $skipped = $result['skipped'] ?? 0;

            return redirect()
                ->route('access.invite.create', ['org' => $data['org_slug']])
                ->with('success', "Sent {$sent} invitation(s). Skipped {$skipped} (already members).");
        } catch (\Throwable $e) {
            return back()->withErrors(['invite' => $e->getMessage()]);
        }
    }
}
