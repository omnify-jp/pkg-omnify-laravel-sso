<?php

declare(strict_types=1);

namespace Omnify\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Omnify\Core\Facades\Context;
use Omnify\Core\Models\Branch;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that sets branch context from header or route parameter.
 *
 * This middleware does NOT require branch - it just sets the context if available.
 * Use sso.require-branch if branch is mandatory.
 *
 * Priority:
 * 1. X-Branch-Id header (already set by SsoOrganizationAccess)
 * 2. Route parameter `branch` or `branch_id`
 * 3. Query parameter `branch_id`
 *
 * Usage in routes:
 *   Route::get('/devices', ...)->middleware('core.with-branch');
 *   Route::get('/branches/{branch}/devices', ...)->middleware('core.with-branch');
 *
 * @see \Omnify\Core\Http\Middleware\SsoOrganizationAccess
 */
class WithBranch
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If branch already set (from header), continue
        if (Context::hasBranch()) {
            return $next($request);
        }

        // Try to get branch from route parameter
        $branchId = $request->route('branch')
            ?? $request->route('branch_id')
            ?? $request->query('branch_id');

        if ($branchId && Context::hasOrganization()) {
            // Validate branch belongs to current organization
            $branch = Branch::where('id', $branchId)
                ->orWhere('console_branch_id', $branchId)
                ->first();

            if ($branch && $branch->console_organization_id === Context::organizationId()) {
                $request->attributes->set('branchId', $branch->console_branch_id ?? $branch->id);
                $request->attributes->set('branch', $branch);

                session([
                    'current_branch_id' => $branch->console_branch_id ?? $branch->id,
                    'current_branch_code' => $branch->slug,
                    'current_branch_name' => $branch->name,
                ]);
            }
        }

        return $next($request);
    }
}
