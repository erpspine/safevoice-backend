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
        Schema::create('case_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Core relationships
            $table->ulid('case_id');

            // Polymorphic sender (User, Investigator, or system)
            $table->ulid('sender_id')->nullable(); // Null for system messages
            $table->string('sender_type'); // 'reporter', 'investigator', 'system'

            // Message content and visibility
            $table->enum('visibility', ['public', 'internal'])
                ->default('public');
            $table->text('message');
            $table->boolean('has_attachments')->default(false);
            $table->json('attachments')->nullable();

            // Message metadata
            $table->enum('message_type', [
                'comment',
                'update',
                'attachment',
                'status_change',
                'assignment',
                'notification',
                'system'
            ])->default('comment');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])
                ->default('normal');

            // Read tracking
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->ulid('read_by_user_id')->nullable();

            // Threading support
            $table->ulid('parent_message_id')->nullable(); // For replies

            // Additional metadata
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table->foreign('case_id')
                ->references('id')
                ->on('cases')
                ->onDelete('cascade');

            $table->foreign('read_by_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Note: Self-referencing foreign key will be added after table creation
            // to avoid constraint issues during migration

            // Indexes for performance
            $table->index(['case_id', 'created_at']);
            $table->index(['case_id', 'visibility']);
            $table->index(['case_id', 'sender_type']);
            $table->index(['sender_id', 'sender_type']);
            $table->index(['visibility']);
            $table->index(['sender_type']);
            $table->index(['message_type']);
            $table->index(['priority']);
            $table->index(['is_read']);
            $table->index(['created_at']);
            $table->index(['parent_message_id']);

            // Composite indexes
            $table->index(['case_id', 'visibility', 'created_at']);
            $table->index(['case_id', 'sender_type', 'visibility']);
            $table->index(['is_read', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_messages');
    }
};
