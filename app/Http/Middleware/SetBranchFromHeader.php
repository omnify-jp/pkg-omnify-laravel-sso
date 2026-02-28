<?php

declare(strict_types=1);

namespace Omnify\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Omnify\Core\Models\Branch;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that sets branch context from X-Branch-Id header.
 *
 * This is a simpler alternative to SsoOrganizationAccess that:
 * - Does not require organization context
 * - Auto-creates branch record if it doesn't exist (lazy sync from SSO Console)
 * - Useful for services where branch selection is handled by frontend
 *
 * Headers read:
 * - X-Branch-Id (required): Console branch UUID
 * - X-Organization-Id (optional): Console organization UUID (for auto-creation)
 *
 * Sets request attributes:
 * - branch: Branch model instance
 * - branchId: Console branch UUID
 *
 * @example
 * // In bootstrap/app.php
 * $middleware->append(\Omnify\Core\Http\Middleware\SetBranchFromHeader::class);
 *
 * // In controller
 * $branch = $request->attributes->get('branch');
 * if ($branch) {
 *     $project = Project::create([
 *         'console_organization_id' => $branch->console_organization_id,
 *         'console_branch_id' => $branch->console_branch_id,
 *         // ...
 *     ]);
 * }
 */
class SetBranchFromHeader
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip if branch is already set (by SsoOrganizationAccess middleware)
        if ($request->attributes->has('branch')) {
            return $next($request);
        }

        // Get branch ID from header (this is console_branch_id from SSO Console)
        $consoleBranchId = $request->header('X-Branch-Id');

        if ($consoleBranchId) {
            // Look up by console_branch_id (UUID from SSO), not by local id
            $branch = Branch::where('console_branch_id', $consoleBranchId)->first();

            // Auto-create branch if it doesn't exist (lazy sync)
            if (! $branch) {
                $consoleOrgId = $request->header('X-Organization-Id');

                if ($consoleOrgId) {
                    $branch = Branch::create([
                        'console_branch_id' => $consoleBranchId,
                        'console_organization_id' => $consoleOrgId,
                        'slug' => 'DEFAULT',
                        'name' => 'Default Branch',
                        'is_headquarters' => true,
                        'is_active' => true,
                    ]);
                }
            }

            if ($branch) {
                $request->attributes->set('branch', $branch);
                $request->attributes->set('branchId', $consoleBranchId);
            }
        }

        return $next($request);
    }
}
