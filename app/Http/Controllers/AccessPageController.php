<?php

declare(strict_types=1);

namespace Omnify\Core\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Omnify\Core\Models\Branch;
use Omnify\Core\Models\Organization;
use Omnify\Core\Models\Permission;
use Omnify\Core\Models\Role;
use Omnify\Core\Models\User;

/**
 * Controller for Access Management (IAM) pages.
 *
 * Renders Inertia pages for user, role, team, and permission management.
 * Page paths are configurable via 'omnify-auth.routes.access_pages_path'.
 */
class AccessPageController extends Controller
{
    /**
     * Get the base path for IAM pages.
     */
    protected function getPagePath(string $page): string
    {
        $basePath = config('omnify-auth.routes.access_pages_path', 'admin/iam');

        return "{$basePath}/{$page}";
    }

    /**
     * Build a flat list of all scoped assignments with user and role data embedded.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function getAllAssignments(): \Illuminate\Support\Collection
    {
        // Build console_id → local_id maps to resolve pivot IDs into local DB IDs
        $orgMap = Organization::query()
            ->pluck('id', 'console_organization_id');

        $branchMap = Branch::query()
            ->pluck('id', 'console_branch_id');

        $orgNameMap = Organization::query()
            ->pluck('name', 'console_organization_id');

        $branchNameMap = Branch::query()
            ->pluck('name', 'console_branch_id');

        return User::query()
            ->with(['roles' => fn ($q) => $q
                ->withPivot('console_organization_id', 'console_branch_id', 'created_at'),
            ])
            ->whereHas('roles')
            ->get()
            ->flatMap(fn ($user) => $user->roles->map(fn ($role) => [
                'id' => "{$user->id}:{$role->id}",
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'level' => $role->level,
                ],
                'scope_type' => match (true) {
                    $role->pivot->console_branch_id !== null => 'branch',
                    $role->pivot->console_organization_id !== null => 'org-wide',
                    default => 'global',
                },
                'organization_id' => $orgMap[$role->pivot->console_organization_id] ?? null,
                'branch_id' => $branchMap[$role->pivot->console_branch_id] ?? null,
                'organization_name' => $orgNameMap[$role->pivot->console_organization_id] ?? null,
                'branch_name' => $branchNameMap[$role->pivot->console_branch_id] ?? null,
                'created_at' => $role->pivot->created_at?->toISOString(),
            ]))
            ->values();
    }

    /**
     * Serialize organizations for frontend.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function getOrganizations(): \Illuminate\Support\Collection
    {
        return Organization::query()
            ->orderBy('name')
            ->get()
            ->map(fn ($org) => [
                'id' => $org->id,
                'console_organization_id' => $org->console_organization_id,
                'name' => $org->name,
                'slug' => $org->slug,
                'is_active' => (bool) $org->is_active,
            ]);
    }

    /**
     * Serialize branches for frontend.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function getBranches(): \Illuminate\Support\Collection
    {
        return Branch::query()
            ->orderBy('name')
            ->get()
            ->map(fn ($branch) => [
                'id' => $branch->id,
                'console_branch_id' => $branch->console_branch_id,
                'console_organization_id' => $branch->console_organization_id,
                'name' => $branch->name,
                'is_headquarters' => (bool) $branch->is_headquarters,
                'is_active' => (bool) $branch->is_active,
            ]);
    }

    /**
     * IAM overview / dashboard page.
     */
    public function overview(): Response
    {
        $totalUsers = User::query()->count();
        $totalRoles = Role::query()->count();
        $globalRoles = Role::query()->whereNull('console_organization_id')->count();
        $totalPermissions = Permission::query()->count();

        $recentAssignments = User::query()
            ->with(['roles' => fn ($q) => $q
                ->withPivot('console_organization_id', 'console_branch_id')
                ->orderByPivot('created_at', 'desc')
                ->limit(10),
            ])
            ->whereHas('roles')
            ->get()
            ->flatMap(fn ($user) => $user->roles->map(fn ($role) => [
                'user' => ['id' => $user->id, 'name' => $user->name],
                'role' => ['id' => $role->id, 'name' => $role->name],
                'scope_type' => match (true) {
                    $role->pivot->console_branch_id !== null => 'branch',
                    $role->pivot->console_organization_id !== null => 'org-wide',
                    default => 'global',
                },
                'organization_name' => null,
                'branch_name' => null,
                'created_at' => $role->pivot->created_at?->toISOString(),
            ]))
            ->sortByDesc('created_at')
            ->take(10)
            ->values();

        return Inertia::render($this->getPagePath('overview'), [
            'stats' => [
                'total_users' => $totalUsers,
                'total_roles' => $totalRoles,
                'global_roles' => $globalRoles,
                'org_scoped_roles' => $totalRoles - $globalRoles,
                'total_permissions' => $totalPermissions,
            ],
            'recent_assignments' => $recentAssignments,
        ]);
    }

