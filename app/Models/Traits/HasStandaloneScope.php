<?php

declare(strict_types=1);

namespace Omnify\Core\Models\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Trait for models that track data origin (standalone vs console mode).
 *
 * - Global scope: automatically filters queries by current auth mode
 *   (standalone mode → is_standalone=true, console mode → is_standalone=false)
 * - Auto-sets `is_standalone` on creation based on current mode
 * - Use `withoutGlobalScope('standalone_mode')` to bypass the filter
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
        // Global scope: in console mode, auto-filter to only show console-synced data.
        // In standalone mode, no filter needed (all data is local).
        if (config('omnify-auth.mode') === 'console') {
            static::addGlobalScope('standalone_mode', function (Builder $builder) {
                $table = (new static)->getTable();
                $builder->where("{$table}.is_standalone", false);
            });
        }

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
