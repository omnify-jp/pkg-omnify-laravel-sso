<?php

namespace Omnify\Core\Http\Controllers\Standalone\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Omnify\Core\Http\Requests\Admin\BrandStoreRequest;
use Omnify\Core\Http\Requests\Admin\BrandUpdateRequest;
use Omnify\Core\Models\Brand;
use Omnify\Core\Models\Organization;

class BrandAdminController
{
    public function index(Request $request): Response
    {
        $pagesPath = config('omnify-auth.routes.standalone_admin_pages_path', 'admin');

        $brands = Brand::query()
            ->currentMode()
            ->when(
                $request->input('search'),
                fn ($q, $search) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
            )
            ->when(
                $request->input('organization_id'),
                fn ($q, $orgId) => $q->where('console_organization_id', $orgId)
            )
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $organizations = Organization::where('is_active', true)->currentMode()
            ->orderBy('name')
            ->get(['id', 'console_organization_id', 'name', 'slug']);

        return Inertia::render("{$pagesPath}/brands/index", [
            'brands' => [
                'data' => $brands->items(),
                'meta' => [
                    'current_page' => $brands->currentPage(),
                    'last_page' => $brands->lastPage(),
                    'per_page' => $brands->perPage(),
                    'total' => $brands->total(),
                ],
                'links' => [
                    'first' => $brands->url(1),
                    'last' => $brands->url($brands->lastPage()),
                    'prev' => $brands->previousPageUrl(),
                    'next' => $brands->nextPageUrl(),
                ],
            ],
            'organizations' => $organizations,
            'filters' => $request->only('search', 'organization_id'),
        ]);
    }

    public function create(): Response
    {
        $pagesPath = config('omnify-auth.routes.standalone_admin_pages_path', 'admin');

        $organizations = Organization::where('is_active', true)->currentMode()
            ->orderBy('name')
            ->get(['id', 'console_organization_id', 'name', 'slug']);

        return Inertia::render("{$pagesPath}/brands/create", [
            'organizations' => $organizations,
        ]);
    }

    public function store(BrandStoreRequest $request): RedirectResponse
    {
        $org = Organization::findOrFail($request->organization_id);

        Brand::create([
            'name' => $request->name,
            'slug' => $request->slug,
            'description' => $request->description,
            'logo_url' => $request->logo_url,
            'cover_image_url' => $request->cover_image_url,
            'website' => $request->website,
            'is_active' => $request->boolean('is_active', true),
            'is_standalone' => true,
            'console_brand_id' => Str::uuid()->toString(),
            'console_organization_id' => $org->console_organization_id,
        ]);

        return redirect()->route('admin.brands.index')
            ->with('success', __('Brand created.'));
    }

    public function edit(Brand $brand): Response
    {
        $pagesPath = config('omnify-auth.routes.standalone_admin_pages_path', 'admin');

        $organizations = Organization::where('is_active', true)->currentMode()
            ->orderBy('name')
            ->get(['id', 'console_organization_id', 'name', 'slug']);

        return Inertia::render("{$pagesPath}/brands/edit", [
            'brand' => $brand,
            'organizations' => $organizations,
        ]);
    }

    public function update(BrandUpdateRequest $request, Brand $brand): RedirectResponse
    {
        $data = $request->validated();

        if (isset($data['organization_id'])) {
            $org = Organization::findOrFail($data['organization_id']);
            $data['console_organization_id'] = $org->console_organization_id;
            unset($data['organization_id']);
        }

        $brand->update($data);

        return redirect()->route('admin.brands.index', [], 303)
            ->with('success', __('Brand updated.'));
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        $brand->delete();

        return redirect()->route('admin.brands.index', [], 303)
            ->with('success', __('Brand deleted.'));
    }
}
