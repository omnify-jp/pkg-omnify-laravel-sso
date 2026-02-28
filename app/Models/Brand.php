<?php

namespace Omnify\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Omnify\Core\Database\Factories\BrandFactory;
use Omnify\Core\Models\Base\BrandBaseModel;
use Omnify\Core\Models\Traits\HasStandaloneScope;

/**
 * Brand Model
 *
 * This file is generated once and can be customized.
 * Add your custom methods and logic here.
 */
class Brand extends BrandBaseModel
{
    use HasFactory;
    use HasStandaloneScope;

    protected static function newFactory(): BrandFactory
    {
        return BrandFactory::new();
    }

    /**
     * Get the organization that owns this brand.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'console_organization_id', 'console_organization_id');
    }

    /**
     * Get the branches under this brand.
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class, 'console_brand_id', 'console_brand_id');
    }
}
