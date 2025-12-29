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
        Schema::table('case_assignments', function (Blueprint $table) {
            // Rename assigned_by_user_id to assigned_by for consistency
            $table->renameColumn('assigned_by_user_id', 'assigned_by');

            // Rename unassigned_by_user_id to unassigned_by for consistency
            $table->renameColumn('unassigned_by_user_id', 'unassigned_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('case_assignments', function (Blueprint $table) {
            // Revert the column names
            $table->renameColumn('assigned_by', 'assigned_by_user_id');
            $table->renameColumn('unassigned_by', 'unassigned_by_user_id');
        });
    }
};
