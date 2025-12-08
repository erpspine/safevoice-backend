<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Composite indexes for common query patterns
            $table->index(['company_id', 'branch_id'], 'users_company_branch_idx');
            $table->index(['company_id', 'status'], 'users_company_status_idx');
            $table->index(['company_id', 'role'], 'users_company_role_idx');
            $table->index(['company_id', 'is_verified'], 'users_company_verified_idx');
            $table->index(['branch_id', 'status'], 'users_branch_status_idx');
            $table->index(['branch_id', 'role'], 'users_branch_role_idx');

            // Search optimization indexes
            $table->index(['name'], 'users_name_idx');
            $table->index(['email'], 'users_email_idx');
            $table->index(['employee_id'], 'users_employee_id_idx');

            // Performance indexes for filtering
            $table->index(['status', 'is_verified'], 'users_status_verified_idx');
            $table->index(['created_at'], 'users_created_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop composite indexes
            $table->dropIndex('users_company_branch_idx');
            $table->dropIndex('users_company_status_idx');
            $table->dropIndex('users_company_role_idx');
            $table->dropIndex('users_company_verified_idx');
            $table->dropIndex('users_branch_status_idx');
            $table->dropIndex('users_branch_role_idx');

            // Drop search indexes
            $table->dropIndex('users_name_idx');
            $table->dropIndex('users_email_idx');
            $table->dropIndex('users_employee_id_idx');

            // Drop performance indexes
            $table->dropIndex('users_status_verified_idx');
            $table->dropIndex('users_created_at_idx');
        });
    }
};
