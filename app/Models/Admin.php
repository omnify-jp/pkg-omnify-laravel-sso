<?php

namespace Omnify\Core\Models;

use Omnify\Core\Models\Base\AdminBaseModel;

/**
 * Admin Model — standalone mode only.
 *
 * Separate from User. Used for system administrators
 * who manage the application via the admin:create command.
 */
class Admin extends AdminBaseModel
{
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
