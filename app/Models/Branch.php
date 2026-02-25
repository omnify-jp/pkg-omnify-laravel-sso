<?php

namespace Omnify\SsoClient\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Omnify\SsoClient\Database\Factories\BranchFactory;
use Omnify\SsoClient\Models\Base\BranchBaseModel;

/**
 * Branch Model
 *
 * This file is generated once and can be customized.
 * Add your custom methods and logic here.
 */
class Branch extends BranchBaseModel
{
    use HasFactory;

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
}
