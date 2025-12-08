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
            // Check if column exists before dropping
            if (Schema::hasColumn('cases', 'incident_category_id')) {
                $table->dropColumn('incident_category_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            // Re-add the column if migration is rolled back
            $table->ulid('incident_category_id')->nullable()->after('category_id');
        });
    }
};
