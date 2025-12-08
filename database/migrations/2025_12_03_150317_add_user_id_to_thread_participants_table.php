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
        Schema::table('thread_participants', function (Blueprint $table) {
            // Add user_id as nullable first
            $table->ulid('user_id')->nullable()->after('thread_id');
            $table->timestamp('joined_at')->nullable()->after('last_read_at');
        });

        // Update existing records - use investigator_id as user_id if available
        DB::statement("UPDATE thread_participants SET user_id = investigator_id WHERE user_id IS NULL AND investigator_id IS NOT NULL");

        // Delete records where we can't determine the user_id
        DB::statement("DELETE FROM thread_participants WHERE user_id IS NULL");

        // Now make user_id required and add foreign key
        Schema::table('thread_participants', function (Blueprint $table) {
            $table->ulid('user_id')->nullable(false)->change();

            // Add foreign key for user_id
            $table->foreign('user_id')
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
        Schema::table('thread_participants', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'joined_at']);
        });
    }
};
