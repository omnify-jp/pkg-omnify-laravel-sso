<?php

namespace Omnify\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Omnify\Core\Database\Factories\OrganizationFactory;
use Omnify\Core\Models\Base\OrganizationBaseModel;
use Omnify\Core\Models\Traits\HasStandaloneScope;

/**
 * Organization Model
 *
 * This file is generated once and can be customized.
 * Add your custom methods and logic here.
 */
class Organization extends OrganizationBaseModel
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
    protected static function newFactory(): OrganizationFactory
    {
        return OrganizationFactory::new();
    }

    /**
     * Get the route key for the model (slug-based route binding).
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
