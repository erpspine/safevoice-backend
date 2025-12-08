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
        Schema::create('attachments', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Core relationships
            $table->ulid('case_id');
            $table->ulid('message_id')->nullable(); // Optional link to specific message

            // File information (as requested)
            $table->string('file_url'); // Public accessible URL
            $table->string('file_path'); // Internal storage path
            $table->string('file_name'); // Generated filename
            $table->string('original_name'); // User's original filename
            $table->string('file_hash'); // Hash for duplicate detection
            $table->string('mime_type'); // MIME type (was 'mime' in request)
            $table->bigInteger('file_size'); // File size in bytes (was 'size' in request)
            $table->enum('visibility', ['public', 'internal', 'restricted'])
                ->default('public');
            $table->ulid('uploaded_by'); // User who uploaded (was 'uploaded_by' in request)

            // Additional metadata
            $table->text('description')->nullable();
            $table->boolean('is_image')->default(false);
            $table->boolean('is_document')->default(false);

            // Security and scanning
            $table->enum('scan_status', ['pending', 'clean', 'infected', 'error'])
                ->default('pending');
            $table->json('scan_result')->nullable();

            // Usage tracking
            $table->integer('download_count')->default(0);
            $table->json('metadata')->nullable(); // Additional file metadata

            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table->foreign('case_id')
                ->references('id')
                ->on('cases')
                ->onDelete('cascade');

            $table->foreign('message_id')
                ->references('id')
                ->on('case_messages')
                ->onDelete('set null');

            $table->foreign('uploaded_by')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');

            // Indexes for performance
            $table->index(['case_id', 'visibility']);
            $table->index(['case_id', 'created_at']);
            $table->index(['message_id']);
            $table->index(['uploaded_by']);
            $table->index(['file_hash']); // For duplicate detection
            $table->index(['mime_type']);
            $table->index(['visibility']);
            $table->index(['scan_status']);
            $table->index(['is_image']);
            $table->index(['is_document']);
            $table->index(['created_at']);

            // Composite indexes
            $table->index(['case_id', 'visibility', 'scan_status']);
            $table->index(['case_id', 'mime_type']);
            $table->index(['uploaded_by', 'created_at']);
            $table->index(['file_hash', 'file_size']); // For duplicate detection
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
