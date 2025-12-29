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
            // Drop the existing foreign key constraint that only points to incident_categories
            $table->dropForeign(['category_id']);

            // Remove the default value constraint and make it more flexible
            $table->string('category_type')->default(null)->change();
        });

        // Note: We're not adding back foreign key constraints because the category_id
        // can reference either incident_categories or feedback_categories based on category_type.
        // This is handled at the application level through the polymorphic relationship.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('case_categories', function (Blueprint $table) {
            // Restore the original foreign key constraint
            $table->foreignUlid('category_id')->constrained('incident_categories')->onDelete('cascade');

            // Restore the default value
            $table->string('category_type')->default('incident')->change();
        });
    }
};
