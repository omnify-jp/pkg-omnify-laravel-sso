<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Models\Traits;

use Illuminate\Database\Eloquent\Builder;
use Omnify\SsoClient\Facades\Context;

/**
 * Trait for models that belong to an organization.
 *
 * Adds query scopes for filtering by organization context.
 * Does NOT override relationship methods - those are in BaseModel.
 *
 * Requirements:
 * - Model must have `organization_id` column
 * - BaseModel already has `organization()` relationship
 *
 * @method static Builder forOrganization(string|int $organizationId)
 * @method static Builder inCurrentOrganization()
 */
trait HasOrganizationScope
{
    /**
     * Boot the trait.
     */
    public static function bootHasOrganizationScope(): void
    {
        // Auto-fill organization_id on creating
        static::creating(function ($model) {
            if (empty($model->organization_id) && Context::hasOrganization()) {
                $model->organization_id = Context::organizationId();
            }
        });

        // Prevent changing organization_id on update
        static::updating(function ($model) {
            if ($model->isDirty('organization_id') && $model->getOriginal('organization_id') !== null) {
                // Restore original organization_id
                $model->organization_id = $model->getOriginal('organization_id');
            }
        });
    }

    /**
     * Scope to filter by specific organization.
     */
    public function scopeForOrganization(Builder $query, string|int $organizationId): Builder
    {
        return $query->where($this->getTable().'.organization_id', $organizationId);
    }

    /**
     * Scope to filter by current organization context.
     *
     * @throws \RuntimeException If no organization context is set
     */
    public function scopeInCurrentOrganization(Builder $query): Builder
    {
        $organizationId = Context::organizationId();

        if ($organizationId === null) {
            throw new \RuntimeException('No organization context set. Ensure SsoOrganizationAccess middleware is applied.');
        }

        return $query->where($this->getTable().'.organization_id', $organizationId);
    }
}
