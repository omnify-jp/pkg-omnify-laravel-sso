<?php

namespace Omnify\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Omnify\Core\Database\Factories\RolePermissionFactory;
use Omnify\Core\Models\Base\RolePermissionBaseModel;

/**
 * RolePermission Model
 *
 * This file is generated once and can be customized.
 * Add your custom methods and logic here.
 */
class RolePermission extends RolePermissionBaseModel
{
    use HasFactory;

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
    protected static function newFactory(): RolePermissionFactory
    {
        return RolePermissionFactory::new();
    }

    // Add your custom methods here
}
