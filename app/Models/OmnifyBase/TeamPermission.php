<?php

namespace Omnify\SsoClient\Models\OmnifyBase;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Omnify\SsoClient\Models\OmnifyBase\Base\TeamPermissionBaseModel;

/**
 * TeamPermission Model
 *
 * This file is generated once and can be customized.
 * Add your custom methods and logic here.
 */
class TeamPermission extends TeamPermissionBaseModel
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
