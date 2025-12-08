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
        Schema::table('case_files', function (Blueprint $table) {
            // Drop the foreign key constraint for case_id first
            $table->dropForeign(['case_id']);

            // Drop case_id column
            $table->dropColumn('case_id');

            // Add case_message_id column
            $table->ulid('case_message_id')->after('id');

            // Add foreign key for case_message_id
            $table->foreign('case_message_id')
                ->references('id')
                ->on('case_messages')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('case_files', function (Blueprint $table) {
            // Drop case_message_id foreign key and column
            $table->dropForeign(['case_message_id']);
            $table->dropColumn('case_message_id');

            // Restore case_id column
            $table->ulid('case_id')->after('id');

            // Restore foreign key for case_id
            $table->foreign('case_id')
                ->references('id')
                ->on('cases')
                ->onDelete('cascade');
        });
    }
};
