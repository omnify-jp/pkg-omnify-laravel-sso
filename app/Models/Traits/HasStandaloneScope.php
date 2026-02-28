<?php

declare(strict_types=1);

namespace Omnify\Core\Models\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Trait for models that track data origin (standalone vs console mode).
 *
 * Auto-sets `is_standalone` based on current OMNIFY_AUTH_MODE on creation.
 * Provides query scopes for filtering by mode.
 *
 * Requirements:
 * - Model must have `is_standalone` boolean column
 *
 * @method static Builder standalone()
 * @method static Builder console()
 * @method static Builder currentMode()
 */
trait HasStandaloneScope
{
    /**
     * Boot the trait.
     */
    public static function bootHasStandaloneScope(): void
    {
        static::creating(function ($model) {
            if (! isset($model->is_standalone)) {
                $model->is_standalone = config('omnify-auth.mode') === 'standalone';
            }
        });
    }

    /**
     * Scope to filter records created in standalone mode.
     */
    public function scopeStandalone(Builder $query): Builder
    {
        return $query->where($this->getTable().'.is_standalone', true);
    }

    /**
     * Scope to filter records created in console mode.
     */
    public function scopeConsole(Builder $query): Builder
    {
        return $query->where($this->getTable().'.is_standalone', false);
    }

    /**
     * Scope to filter records matching the current auth mode.
     */
    public function scopeCurrentMode(Builder $query): Builder
    {
        return $query->where($this->getTable().'.is_standalone', config('omnify-auth.mode') === 'standalone');
    }
}
