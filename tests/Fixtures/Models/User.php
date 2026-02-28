<?php

namespace Omnify\Core\Tests\Fixtures\Models;

use Omnify\Core\Models\User as SsoUser;

/**
 * テスト用Userモデル
 *
 * パッケージのテストで使用するためのモデル
 * Extends the SSO Client User model for testing.
 *
 * Note: Uses parent's newFactory() method which returns Database\Factories\UserFactory
 */
class User extends SsoUser
{
    // Inherits newFactory() from parent
}
