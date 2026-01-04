<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds investigator_type field to support:
     * - internal: Branch admins who are not involved in the case
     * - external: Investigators assigned to the company (role = investigator)
     */
    public function up(): void
    {
        Schema::table('case_assignments', function (Blueprint $table) {
            // Add investigator type column
            $table->string('investigator_type', 20)->default('external')->after('investigator_id');
            // internal = branch admins not involved in case
            // external = investigators assigned to company

            // Add index for filtering by type
            $table->index('investigator_type');
            $table->index(['case_id', 'investigator_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('case_assignments', function (Blueprint $table) {
            $table->dropIndex(['investigator_type']);
            $table->dropIndex(['case_id', 'investigator_type', 'status']);
            $table->dropColumn('investigator_type');
        });
    }
};
