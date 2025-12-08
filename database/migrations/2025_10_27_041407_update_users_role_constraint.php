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
        // Drop existing role constraint
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check;');

        // Update old role values to new ones
        $updates = [
            'branch_manager' => 'branch_admin',
            'department_head' => 'investigator',
            'user' => 'investigator',
            'viewer' => 'investigator'
        ];

        foreach ($updates as $oldRole => $newRole) {
            DB::table('users')
                ->where('role', $oldRole)
                ->update(['role' => $newRole, 'updated_at' => now()]);
        }

        // Add new role constraint with updated values
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('super_admin', 'admin', 'company_admin', 'branch_admin', 'investigator'));");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the new constraint
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check;');

        // Restore old constraint (for rollback)
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('super_admin', 'admin', 'branch_manager', 'department_head', 'investigator', 'user', 'viewer'));");
    }
};
