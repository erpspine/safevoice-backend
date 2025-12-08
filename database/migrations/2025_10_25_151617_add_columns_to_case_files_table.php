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
            $table->ulid('case_id');
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('file_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->enum('file_type', ['evidence', 'document', 'image', 'video', 'audio', 'other'])->default('document');
            $table->text('description')->nullable();
            $table->string('uploaded_by_type')->nullable(); // 'admin', 'user', 'investigator'
            $table->ulid('uploaded_by_id')->nullable();
            $table->boolean('is_confidential')->default(false);
            $table->enum('processing_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->json('metadata')->nullable(); // For additional file information

            // Foreign key constraint
            $table->foreign('case_id')->references('id')->on('cases')->onDelete('cascade');

            // Indexes
            $table->index('case_id');
            $table->index('file_type');
            $table->index('processing_status');
            $table->index(['uploaded_by_type', 'uploaded_by_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('case_files', function (Blueprint $table) {
            $table->dropForeign(['case_id']);
            $table->dropIndex(['case_id']);
            $table->dropIndex(['file_type']);
            $table->dropIndex(['processing_status']);
            $table->dropIndex(['uploaded_by_type', 'uploaded_by_id']);

            $table->dropColumn([
                'case_id',
                'original_name',
                'stored_name',
                'file_path',
                'mime_type',
                'file_size',
                'file_type',
                'description',
                'uploaded_by_type',
                'uploaded_by_id',
                'is_confidential',
                'processing_status',
                'metadata'
            ]);
        });
    }
};
