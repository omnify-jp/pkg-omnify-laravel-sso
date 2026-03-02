<?php

namespace Omnify\Core\Http\Controllers\Standalone\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Omnify\Core\Http\Requests\Admin\OrganizationUserStoreRequest;
use Omnify\Core\Http\Requests\Admin\OrganizationUserUpdateRequest;
use Omnify\Core\Models\Organization;
use Omnify\Core\Models\User;
use Omnify\Core\Notifications\WelcomeUserNotification;
use Omnify\Core\Services\UserRoleService;

class OrganizationUserAdminController
{
    public function __construct(
        private UserRoleService $userRoleService
    ) {}

    /**
     * Search for a user by email globally, return user + already_in_org flag.
     */
    public function search(Request $request, Organization $organization): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if (! $user) {
            return response()->json(['user' => null]);
        }

        $alreadyInOrg = $user->roles()
            ->wherePivot('console_organization_id', $organization->console_organization_id)
            ->exists();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'already_in_org' => $alreadyInOrg,
            ],
        ]);
    }

    /**
     * Add a user to the organization. Creates the user if they do not exist.
     */
    public function store(OrganizationUserStoreRequest $request, Organization $organization): JsonResponse
    {
        $isNewUser = false;
        $user = User::where('email', $request->input('email'))->first();

        if (! $user) {
            $user = User::create([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'password' => Hash::make(Str::random(16)),
                'is_default_password' => true,
                'is_standalone' => true,
            ]);
            $isNewUser = true;
        }

        $result = $this->userRoleService->assignRole($user, [
            'role_id' => $request->input('role_id'),
            'console_organization_id' => $organization->console_organization_id,
            'console_branch_id' => $request->input('console_branch_id'),
        ]);

        if (! $result['success']) {
            return response()->json(['message' => $result['message'] ?? __('Failed to assign role.')], 409);
        }

        if ($isNewUser) {
            $token = Password::createToken($user);
            $resetUrl = url('/password/reset/'.$token.'?email='.urlencode($user->email));
            $user->notify(new WelcomeUserNotification($organization->name, $resetUrl));
        }

        return response()->json(['message' => __('User added to organization.')], 201);
    }

    /**
     * Update a user's role assignment within the organization.
     */
    public function update(OrganizationUserUpdateRequest $request, Organization $organization, User $user): JsonResponse
    {
        // Remove all existing role assignments for this user in this org
        $user->roles()
            ->wherePivot('console_organization_id', $organization->console_organization_id)
            ->detach();

        // Assign the new role
        $this->userRoleService->assignRole($user, [
            'role_id' => $request->input('role_id'),
            'console_organization_id' => $organization->console_organization_id,
            'console_branch_id' => $request->input('console_branch_id'),
        ]);

        return response()->json(['message' => __('Role assignment updated.')]);
    }

    /**
     * Remove all role assignments for a user in this organization (does not delete the user).
     */
    public function destroy(Organization $organization, User $user): JsonResponse
    {
        $user->roles()
            ->wherePivot('console_organization_id', $organization->console_organization_id)
            ->detach();

        return response()->json(['message' => __('User removed from organization.')]);
    }
}
