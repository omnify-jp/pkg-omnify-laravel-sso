<?php

namespace Omnify\Core\Http\Controllers\Standalone\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Omnify\Core\Http\Requests\Admin\OrganizationStoreRequest;
use Omnify\Core\Http\Requests\Admin\OrganizationUpdateRequest;
use Omnify\Core\Models\Branch;
use Omnify\Core\Models\Location;
use Omnify\Core\Models\Organization;
use Omnify\Core\Models\Role;
use Omnify\Core\Models\User;

class OrganizationAdminController
{
    public function index(Request $request): Response
    {
        $pagesPath = config('omnify-auth.routes.standalone_admin_pages_path', 'admin');

        $sortField = $request->input('sort', 'name');
        $sortDirection = 'asc';

        if (str_starts_with($sortField, '-')) {
            $sortField = substr($sortField, 1);
            $sortDirection = 'desc';
        }

        $allowedSorts = ['name', 'slug', 'is_active', 'created_at'];
        if (! in_array($sortField, $allowedSorts)) {
            $sortField = 'name';
            $sortDirection = 'asc';
        }

        $organizations = Organization::query()
            ->currentMode()
            ->when(
                $request->input('q'),
                fn ($q, $search) => $q->where(fn ($sub) => $sub->where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%"))
            )
            ->when(
                $request->input('filter.is_active') !== null,
                fn ($q) => $q->where('is_active', $request->boolean('filter.is_active'))
            )
            ->when(
                $request->input('filter.created_at_from'),
                fn ($q, $from) => $q->whereDate('created_at', '>=', $from)
            )
            ->when(
                $request->input('filter.created_at_to'),
                fn ($q, $to) => $q->whereDate('created_at', '<=', $to)
            )
            ->orderBy($sortField, $sortDirection)
            ->paginate(15)
            ->withQueryString();

        return Inertia::render("{$pagesPath}/organizations/index", [
            'organizations' => [
                'data' => $organizations->items(),
                'meta' => [
                    'current_page' => $organizations->currentPage(),
                    'last_page' => $organizations->lastPage(),
                    'per_page' => $organizations->perPage(),
                    'total' => $organizations->total(),
                ],
                'links' => [
                    'first' => $organizations->url(1),
                    'last' => $organizations->url($organizations->lastPage()),
                    'prev' => $organizations->previousPageUrl(),
                    'next' => $organizations->nextPageUrl(),
                ],
            ],
            'filters' => [
                'q' => $request->input('q'),
                'sort' => $request->input('sort'),
                'filter' => $request->input('filter'),
            ],
        ]);
    }

    public function show(Request $request, Organization $organization): Response
    {
        $pagesPath = config('omnify-auth.routes.standalone_admin_pages_path', 'admin');

        $tab = $request->input('tab', 'general');

        // Branches — search + sort
        $branchSortField = $request->input('branches_sort', 'name');
        $branchSortDirection = 'asc';
        if (str_starts_with($branchSortField, '-')) {
            $branchSortField = substr($branchSortField, 1);
            $branchSortDirection = 'desc';
        }
        $allowedBranchSorts = ['name', 'slug', 'is_headquarters', 'is_active', 'created_at'];
        if (! in_array($branchSortField, $allowedBranchSorts)) {
            $branchSortField = 'name';
            $branchSortDirection = 'asc';
        }

        $branches = Branch::query()
            ->where('console_organization_id', $organization->console_organization_id)
            ->when(
                $request->input('branches_q'),
                fn ($q, $search) => $q->where(fn ($sub) => $sub->where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%"))
            )
            ->orderBy($branchSortField, $branchSortDirection)
            ->paginate(15, ['*'], 'branches_page')
            ->withQueryString();

        // Locations — search + sort + filter by branch
        $locationSortField = $request->input('locations_sort', 'name');
        $locationSortDirection = 'asc';
        if (str_starts_with($locationSortField, '-')) {
            $locationSortField = substr($locationSortField, 1);
            $locationSortDirection = 'desc';
        }
        $allowedLocationSorts = ['name', 'code', 'type', 'is_active', 'created_at'];
        if (! in_array($locationSortField, $allowedLocationSorts)) {
            $locationSortField = 'name';
            $locationSortDirection = 'asc';
        }

        $locations = Location::query()
            ->with('branch:id,name')
            ->where('console_organization_id', $organization->console_organization_id)
            ->when(
                $request->input('locations_q'),
                fn ($q, $search) => $q->where(fn ($sub) => $sub->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
            )
            ->when(
                $request->input('branch_id'),
                fn ($q, $branchId) => $q->where('console_branch_id', $branchId)
            )
            ->orderBy($locationSortField, $locationSortDirection)
            ->paginate(15, ['*'], 'locations_page')
            ->withQueryString();

        // Users — users with any role scoped to this organization
        $userSortField = $request->input('users_sort', 'name');
        $userSortDirection = 'asc';
        if (str_starts_with($userSortField, '-')) {
            $userSortField = substr($userSortField, 1);
            $userSortDirection = 'desc';
        }
        $allowedUserSorts = ['name', 'email', 'is_active', 'created_at'];
        if (! in_array($userSortField, $allowedUserSorts)) {
            $userSortField = 'name';
            $userSortDirection = 'asc';
        }

        $users = User::query()
            ->whereHas('roles', fn ($q) => $q->where('role_user_pivot.console_organization_id', $organization->console_organization_id))
            ->with(['roles' => fn ($q) => $q->where('role_user_pivot.console_organization_id', $organization->console_organization_id)])
            ->when(
                $request->input('users_q'),
                fn ($q, $search) => $q->where(fn ($sub) => $sub->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
            )
            ->orderBy($userSortField, $userSortDirection)
            ->paginate(15, ['*'], 'users_page')
            ->withQueryString();

        $paginationMeta = fn ($paginator) => [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];

        return Inertia::render("{$pagesPath}/organizations/show", [
            'organization' => $organization,
            'branches' => [
                'data' => $branches->items(),
                'meta' => $paginationMeta($branches),
            ],
            'locations' => [
                'data' => $locations->items(),
                'meta' => $paginationMeta($locations),
            ],
            'users' => [
                'data' => collect($users->items())->map(function ($user) use ($organization) {
                    static $orgBranches = null;
                    $orgBranches ??= Branch::where('console_organization_id', $organization->console_organization_id)
                        ->pluck('name', 'console_branch_id');

                    $role = $user->roles->first();
                    $consoleBranchId = $role?->pivot->console_branch_id;

                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'is_active' => $user->is_active,
                        'role_id' => $role?->id,
                        'role_name' => $role?->name,
                        'role_slug' => $role?->slug,
                        'console_branch_id' => $consoleBranchId,
                        'branch_name' => $consoleBranchId ? ($orgBranches[$consoleBranchId] ?? null) : null,
                        'scope_type' => $consoleBranchId ? 'branch' : 'org-wide',
                    ];
                }),
                'meta' => $paginationMeta($users),
            ],
            'roles' => Role::orderBy('level')->get(['id', 'name', 'slug', 'level']),
            'tab' => $tab,
            'filters' => [
                'branches_q' => $request->input('branches_q'),
                'branches_sort' => $request->input('branches_sort'),
                'locations_q' => $request->input('locations_q'),
                'locations_sort' => $request->input('locations_sort'),
                'users_q' => $request->input('users_q'),
                'users_sort' => $request->input('users_sort'),
                'branch_id' => $request->input('branch_id'),
            ],
        ]);
    }

    public function store(OrganizationStoreRequest $request): JsonResponse|RedirectResponse
    {
        Organization::create([
            'name' => $request->name,
            'slug' => $request->slug,
            'is_active' => $request->boolean('is_active', true),
            'is_standalone' => true,
            'console_organization_id' => Str::uuid()->toString(),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => __('Organization created.')], 201);
        }

        return redirect()->route('admin.organizations.index')
            ->with('success', __('Organization created.'));
    }

    public function update(OrganizationUpdateRequest $request, Organization $organization): JsonResponse|RedirectResponse
    {
        $organization->update($request->validated());

        if ($request->expectsJson()) {
            return response()->json(['message' => __('Organization updated.')]);
        }

        return redirect()->route('admin.organizations.index', [], 303)
            ->with('success', __('Organization updated.'));
    }

    public function destroy(Organization $organization): RedirectResponse
    {
        $organization->delete();

        return redirect()->route('admin.organizations.index', [], 303)
            ->with('success', __('Organization deleted.'));
    }
}
