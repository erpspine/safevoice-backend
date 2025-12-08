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
        Schema::table('thread_participants', function (Blueprint $table) {
            // Remove the unique constraint to allow multiple participants per thread
            $table->dropUnique('uq_tp_unique');
        });

        // Add a new composite unique constraint to prevent duplicate user per thread
        Schema::table('thread_participants', function (Blueprint $table) {
            $table->unique(['thread_id', 'user_id'], 'uq_thread_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('thread_participants', function (Blueprint $table) {
            // Remove the new constraint
            $table->dropUnique('uq_thread_user');
        });

        Schema::table('thread_participants', function (Blueprint $table) {
            // Restore the original constraint
            $table->unique('thread_id', 'uq_tp_unique');
        });
    }
};
