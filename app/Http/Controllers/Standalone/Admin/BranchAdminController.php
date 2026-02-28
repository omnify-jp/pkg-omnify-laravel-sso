<?php

namespace Omnify\Core\Http\Controllers\Standalone\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Omnify\Core\Http\Requests\Admin\BranchStoreRequest;
use Omnify\Core\Http\Requests\Admin\BranchUpdateRequest;
use Omnify\Core\Models\Branch;
use Omnify\Core\Models\Organization;

class BranchAdminController
{
    public function index(Request $request): Response
    {
        $pagesPath = config('omnify-auth.routes.standalone_admin_pages_path', 'admin');

        $branches = Branch::query()
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

        return Inertia::render("{$pagesPath}/branches/index", [
            'branches' => [
                'data' => $branches->items(),
                'meta' => [
                    'current_page' => $branches->currentPage(),
                    'last_page' => $branches->lastPage(),
                    'per_page' => $branches->perPage(),
                    'total' => $branches->total(),
                ],
                'links' => [
                    'first' => $branches->url(1),
                    'last' => $branches->url($branches->lastPage()),
                    'prev' => $branches->previousPageUrl(),
                    'next' => $branches->nextPageUrl(),
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

        return Inertia::render("{$pagesPath}/branches/create", [
            'organizations' => $organizations,
        ]);
    }

    public function store(BranchStoreRequest $request): RedirectResponse
    {
        $org = Organization::findOrFail($request->organization_id);

        Branch::create([
            'name' => $request->name,
            'slug' => $request->slug,
            'is_active' => $request->boolean('is_active', true),
            'is_headquarters' => $request->boolean('is_headquarters', false),
            'is_standalone' => true,
            'console_organization_id' => $org->console_organization_id,
            'console_branch_id' => Str::uuid()->toString(),
        ]);

        return redirect()->route('admin.branches.index')
            ->with('success', __('Branch created.'));
    }

    public function edit(Branch $branch): Response
    {
        $pagesPath = config('omnify-auth.routes.standalone_admin_pages_path', 'admin');

        $organizations = Organization::where('is_active', true)->currentMode()
            ->orderBy('name')
            ->get(['id', 'console_organization_id', 'name', 'slug']);

        return Inertia::render("{$pagesPath}/branches/edit", [
            'branch' => $branch,
            'organizations' => $organizations,
        ]);
    }

    public function update(BranchUpdateRequest $request, Branch $branch): RedirectResponse
    {
        $data = $request->validated();

        if (isset($data['organization_id'])) {
            $org = Organization::findOrFail($data['organization_id']);
            $data['console_organization_id'] = $org->console_organization_id;
            unset($data['organization_id']);
        }

        $branch->update($data);

        return redirect()->route('admin.branches.index', [], 303)
            ->with('success', __('Branch updated.'));
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $branch->delete();

        return redirect()->route('admin.branches.index', [], 303)
            ->with('success', __('Branch deleted.'));
    }
}
