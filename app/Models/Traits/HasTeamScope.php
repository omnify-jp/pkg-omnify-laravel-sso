<?php

declare(strict_types=1);

namespace Omnify\Core\Models\Traits;

use Illuminate\Database\Eloquent\Builder;
use Omnify\Core\Facades\Context;

/**
 * Trait for models that belong to an organization and team.
 *
 * Adds query scopes for filtering by organization and team context.
 * Does NOT override relationship methods - those are in BaseModel.
 *
 * Requirements:
 * - Model must have `organization_id` and `team_id` columns
 * - BaseModel already has `organization()` and `team()` relationships
 *
 * @method static Builder forOrganization(string|int $organizationId)
 * @method static Builder forTeam(string|int $teamId)
 * @method static Builder inCurrentOrganization()
 * @method static Builder inCurrentTeam()
 */
trait HasTeamScope
{
    use HasOrganizationScope;

    /**
     * Boot the trait.
     */
    public static function bootHasTeamScope(): void
    {
        // Auto-fill team_id on creating
        static::creating(function ($model) {
            if (empty($model->team_id) && Context::hasTeam()) {
                $model->team_id = Context::teamId();
            }
        });

        // Prevent changing team_id on update
        static::updating(function ($model) {
            if ($model->isDirty('team_id') && $model->getOriginal('team_id') !== null) {
                // Restore original team_id
                $model->team_id = $model->getOriginal('team_id');
            }
        });
    }

    /**
     * Scope to filter by specific team.
     */
    public function scopeForTeam(Builder $query, string|int $teamId): Builder
    {
        return $query->where($this->getTable().'.team_id', $teamId);
    }

    /**
     * Scope to filter by current team context.
     *
     * @throws \RuntimeException If no team context is set
     */
    public function scopeInCurrentTeam(Builder $query): Builder
    {
        $teamId = Context::teamId();

        if ($teamId === null) {
            throw new \RuntimeException('No team context set. Ensure team context is provided.');
        }

        return $query->where($this->getTable().'.team_id', $teamId);
    }
}
