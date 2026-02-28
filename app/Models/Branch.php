<?php

namespace Omnify\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Omnify\Core\Database\Factories\BranchFactory;
use Omnify\Core\Models\Base\BranchBaseModel;
use Omnify\Core\Models\Traits\HasStandaloneScope;

/**
 * Branch Model
 *
 * This file is generated once and can be customized.
 * Add your custom methods and logic here.
 */
class Branch extends BranchBaseModel
{
    use HasFactory;
    use HasStandaloneScope;

    protected static function newFactory(): BranchFactory
    {
        return BranchFactory::new();
    }

    /**
     * Get the organization this branch belongs to.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'console_organization_id', 'console_organization_id');
    }

    /**
     * Get the brand this branch belongs to.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'console_brand_id', 'console_brand_id');
    }

    /**
     * Get the locations for this branch.
     */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class, 'console_branch_id', 'console_branch_id');
    }
}
