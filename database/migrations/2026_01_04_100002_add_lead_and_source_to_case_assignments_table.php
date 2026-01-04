<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds is_lead_investigator and internal_source fields for dual investigator support.
     */
    public function up(): void
    {
        Schema::table('case_assignments', function (Blueprint $table) {
            // Add lead investigator flag - only one per case
            $table->boolean('is_lead_investigator')->default(false)->after('investigator_type');

            // Add internal source to track where internal investigator came from
            // branch_admin, company_admin, super_admin
            $table->string('internal_source', 30)->nullable()->after('is_lead_investigator');

            // Add indexes
            $table->index(['case_id', 'is_lead_investigator']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('case_assignments', function (Blueprint $table) {
            $table->dropIndex(['case_id', 'is_lead_investigator']);
            $table->dropColumn(['is_lead_investigator', 'internal_source']);
        });
    }
};
