<?php

namespace Omnify\SsoClient\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Omnify\SsoClient\Http\Requests\Admin\OrganizationStoreRequest;
use Omnify\SsoClient\Http\Requests\Admin\OrganizationUpdateRequest;
use Omnify\SsoClient\Models\Organization;

class OrganizationAdminController
{
    public function index(Request $request): Response
    {
        $pagesPath = config('omnify-auth.routes.standalone_admin_pages_path', 'admin');

        $organizations = Organization::query()
            ->when(
                $request->input('search'),
                fn ($q, $search) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
            )
            ->orderBy('name')
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
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        $pagesPath = config('omnify-auth.routes.standalone_admin_pages_path', 'admin');

        return Inertia::render("{$pagesPath}/organizations/create");
    }

    public function store(OrganizationStoreRequest $request): RedirectResponse
    {
        Organization::create([
            'name' => $request->name,
            'slug' => $request->slug,
            'is_active' => $request->boolean('is_active', true),
            'console_organization_id' => Str::uuid()->toString(),
        ]);

        return redirect()->route('admin.organizations.index')
            ->with('success', __('Organization created.'));
    }

    public function edit(Organization $organization): Response
    {
        $pagesPath = config('omnify-auth.routes.standalone_admin_pages_path', 'admin');

        return Inertia::render("{$pagesPath}/organizations/edit", [
            'organization' => $organization,
        ]);
    }

    public function update(OrganizationUpdateRequest $request, Organization $organization): RedirectResponse
    {
        $organization->update($request->validated());

        return redirect()->route('admin.organizations.index')
            ->with('success', __('Organization updated.'));
    }

    public function destroy(Organization $organization): RedirectResponse
    {
        $organization->delete();

        return redirect()->route('admin.organizations.index')
            ->with('success', __('Organization deleted.'));
    }
}
