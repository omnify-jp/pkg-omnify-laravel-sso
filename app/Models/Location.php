<?php

namespace Omnify\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Omnify\Core\Database\Factories\LocationFactory;
use Omnify\Core\Models\Base\LocationBaseModel;
use Omnify\Core\Models\Traits\HasStandaloneScope;

/**
 * Location Model
 *
 * This file is generated once and can be customized.
 * Add your custom methods and logic here.
 */
class Location extends LocationBaseModel
{
    use HasFactory;
    use HasStandaloneScope;

    /**
     * Create a new model instance.
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): LocationFactory
    {
        return LocationFactory::new();
    }

    /**
     * Get the branch this location belongs to.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'console_branch_id', 'console_branch_id');
    }

    /**
     * Get the organization this location belongs to.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'console_organization_id', 'console_organization_id');
    }
}
