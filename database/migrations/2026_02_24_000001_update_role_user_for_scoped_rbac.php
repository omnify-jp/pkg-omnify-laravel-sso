<?php

/**
 * Custom migration: Update role_user table to support scoped RBAC.
 *
 * The auto-generated migration creates role_user with PRIMARY KEY (user_id, role_id),
 * which only allows ONE role assignment per user per role (across all scopes).
 * Scoped RBAC requires the same role to be assigned in different scopes
 * (global, org-wide, branch-specific), so we recreate the table with a proper schema.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Recreate with bigint id as PK to allow same role in multiple scopes
        Schema::dropIfExists('role_user');

        Schema::create('role_user', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('user_id');
            $table->uuid('role_id');
            $table->string('console_organization_id', 36)->nullable();
            $table->string('console_branch_id', 36)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade')->onUpdate('cascade');
            $table->index(['user_id', 'role_id']);
            $table->index('user_id');
            $table->index('role_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');

        Schema::create('role_user', function (Blueprint $table) {
            $table->uuid('user_id');
            $table->uuid('role_id');
            $table->string('console_organization_id', 36)->nullable();
            $table->string('console_branch_id', 36)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade')->onUpdate('cascade');
            $table->unique(['user_id', 'role_id']);
            $table->index('user_id');
            $table->index('role_id');
            $table->primary(['user_id', 'role_id']);
        });
    }
};
