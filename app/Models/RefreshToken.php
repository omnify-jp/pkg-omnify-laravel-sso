<?php

namespace Omnify\Core\Models;

use Omnify\Core\Models\Base\RefreshTokenBaseModel;
use Omnify\Core\Database\Factories\RefreshTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * RefreshToken Model
 *
 * This file is generated once and can be customized.
 * Add your custom methods and logic here.
 */
class RefreshToken extends RefreshTokenBaseModel
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
    protected static function newFactory(): RefreshTokenFactory
    {
        return RefreshTokenFactory::new();
    }

    // Add your custom methods here
}
