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
        // Drop existing basic table and recreate with full structure
        Schema::dropIfExists('notifications');

        Schema::create('notifications', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Core relationships (as requested)
            $table->ulid('branch_id');
            $table->ulid('case_id');

            // Optional recipient relationships
            $table->ulid('recipient_id')->nullable(); // Link to Recipients table
            $table->ulid('user_id')->nullable(); // Link to Users table

            // Notification details (as requested)
            $table->enum('channel', [
                'email',
                'sms',
                'whatsapp',
                'push',
                'slack',
                'teams',
                'webhook'
            ]); // Notification channel
            $table->json('payload_json'); // Notification payload
            $table->enum('status', [
                'pending',
                'sent',
                'delivered',
                'read',
                'failed'
            ])->default('pending');
            $table->timestamp('sent_at')->nullable(); // When sent (as requested)

            // Additional tracking timestamps
            $table->timestamp('delivered_at')->nullable(); // When delivered
            $table->timestamp('read_at')->nullable(); // When read/opened
            $table->timestamp('failed_at')->nullable(); // When failed

            // Error handling and retries
            $table->integer('retry_count')->default(0);
            $table->text('error_message')->nullable();

            // Notification metadata
            $table->enum('notification_type', [
                'case_created',
                'case_updated',
                'case_assigned',
                'case_closed',
                'message_received',
                'deadline_approaching',
                'system_alert',
                'custom'
            ])->default('case_updated');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])
                ->default('normal');
            $table->string('template_name')->nullable(); // Email/SMS template used
            $table->string('subject')->nullable(); // Email subject or notification title
            $table->text('message_preview')->nullable(); // Short preview of content
            $table->json('metadata')->nullable(); // Additional notification data

            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table->foreign('branch_id')
                ->references('id')
                ->on('branches')
                ->onDelete('cascade');

            $table->foreign('case_id')
                ->references('id')
                ->on('cases')
                ->onDelete('cascade');

            $table->foreign('recipient_id')
                ->references('id')
                ->on('recipients')
                ->onDelete('set null');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Indexes for performance
            $table->index(['branch_id', 'status']);
            $table->index(['case_id', 'channel']);
            $table->index(['case_id', 'status']);
            $table->index(['recipient_id']);
            $table->index(['user_id']);
            $table->index(['channel']);
            $table->index(['status']);
            $table->index(['notification_type']);
            $table->index(['priority']);
            $table->index(['sent_at']);
            $table->index(['created_at']);
            $table->index(['retry_count']);

            // Composite indexes for common queries
            $table->index(['branch_id', 'channel', 'status']);
            $table->index(['case_id', 'channel', 'status']);
            $table->index(['status', 'retry_count']);
            $table->index(['channel', 'priority', 'status']);
            $table->index(['notification_type', 'status']);
            $table->index(['created_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
