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
use Omnify\Core\Models\Organization;

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
                fn ($q, $search) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
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
