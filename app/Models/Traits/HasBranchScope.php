<?php

declare(strict_types=1);

namespace Omnify\Core\Models\Traits;

use Illuminate\Database\Eloquent\Builder;
use Omnify\Core\Facades\Context;

/**
 * Trait for models that belong to an organization and branch.
 *
 * Adds query scopes for filtering by organization and branch context.
 * Does NOT override relationship methods - those are in BaseModel.
 *
 * Requirements:
 * - Model must have `organization_id` and `branch_id` columns
 * - BaseModel already has `organization()` and `branch()` relationships
 *
 * @method static Builder forOrganization(string|int $organizationId)
 * @method static Builder forBranch(string|int $branchId)
 * @method static Builder inCurrentOrganization()
 * @method static Builder inCurrentBranch()
 * @method static Builder inCurrentContext()
 */
trait HasBranchScope
{
    use HasOrganizationScope;

    /**
     * Boot the trait.
     */
    public static function bootHasBranchScope(): void
    {
        // Auto-fill branch_id on creating
        static::creating(function ($model) {
            if (empty($model->branch_id) && Context::hasBranch()) {
                $model->branch_id = Context::branchId();
            }
        });

        // Prevent changing branch_id on update
        static::updating(function ($model) {
            if ($model->isDirty('branch_id') && $model->getOriginal('branch_id') !== null) {
                // Restore original branch_id
                $model->branch_id = $model->getOriginal('branch_id');
            }
        });
    }

    /**
     * Scope to filter by specific branch.
     */
    public function scopeForBranch(Builder $query, string|int $branchId): Builder
    {
        return $query->where($this->getTable().'.branch_id', $branchId);
    }

    /**
     * Scope to filter by current branch context.
     *
     * @throws \RuntimeException If no branch context is set
     */
    public function scopeInCurrentBranch(Builder $query): Builder
    {
        $branchId = Context::branchId();

        if ($branchId === null) {
            throw new \RuntimeException('No branch context set. Ensure X-Branch-Id header is provided or use sso.require-branch middleware.');
        }

        return $query->where($this->getTable().'.branch_id', $branchId);
    }

    /**
     * Scope to filter by current organization and branch context.
     *
     * If branch context is set, filters by both org and branch.
     * If only org context is set, filters by org only.
     */
    public function scopeInCurrentContext(Builder $query): Builder
    {
        $query->inCurrentOrganization();

        if (Context::hasBranch()) {
            $query->where($this->getTable().'.branch_id', Context::branchId());
        }

        return $query;
    }
}
