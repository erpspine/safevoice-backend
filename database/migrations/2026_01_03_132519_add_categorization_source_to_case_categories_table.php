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
        Schema::table('case_categories', function (Blueprint $table) {
            // Source of categorization: 'user' (reporter), 'company', 'branch', 'investigator', 'admin'
            $table->string('categorization_source')->default('user')->after('category_type');

            // Parent category ID (for hierarchical categorization)
            $table->ulid('parent_category_id')->nullable()->after('category_id');

            // Indicates if this is the primary/main category for the case
            $table->boolean('is_primary')->default(false)->after('categorization_source');

            // Confidence level for categorization (useful for AI/auto-categorization later)
            $table->string('confidence_level')->nullable()->after('is_primary'); // 'high', 'medium', 'low'

            // Flag to indicate if this categorization was reviewed/verified
            $table->boolean('is_verified')->default(false)->after('confidence_level');

            // Who verified the categorization
            $table->ulid('verified_by')->nullable()->after('is_verified');

            // When the categorization was verified
            $table->timestamp('verified_at')->nullable()->after('verified_by');

            // Reason for recategorization (when company/branch changes user's original category)
            $table->text('recategorization_reason')->nullable()->after('assignment_note');

            // Original category ID (to track if category was changed from user's selection)
            $table->ulid('original_category_id')->nullable()->after('recategorization_reason');

            // Indexes for performance
            $table->index('categorization_source');
            $table->index('parent_category_id');
            $table->index('is_primary');
            $table->index('is_verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('case_categories', function (Blueprint $table) {
            $table->dropIndex(['categorization_source']);
            $table->dropIndex(['parent_category_id']);
            $table->dropIndex(['is_primary']);
            $table->dropIndex(['is_verified']);

            $table->dropColumn([
                'categorization_source',
                'parent_category_id',
                'is_primary',
                'confidence_level',
                'is_verified',
                'verified_by',
                'verified_at',
                'recategorization_reason',
                'original_category_id',
            ]);
        });
    }
};
