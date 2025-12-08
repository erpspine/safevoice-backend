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
        Schema::table('case_messages', function (Blueprint $table) {
            // Drop foreign key constraint for parent_message_id first
            $table->dropForeign(['parent_message_id']);

            // Drop columns
            $table->dropColumn([
                'visibility',
                'attachments',
                'message_type',
                'priority',
                'is_read',
                'read_at',
                'read_by_user_id',
                'parent_message_id',
                'metadata'
            ]);

            // Add thread_id column
            $table->ulid('thread_id')->after('case_id');

            // Add foreign key for thread_id
            $table->foreign('thread_id')
                ->references('id')
                ->on('threads')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('case_messages', function (Blueprint $table) {
            // Drop thread_id foreign key and column
            $table->dropForeign(['thread_id']);
            $table->dropColumn('thread_id');

            // Restore removed columns
            $table->enum('visibility', ['public', 'internal', 'private'])->default('public');
            $table->json('attachments')->nullable();
            $table->enum('message_type', ['user', 'system', 'status_change'])->default('user');
            $table->enum('priority', ['low', 'normal', 'high'])->default('normal');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->ulid('read_by_user_id')->nullable();
            $table->ulid('parent_message_id')->nullable();
            $table->json('metadata')->nullable();

            // Restore foreign key for parent_message_id
            $table->foreign('parent_message_id')
                ->references('id')
                ->on('case_messages')
                ->onDelete('set null');
        });
    }
};
