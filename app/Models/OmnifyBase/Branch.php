<?php

namespace Omnify\SsoClient\Models\OmnifyBase;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Omnify\SsoClient\Models\OmnifyBase\Base\BranchBaseModel;

/**
 * Branch Model
 *
 * This file is generated once and can be customized.
 * Add your custom methods and logic here.
 */
class Branch extends BranchBaseModel
{
    use HasFactory;

    /**
     * Create a new model instance.
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    // Add your custom methods here
}
