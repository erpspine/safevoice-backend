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
        Schema::table('message_reads', function (Blueprint $table) {
            // Remove the incorrect unique constraint
            $table->dropUnique('uq_mr_unique');
        });

        // Add correct composite unique constraint to prevent duplicate reads per user per message
        Schema::table('message_reads', function (Blueprint $table) {
            $table->unique(['message_id', 'user_id'], 'uq_message_user_read');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('message_reads', function (Blueprint $table) {
            // Remove the new constraint
            $table->dropUnique('uq_message_user_read');
        });

        Schema::table('message_reads', function (Blueprint $table) {
            // Restore the original constraint
            $table->unique('message_id', 'uq_mr_unique');
        });
    }
};
