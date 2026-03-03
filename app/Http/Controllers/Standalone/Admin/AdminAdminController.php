<?php

namespace Omnify\Core\Http\Controllers\Standalone\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Omnify\Core\Http\Requests\Admin\AdminAdminStoreRequest;
use Omnify\Core\Http\Requests\Admin\AdminAdminUpdateRequest;
use Omnify\Core\Models\Admin;

class AdminAdminController
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

        $allowedSorts = ['name', 'email', 'created_at'];
        if (! in_array($sortField, $allowedSorts)) {
            $sortField = 'name';
            $sortDirection = 'asc';
        }

        $admins = Admin::query()
            ->when(
                $request->input('q'),
                fn ($q, $search) => $q->where(fn ($sub) => $sub->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
            )
            ->orderBy($sortField, $sortDirection)
            ->paginate(15)
            ->withQueryString();

        return Inertia::render("{$pagesPath}/admins/index", [
            'admins' => [
                'data' => $admins->items(),
                'meta' => [
                    'current_page' => $admins->currentPage(),
                    'last_page' => $admins->lastPage(),
                    'per_page' => $admins->perPage(),
                    'total' => $admins->total(),
                ],
            ],
            'filters' => [
                'q' => $request->input('q'),
                'sort' => $request->input('sort'),
            ],
        ]);
    }

    public function store(AdminAdminStoreRequest $request): JsonResponse
    {
        Admin::create($request->validated());

        return response()->json(['message' => __('Admin created.')], 201);
    }

    public function update(AdminAdminUpdateRequest $request, Admin $admin): JsonResponse
    {
        $data = $request->validated();

        if (! isset($data['password']) || $data['password'] === null || $data['password'] === '') {
            unset($data['password']);
        }

        $admin->update($data);

        return response()->json(['message' => __('Admin updated.')]);
    }

    public function destroy(Admin $admin): JsonResponse
    {
        if ($admin->id === Auth::guard('admin')->id()) {
            return response()->json(['message' => __('Cannot delete your own account.')], 403);
        }

        $admin->delete();

        return response()->json(['message' => __('Admin deleted.')]);
    }
}
