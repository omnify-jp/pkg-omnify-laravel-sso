<?php

namespace Omnify\Core\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Omnify\Core\Database\Factories\AdminFactory;
use Omnify\Core\Models\Base\AdminBaseModel;

/**
 * Admin Model — standalone mode only.
 *
 * Separate from User. Used for system administrators
 * who manage the application via the admin:create command.
 */
class Admin extends AdminBaseModel
{
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return AdminFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'password' => 'hashed',
        ];
    }
}
