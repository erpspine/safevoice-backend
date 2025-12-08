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
            // Remove columns
            $table->dropColumn(['department_id', 'severity_level']);

            // Rename existing columns
            $table->renameColumn('location', 'location_description');
            $table->renameColumn('incident_date', 'date_occurred');

            // Add new columns
            $table->string('date_time_type')->default('specific')->after('description');
            $table->time('time_occurred')->nullable()->after('date_occurred');
            $table->string('general_timeframe')->nullable()->after('time_occurred');
            $table->string('company_relationship')->default('employee')->after('general_timeframe');
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
