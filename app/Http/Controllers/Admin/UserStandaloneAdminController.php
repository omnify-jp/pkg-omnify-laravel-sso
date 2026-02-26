<?php

namespace Omnify\SsoClient\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Omnify\SsoClient\Http\Requests\Admin\UserStandaloneStoreRequest;
use Omnify\SsoClient\Models\Role;
use Omnify\SsoClient\Models\User;

class UserStandaloneAdminController
{
    public function create(): Response
    {
        $pagesPath = config('omnify-auth.routes.standalone_admin_pages_path', 'admin');

        return Inertia::render("{$pagesPath}/users/create", [
            'roles' => Role::orderBy('level', 'desc')->get(['id', 'slug', 'name', 'level']),
        ]);
    }

    public function store(UserStandaloneStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        if (! empty($validated['role_id'])) {
            $role = Role::findOrFail($validated['role_id']);
            $user->assignRole($role, null, null);
        }

        return redirect()->route('access.users')
            ->with('success', __('User created successfully.'));
    }
}
