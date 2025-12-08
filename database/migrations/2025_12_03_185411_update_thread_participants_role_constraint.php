<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the existing role constraint
        DB::statement("ALTER TABLE thread_participants DROP CONSTRAINT IF EXISTS thread_participants_role_check");

        // Add new role constraint with additional roles
        DB::statement("ALTER TABLE thread_participants ADD CONSTRAINT thread_participants_role_check CHECK (role IN ('reporter', 'investigator', 'company_admin', 'branch_admin', 'super_admin', 'admin'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the updated constraint
        DB::statement("ALTER TABLE thread_participants DROP CONSTRAINT IF EXISTS thread_participants_role_check");

        // Restore original constraint
        DB::statement("ALTER TABLE thread_participants ADD CONSTRAINT thread_participants_role_check CHECK (role IN ('reporter', 'investigator'))");
    }
};
