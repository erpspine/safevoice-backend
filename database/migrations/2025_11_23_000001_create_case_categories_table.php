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
        Schema::create('case_categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('case_id')->constrained('cases')->onDelete('cascade');
            $table->foreignUlid('category_id')->constrained('incident_categories')->onDelete('cascade');
            $table->string('category_type')->default('incident'); // 'incident' or 'feedback'
            $table->timestamp('assigned_at')->useCurrent();
            $table->foreignUlid('assigned_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('assignment_note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Prevent duplicate category assignments
            $table->unique(['case_id', 'category_id', 'category_type']);

            // Indexes for performance
            $table->index('case_id');
            $table->index('category_id');
            $table->index('category_type');
            $table->index('assigned_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_categories');
    }
};
