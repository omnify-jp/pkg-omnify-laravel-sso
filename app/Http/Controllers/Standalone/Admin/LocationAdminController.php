<?php

namespace Omnify\Core\Http\Controllers\Standalone\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Omnify\Core\Http\Requests\Admin\LocationStoreRequest;
use Omnify\Core\Http\Requests\Admin\LocationUpdateRequest;
use Omnify\Core\Models\Branch;
use Omnify\Core\Models\Location;
use Omnify\Core\Models\Organization;

class LocationAdminController
{
    public function index(Request $request): Response
    {
        $pagesPath = config('omnify-auth.routes.standalone_admin_pages_path', 'admin');

        $locations = Location::query()
            ->currentMode()
            ->when(
                $request->input('search'),
                fn ($q, $search) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
            )
            ->when(
                $request->input('branch_id'),
                fn ($q, $branchId) => $q->where('console_branch_id', $branchId)
            )
            ->when(
                $request->input('organization_id'),
                fn ($q, $orgId) => $q->where('console_organization_id', $orgId)
            )
            ->when(
                $request->input('type'),
                fn ($q, $type) => $q->where('type', $type)
            )
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $branches = Branch::where('is_active', true)->currentMode()
            ->orderBy('name')
            ->get(['id', 'console_branch_id', 'console_organization_id', 'name', 'slug']);

        $organizations = Organization::where('is_active', true)->currentMode()
            ->orderBy('name')
            ->get(['id', 'console_organization_id', 'name', 'slug']);

        return Inertia::render("{$pagesPath}/locations/index", [
            'locations' => [
                'data' => $locations->items(),
                'meta' => [
                    'current_page' => $locations->currentPage(),
                    'last_page' => $locations->lastPage(),
                    'per_page' => $locations->perPage(),
                    'total' => $locations->total(),
                ],
                'links' => [
                    'first' => $locations->url(1),
                    'last' => $locations->url($locations->lastPage()),
                    'prev' => $locations->previousPageUrl(),
                    'next' => $locations->nextPageUrl(),
                ],
            ],
            'branches' => $branches,
            'organizations' => $organizations,
            'filters' => $request->only('search', 'branch_id', 'organization_id', 'type'),
        ]);
    }

    public function create(): Response
    {
        $pagesPath = config('omnify-auth.routes.standalone_admin_pages_path', 'admin');

        $branches = Branch::where('is_active', true)->currentMode()
            ->orderBy('name')
            ->get(['id', 'console_branch_id', 'console_organization_id', 'name', 'slug']);

        $organizations = Organization::where('is_active', true)->currentMode()
            ->orderBy('name')
            ->get(['id', 'console_organization_id', 'name', 'slug']);

        return Inertia::render("{$pagesPath}/locations/create", [
            'branches' => $branches,
            'organizations' => $organizations,
        ]);
    }

    public function store(LocationStoreRequest $request): RedirectResponse
    {
        $branch = Branch::findOrFail($request->branch_id);

        Location::create([
            'name' => $request->name,
            'code' => $request->code,
            'type' => $request->type,
            'is_active' => $request->boolean('is_active', true),
            'is_standalone' => true,
            'console_location_id' => Str::uuid()->toString(),
            'console_branch_id' => $branch->console_branch_id,
            'console_organization_id' => $branch->console_organization_id,
            'address' => $request->address,
            'city' => $request->city,
            'state_province' => $request->state_province,
            'postal_code' => $request->postal_code,
            'country_code' => $request->country_code,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'phone' => $request->phone,
            'email' => $request->email,
            'timezone' => $request->timezone,
            'capacity' => $request->capacity,
            'sort_order' => $request->integer('sort_order', 0),
            'description' => $request->description,
        ]);

        return redirect()->route('admin.locations.index')
            ->with('success', __('Location created.'));
    }

    public function edit(Location $location): Response
    {
        $pagesPath = config('omnify-auth.routes.standalone_admin_pages_path', 'admin');

        $branches = Branch::where('is_active', true)->currentMode()
            ->orderBy('name')
            ->get(['id', 'console_branch_id', 'console_organization_id', 'name', 'slug']);

        $organizations = Organization::where('is_active', true)->currentMode()
            ->orderBy('name')
            ->get(['id', 'console_organization_id', 'name', 'slug']);

        return Inertia::render("{$pagesPath}/locations/edit", [
            'location' => $location,
            'branches' => $branches,
            'organizations' => $organizations,
        ]);
    }

    public function update(LocationUpdateRequest $request, Location $location): RedirectResponse
    {
        $data = $request->validated();

        if (isset($data['branch_id'])) {
            $branch = Branch::findOrFail($data['branch_id']);
            $data['console_branch_id'] = $branch->console_branch_id;
            $data['console_organization_id'] = $branch->console_organization_id;
            unset($data['branch_id']);
        }

        $location->update($data);

        return redirect()->route('admin.locations.index', [], 303)
            ->with('success', __('Location updated.'));
    }

    public function destroy(Location $location): RedirectResponse
    {
        $location->delete();

        return redirect()->route('admin.locations.index', [], 303)
            ->with('success', __('Location deleted.'));
    }
}
