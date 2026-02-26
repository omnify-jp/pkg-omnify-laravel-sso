<?php

declare(strict_types=1);

/**
 * Custom migration: Add 2FA/TOTP columns to users table.
 *
 * These columns are defined in the User.yaml schema (google2fa_secret,
 * two_factor_recovery_codes, two_factor_confirmed_at) but require a separate
 * ALTER migration for existing installations because the omnify generate native
 * binary could not be executed to regenerate the create_users migration.
 *
 * The create_users migration (omnify/2026_02_21_122422_create_users_table.php)
 * has also been updated to include these columns for fresh installs.
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'google2fa_secret')) {
                $table->string('google2fa_secret', 100)->nullable()->after('console_token_expires_at')->comment('2FA Secret');
            }

            if (! Schema::hasColumn('users', 'two_factor_recovery_codes')) {
                $table->text('two_factor_recovery_codes')->nullable()->after('google2fa_secret')->comment('2FA Recovery Codes');
            }

            if (! Schema::hasColumn('users', 'two_factor_confirmed_at')) {
                $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes')->comment('2FA Confirmed At');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google2fa_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });
    }
};
