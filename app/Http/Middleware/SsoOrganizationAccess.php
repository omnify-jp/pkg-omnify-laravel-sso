<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Omnify\SsoClient\Models\Branch;
use Omnify\SsoClient\Models\Organization;
use Omnify\SsoClient\Services\OrganizationAccessService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware for organization and branch context.
 *
 * Sets organization and branch context from headers:
 * - X-Organization-Id (required): Organization slug
 * - X-Branch-Id (optional): Branch UUID for branch-specific operations
 *
 * Branch context enables branch-level permissions (Option B - Scoped Role Assignments).
 *
 * @see https://workos.com/blog/how-to-design-multi-tenant-rbac-saas Multi-Tenant RBAC
 */
class SsoOrganizationAccess
{
    public function __construct(
        private readonly OrganizationAccessService $organizationAccessService
    ) {}

    /**
     * Handle an incoming request.
     *
     * Sets request attributes and session for org/branch context:
     * - organizationId, organizationRole, serviceRole, serviceRoleLevel (from Console)
     * - branchId (from X-Branch-Id header, validated against organization)
     *
     * @param  \Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get organization from header
        $organizationIdFromHeader = $request->header('X-Organization-Id');

        if (! $organizationIdFromHeader) {
            return response()->json([
                'error' => 'MISSING_ORGANIZATION',
                'message' => 'X-Organization-Id header is required',
            ], 400);
        }

        $user = $request->user();

        if (! $user) {
            return response()->json([
                'error' => 'UNAUTHENTICATED',
                'message' => 'Authentication required',
            ], 401);
        }

        // Check organization access
        $access = $this->organizationAccessService->checkAccess($user, $organizationIdFromHeader);

        if (! $access) {
            return response()->json([
                'error' => 'ACCESS_DENIED',
                'message' => 'No access to this organization',
            ], 403);
        }

        $organizationId = $access['organization_id'];

        // Auto-cache organization to database (withTrashed to handle soft-deleted records)
        Organization::withTrashed()->updateOrCreate(
            ['console_organization_id' => $organizationId],
            [
                'name' => $access['organization_name'] ?? $access['organization_slug'],
                'slug' => $access['organization_slug'],
                'is_active' => true,
                'deleted_at' => null,
            ]
        );

        // Set organization info on request attributes
        $request->attributes->set('organizationId', $organizationId);
        $request->attributes->set('organizationRole', $access['organization_role']);
        $request->attributes->set('serviceRole', $access['service_role']);
        $request->attributes->set('serviceRoleLevel', $access['service_role_level']);

        // Store in session for later use
        session([
            'current_organization_id' => $organizationId,
            'service_role' => $access['service_role'],
        ]);

        // =====================================================================
        // BRANCH CONTEXT (Branch-Level Permissions - Option B)
        // =====================================================================
        $branchId = $request->header('X-Branch-Id');
        $branch = null;

        if ($branchId) {
            // Validate branch ID format (should be UUID)
            if (! Str::isUuid($branchId)) {
                return response()->json([
                    'error' => 'INVALID_BRANCH_ID',
                    'message' => 'X-Branch-Id must be a valid UUID',
                ], 400);
            }

            // Validate branch belongs to this organization
            $branch = Branch::where('console_branch_id', $branchId)
                ->where('console_organization_id', $organizationId)
                ->first();

            if (! $branch) {
                return response()->json([
                    'error' => 'INVALID_BRANCH',
                    'message' => 'Branch not found or does not belong to this organization',
                ], 400);
            }
        } elseif (config('omnify-auth.branch.fallback_to_hq', false)) {
            // HQ Fallback: Auto-select headquarters branch when no X-Branch-Id header
            $branch = Branch::where('console_organization_id', $organizationId)
                ->where('is_headquarters', true)
                ->where('is_active', true)
                ->first();

            if ($branch) {
                $branchId = $branch->console_branch_id;
            }
        }

        if ($branch) {
            // Set branch context
            $request->attributes->set('branchId', $branchId);
            $request->attributes->set('branch', $branch);

            session([
                'current_branch_id' => $branchId,
                'current_branch_code' => $branch->slug,
                'current_branch_name' => $branch->name,
            ]);
        } else {
            // Clear branch context (org-wide operations)
            $request->attributes->set('branchId', null);
            $request->attributes->set('branch', null);

            session([
                'current_branch_id' => null,
                'current_branch_code' => null,
                'current_branch_name' => null,
            ]);
        }

        // Also set as request properties for convenience
        $request->merge([
            '_organization_id' => $organizationId,
            '_branch_id' => $branchId,
        ]);

        return $next($request);
    }
}