    /**
     * Scope explorer page — shows tree + assignment panel.
     */
    public function scopeExplorer(): Response
    {
        return Inertia::render($this->getPagePath('scope-explorer'), [
            'organizations' => $this->getOrganizations(),
            'branches' => $this->getBranches(),
            'assignments' => $this->getAllAssignments(),
        ]);
    }

    /**
     * Users list page.
     */
    public function users(): Response
    {
        $search = (string) request()->get('search', '');

        $users = User::query()
            ->when($search, fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"))
            ->withCount('roles')
            ->paginate(20);

        return Inertia::render($this->getPagePath('users'), [
            'users' => [
                'data' => $users->map(fn ($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles_count' => $user->roles_count,
                    'created_at' => $user->created_at?->toISOString(),
                ]),
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                ],
                'links' => [
                    'first' => $users->url(1),
                    'last' => $users->url($users->lastPage()),
                    'prev' => $users->previousPageUrl(),
                    'next' => $users->nextPageUrl(),
                ],
            ],
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * User detail page.
     */
    public function userShow(string $userId): Response
    {
        $user = User::query()->findOrFail($userId);

        $userRoles = $user->roles()
            ->withPivot('console_organization_id', 'console_branch_id')
            ->with('permissions')
            ->get();

        $allPermissions = Permission::query()
            ->orderBy('group')
            ->orderBy('name')
            ->get();

        $orgMap = Organization::query()->pluck('id', 'console_organization_id');
        $branchMap = Branch::query()->pluck('id', 'console_branch_id');
        $orgNameMap = Organization::query()->pluck('name', 'console_organization_id');
        $branchNameMap = Branch::query()->pluck('name', 'console_branch_id');

        $assignments = $userRoles->map(fn ($role) => [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'level' => $role->level,
            ],
            'scope_type' => match (true) {
                $role->pivot->console_branch_id !== null => 'branch',
                $role->pivot->console_organization_id !== null => 'org-wide',
                default => 'global',
            },
            'organization_id' => $orgMap[$role->pivot->console_organization_id] ?? null,
            'branch_id' => $branchMap[$role->pivot->console_branch_id] ?? null,
            'organization_name' => $orgNameMap[$role->pivot->console_organization_id] ?? null,
            'branch_name' => $branchNameMap[$role->pivot->console_branch_id] ?? null,
            'created_at' => $role->pivot->created_at?->toISOString(),
        ]);

        /** @var array<string, list<string>> $rolePermissions */
        $rolePermissions = $userRoles->mapWithKeys(
            fn ($role) => [$role->id => $role->permissions->pluck('id')->all()],
        )->all();

        return Inertia::render($this->getPagePath('user-detail'), [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at?->toISOString(),
            ],
            'assignments' => $assignments,
            'all_permissions' => $allPermissions->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'group' => $p->group,
            ]),
            'role_permissions' => $rolePermissions,
        ]);
    }

    /**
     * All assignments list page.
     */
    public function assignments(): Response
    {
        return Inertia::render($this->getPagePath('assignments'), [
            'assignments' => $this->getAllAssignments(),
        ]);
    }

    /**
     * Assignment create form page.
     */
    public function assignmentCreate(): Response
    {
        $defaultScope = request()->get('scope', 'global');
        $defaultScopeId = request()->get('scopeId');

        return Inertia::render($this->getPagePath('assignment-create'), [
            'users' => User::query()->orderBy('name')->get()->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'created_at' => $u->created_at?->toISOString(),
            ]),
            'roles' => Role::query()->orderBy('level')->orderBy('name')->get()->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'slug' => $r->slug,
                'description' => $r->description,
                'level' => $r->level,
                'is_global' => $r->console_organization_id === null,
                'created_at' => $r->created_at?->toISOString(),
            ]),
            'organizations' => $this->getOrganizations(),
            'branches' => $this->getBranches(),
            'default_scope' => $defaultScope,
            'default_scope_id' => $defaultScopeId,
        ]);
    }

    /**
     * Store a new assignment.
     */
    public function assignmentStore(): RedirectResponse
    {
        $data = request()->validate([
            'user_id' => ['required', 'string', 'exists:users,id'],
            'role_id' => ['required', 'string', 'exists:roles,id'],
            'scope_type' => ['required', 'in:global,org-wide,branch'],
            'organization_id' => ['nullable', 'string'],
            'branch_id' => ['nullable', 'string'],
        ]);

        $user = User::query()->findOrFail($data['user_id']);

        $pivot = [
            'console_organization_id' => $data['organization_id'] ?? null,
            'console_branch_id' => $data['branch_id'] ?? null,
        ];

        $user->roles()->syncWithoutDetaching([
            $data['role_id'] => $pivot,
        ]);

        return redirect()->route('access.assignments')->with('success', 'Assignment created.');
    }

    /**
     * Delete an assignment (user+role combo).
     */
    public function assignmentDelete(string $userId, string $roleId): RedirectResponse
    {
        $user = User::query()->findOrFail($userId);
        $user->roles()->detach($roleId);

        return back()->with('success', 'Assignment removed.');
    }

    /**
     * Roles list page.
     */
    public function roles(): Response
    {
        $roles = Role::query()
            ->withCount('permissions')
            ->get()
            ->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'level' => $role->level,
                'is_global' => $role->console_organization_id === null,
                'permissions_count' => $role->permissions_count,
                'created_at' => $role->created_at?->toISOString(),
            ]);

        return Inertia::render($this->getPagePath('roles'), [
            'roles' => $roles,
        ]);
    }

    /**
     * Role detail page.
     */
    public function roleShow(string $roleId): Response
    {
        $role = Role::query()->withCount('permissions')->findOrFail($roleId);

        $rolePermissions = $role->permissions()->get();

        $allPermissions = Permission::query()
            ->orderBy('group')
            ->orderBy('name')
            ->get();

        $orgMap = Organization::query()->pluck('id', 'console_organization_id');
        $branchMap = Branch::query()->pluck('id', 'console_branch_id');
        $orgNameMap = Organization::query()->pluck('name', 'console_organization_id');
        $branchNameMap = Branch::query()->pluck('name', 'console_branch_id');

        $assignments = User::query()
            ->whereHas('roles', fn ($q) => $q->where('roles.id', $roleId))
            ->with(['roles' => fn ($q) => $q
                ->where('roles.id', $roleId)
                ->withPivot('console_organization_id', 'console_branch_id'),
            ])
            ->get()
            ->flatMap(fn ($user) => $user->roles->map(fn ($r) => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'scope_type' => match (true) {
                    $r->pivot->console_branch_id !== null => 'branch',
                    $r->pivot->console_organization_id !== null => 'org-wide',
                    default => 'global',
                },
                'organization_id' => $orgMap[$r->pivot->console_organization_id] ?? null,
                'branch_id' => $branchMap[$r->pivot->console_branch_id] ?? null,
                'organization_name' => $orgNameMap[$r->pivot->console_organization_id] ?? null,
                'branch_name' => $branchNameMap[$r->pivot->console_branch_id] ?? null,
            ]))
            ->values();

        return Inertia::render($this->getPagePath('role-detail'), [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'level' => $role->level,
                'is_global' => $role->console_organization_id === null,
                'permissions_count' => $role->permissions_count,
                'created_at' => $role->created_at?->toISOString(),
            ],
            'permissions' => $rolePermissions->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'group' => $p->group,
            ]),
            'all_permissions' => $allPermissions->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'group' => $p->group,
            ]),
            'assignments' => $assignments,
        ]);
    }

    /**
     * Role create form page.
     */
    public function roleCreate(): Response
    {
        $allPermissions = Permission::query()
            ->orderBy('group')
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'group' => $p->group,
            ]);

        return Inertia::render($this->getPagePath('role-create'), [
            'all_permissions' => $allPermissions,
        ]);
    }

    /**
     * Store a new role.
     */
    public function roleStore(): RedirectResponse
    {
        $data = request()->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'level' => ['required', 'integer', 'min:1', 'max:10'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['string', 'exists:permissions,id'],
        ]);

        $role = Role::query()->create([
            'name' => $data['name'],
            'slug' => \Illuminate\Support\Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'level' => $data['level'],
        ]);

        if (! empty($data['permission_ids'])) {
            $role->permissions()->sync($data['permission_ids']);
        }

        return redirect()->route('access.roles.show', $role->id)
            ->with('success', 'Role created.');
    }

    /**
     * Role edit form page.
     */
    public function roleEdit(string $roleId): Response
    {
        $role = Role::query()->withCount('permissions')->findOrFail($roleId);
        $rolePermissions = $role->permissions()->get();
        $allPermissions = Permission::query()
            ->orderBy('group')
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'group' => $p->group,
            ]);

        return Inertia::render($this->getPagePath('role-edit'), [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'level' => $role->level,
                'is_global' => $role->console_organization_id === null,
                'permissions_count' => $role->permissions_count,
                'created_at' => $role->created_at?->toISOString(),
            ],
            'permissions' => $rolePermissions->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'group' => $p->group,
            ]),
            'all_permissions' => $allPermissions,
        ]);
    }

    /**
     * Update an existing role.
     */
    public function roleUpdate(string $roleId): RedirectResponse
    {
        $role = Role::query()->findOrFail($roleId);

        $data = request()->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'level' => ['required', 'integer', 'min:1', 'max:10'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['string', 'exists:permissions,id'],
        ]);

        $role->update([
            'name' => $data['name'],
            'slug' => \Illuminate\Support\Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'level' => $data['level'],
        ]);

        $role->permissions()->sync($data['permission_ids'] ?? []);

        return redirect()->route('access.roles.show', $role->id)
            ->with('success', 'Role updated.');
    }

    /**
     * Permissions list page.
     */
    public function permissions(): Response
    {
        $permissions = Permission::query()
            ->orderBy('group')
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'group' => $p->group,
            ]);

        return Inertia::render($this->getPagePath('permissions'), [
            'permissions' => $permissions,
        ]);
    }
}
