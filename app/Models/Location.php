<?php

namespace Omnify\SsoClient\Models;

use Omnify\SsoClient\Models\Base\LocationBaseModel;
use Omnify\SsoClient\Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Location Model
 *
 * This file is generated once and can be customized.
 * Add your custom methods and logic here.
 */
class Location extends LocationBaseModel
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
    protected static function newFactory(): LocationFactory
    {
        return LocationFactory::new();
    }

    // Add your custom methods here
}
