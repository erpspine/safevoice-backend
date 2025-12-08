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
            // Add user role and authentication fields
            $table->enum('role', [
                'super_admin',      // System super administrator
                'admin',            // Company administrator  
                'branch_manager',   // Branch manager
                'department_head',  // Department head
                'investigator',     // Case investigator
                'user',            // Regular company user
                'viewer'           // Read-only user
            ])->default('user');

            $table->enum('status', [
                'active',
                'inactive',
                'suspended',
                'pending'
            ])->default('pending');

            // Authentication and security fields
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->integer('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('force_password_change')->default(false);

            // Profile fields
            $table->string('phone')->nullable();
            $table->string('employee_id')->nullable()->unique();
            $table->text('permissions')->nullable(); // JSON field for custom permissions

            // Add indexes for performance
            $table->index(['role']);
            $table->index(['status']);
            $table->index(['employee_id']);
            $table->index(['company_id', 'role']);
            $table->index(['branch_id', 'role']);
            $table->index(['is_verified', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['status']);
            $table->dropIndex(['employee_id']);
            $table->dropIndex(['company_id', 'role']);
            $table->dropIndex(['branch_id', 'role']);
            $table->dropIndex(['is_verified', 'status']);

            $table->dropColumn([
                'role',
                'status',
                'last_login_at',
                'last_login_ip',
                'failed_login_attempts',
                'locked_until',
                'is_verified',
                'force_password_change',
                'phone',
                'employee_id',
                'permissions'
            ]);
        });
    }
};
