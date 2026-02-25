<?php

namespace Omnify\SsoClient\Models;

use Omnify\SsoClient\Models\Base\PasswordResetTokenBaseModel;
use Omnify\SsoClient\Database\Factories\PasswordResetTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * PasswordResetToken Model
 *
 * This file is generated once and can be customized.
 * Add your custom methods and logic here.
 */
class PasswordResetToken extends PasswordResetTokenBaseModel
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
    protected static function newFactory(): PasswordResetTokenFactory
    {
        return PasswordResetTokenFactory::new();
    }

    // Add your custom methods here
}
