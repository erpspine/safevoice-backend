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
        Schema::table('feedback_categories', function (Blueprint $table) {
            // Add parent_id for hierarchical structure
            $table->foreignUlid('parent_id')->nullable()->after('company_id')->constrained('feedback_categories')->onDelete('cascade');

            // Add category_key for template matching
            $table->string('category_key')->nullable()->after('parent_id');

            // Add sort_order if it doesn't exist (it was removed in a previous migration)
            if (!Schema::hasColumn('feedback_categories', 'sort_order')) {
                $table->integer('sort_order')->default(0);
            }

            // Add indexes
            $table->index('parent_id');
            $table->index('category_key');
            $table->index(['company_id', 'category_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feedback_categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['parent_id']);
            $table->dropIndex(['category_key']);
            $table->dropIndex(['company_id', 'category_key']);
            $table->dropColumn(['parent_id', 'category_key']);
        });
    }
};
