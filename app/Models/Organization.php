<?php

namespace Omnify\SsoClient\Models;

use Omnify\SsoClient\Models\Base\OrganizationBaseModel;
use Omnify\SsoClient\Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Organization Model
 *
 * This file is generated once and can be customized.
 * Add your custom methods and logic here.
 */
class Organization extends OrganizationBaseModel
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
    protected static function newFactory(): OrganizationFactory
    {
        return OrganizationFactory::new();
    }

    // Add your custom methods here
}
