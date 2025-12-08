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
            // Drop the old foreign key constraint to investigators table
            $table->dropForeign(['investigator_id']);

            // Add new foreign key constraint to users table
            $table->foreign('investigator_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('case_assignments', function (Blueprint $table) {
            // Drop the foreign key constraint to users table
            $table->dropForeign(['investigator_id']);

            // Restore foreign key constraint to investigators table
            $table->foreign('investigator_id')
                ->references('id')
                ->on('investigators')
                ->onDelete('cascade');
        });
    }
};
