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
        Schema::table('cases', function (Blueprint $table) {
            // Remove columns if they exist
            if (Schema::hasColumn('cases', 'department_id')) {
                $table->dropColumn('department_id');
            }
            if (Schema::hasColumn('cases', 'severity_level')) {
                $table->dropColumn('severity_level');
            }

            // Rename existing columns if they exist
            if (Schema::hasColumn('cases', 'location') && !Schema::hasColumn('cases', 'location_description')) {
                $table->renameColumn('location', 'location_description');
            }
            if (Schema::hasColumn('cases', 'incident_date') && !Schema::hasColumn('cases', 'date_occurred')) {
                $table->renameColumn('incident_date', 'date_occurred');
            }

            // Add new columns only if they don't exist
            if (!Schema::hasColumn('cases', 'date_time_type')) {
                $table->string('date_time_type')->default('specific')->after('description');
            }
            if (!Schema::hasColumn('cases', 'date_occurred')) {
                $table->date('date_occurred')->nullable()->after('date_time_type');
            }
            if (!Schema::hasColumn('cases', 'time_occurred')) {
                $table->time('time_occurred')->nullable()->after('date_occurred');
            }
            if (!Schema::hasColumn('cases', 'general_timeframe')) {
                $table->string('general_timeframe')->nullable()->after('time_occurred');
            }
            if (!Schema::hasColumn('cases', 'company_relationship')) {
                $table->string('company_relationship')->default('employee')->after('general_timeframe');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            // Remove new columns
            $table->dropColumn(['date_time_type', 'time_occurred', 'general_timeframe', 'company_relationship']);

            // Rename columns back
            $table->renameColumn('location_description', 'location');
            $table->renameColumn('date_occurred', 'incident_date');

            // Add back removed columns
            $table->uuid('department_id')->nullable()->after('branch_id');
            $table->integer('severity_level')->default(2)->after('priority');

            // Add foreign key constraint back
            $table->foreign('department_id')->references('id')->on('departments');
        });
    }
};
