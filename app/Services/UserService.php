<?php

declare(strict_types=1);

namespace Omnify\Core\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Omnify\Core\Enums\ScopeType;
use Omnify\Core\Models\Branch;
use Omnify\Core\Models\Organization;
use Omnify\Core\Models\Team;
use Omnify\Core\Models\User;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UserService
{
    /**
     * Get paginated list of users with filters.
     *
     * @param  array{search?: string, organization_id: string, per_page?: int}  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = QueryBuilder::for(User::class)
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('name', 'like', "%{$value}%")
                            ->orWhere('email', 'like', "%{$value}%");
                    });
                }),
            ])
            ->allowedSorts(['id', 'name', 'email', 'created_at', 'updated_at'])
            ->defaultSort('-id');

        // Filter by organization (required)
        if (! empty($filters['organization_id'])) {
            $query->where('console_organization_id', $filters['organization_id']);
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    /**
     * Search users by email (autocomplete).
     */
    public function searchByEmail(string $email, string $organizationId, ?string $excludeUserId = null, int $limit = 10): Collection
    {
        if (strlen($email) < 2) {
            return collect([]);
        }

        $query = User::query()
            ->where('console_organization_id', $organizationId)
            ->where('email', 'like', "%{$email}%")
            ->limit($limit);

        if ($excludeUserId) {
            $query->where('id', '!=', $excludeUserId);
        }

        return $query->get();
    }

    /**
     * Get user by ID.
     */
    public function find(string $id): ?User
    {
        return User::find($id);
    }

    /**
     * Update user.
     *
     * @param  array{name?: string, email?: string}  $data
     */
    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user->fresh();
    }

    /**
     * Delete user.
     */
    public function delete(User $user): bool
    {
        return $user->delete();
    }

    /**
     * Get comprehensive permissions breakdown for a user.
     *
     * @return array{
     *     user: array,
     *     context: array{organization_id: ?string, branch_id: ?string},
     *     role_assignments: array,
     *     team_memberships: array,
     *     aggregated_permissions: array
     * }
     */
    public function getPermissionsBreakdown(User $user, ?string $organizationId = null, ?string $branchId = null): array
    {
        // Get user's primary organization
        $userOrg = $this->getUserOrganization($user);

        // Get role assignments with permissions for this context
        $roleAssignments = $this->getRoleAssignments($user, $organizationId, $branchId);

        // Get team memberships with permissions (if org context)
        $teamMemberships = $this->getTeamMemberships($user, $organizationId);

        // Get aggregated permissions as flat slug list
        $aggregatedPermissions = $user->getAllPermissions($organizationId, $branchId);

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'console_organization_id' => $user->console_organization_id,
                'organization' => $userOrg,
            ],
            'context' => [
                'organization_id' => $organizationId,
                'branch_id' => $branchId,
            ],
            'role_assignments' => $roleAssignments,
            'team_memberships' => $teamMemberships,
            'aggregated_permissions' => array_values($aggregatedPermissions),
        ];
    }

    /**
     * Get user's primary organization info.
     */
    private function getUserOrganization(User $user): ?array
    {
        if (! $user->console_organization_id) {
            return null;
        }

        $organizationCache = Organization::where('console_organization_id', $user->console_organization_id)->first();

        return $organizationCache ? [
            'id' => $organizationCache->id,
            'console_organization_id' => $organizationCache->console_organization_id,
            'name' => $organizationCache->name,
            'slug' => $organizationCache->slug,
        ] : null;
    }

    /**
     * Get role assignments with permissions for context.
     */
    private function getRoleAssignments(User $user, ?string $organizationId, ?string $branchId): array
    {
        $rolesForContext = $user->getRolesForContext($organizationId, $branchId);

        $roleAssignments = $rolesForContext->map(function ($role) {
            $organizationName = null;
            $branchName = null;

            if ($role->pivot->console_organization_id) {
                $org = Organization::where('console_organization_id', $role->pivot->console_organization_id)->first();
                $organizationName = $org?->name;
            }

            if ($role->pivot->console_branch_id) {
                $branch = Branch::where('console_branch_id', $role->pivot->console_branch_id)->first();
                $branchName = $branch?->name;
            }

            return [
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'level' => $role->level,
                ],
                'scope' => ScopeType::fromContext(
                    $role->pivot->console_organization_id ?? null,
                    $role->pivot->console_branch_id ?? null
                )->value,
                'console_organization_id' => $role->pivot->console_organization_id ?? null,
                'console_branch_id' => $role->pivot->console_branch_id ?? null,
                'organization_name' => $organizationName,
                'branch_name' => $branchName,
                'permissions' => $role->permissions->pluck('slug')->toArray(),
            ];
        });

        // Sort: global first, then org-wide, then branch
        return $roleAssignments->sortBy(function ($assignment) {
            return ScopeType::from($assignment['scope'])->sortOrder();
        })->values()->toArray();
    }

    /**
     * Get team memberships with permissions.
     */
    private function getTeamMemberships(User $user, ?string $organizationId): array
    {
        if (! $organizationId) {
            return [];
        }

        $teams = $user->getConsoleTeams($organizationId);
        $memberships = [];

        foreach ($teams as $team) {
            $teamCache = Team::where('console_team_id', $team['id'])->first();
            $memberships[] = [
                'team' => [
                    'id' => $team['id'],
                    'name' => $team['name'],
                    'path' => $team['path'] ?? null,
                ],
                'is_leader' => $team['is_leader'] ?? false,
                'permissions' => $teamCache ? $teamCache->permissions->pluck('slug')->toArray() : [],
            ];
        }

        return $memberships;
    }
}
