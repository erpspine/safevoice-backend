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
        Schema::create('feedback_categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->onDelete('cascade');
            $table->string('name');
            $table->boolean('status')->default(true);
            $table->text('description')->nullable();
            $table->string('icon')->nullable(); // Icon class/name for UI
            $table->string('color', 7)->nullable(); // Hex color code
            $table->boolean('requires_response')->default(false);
            $table->foreignUlid('auto_notify_department')->nullable()->constrained('departments')->onDelete('set null');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            // Indexes for better performance
            $table->index(['company_id', 'status']);
            $table->index(['status']);
            $table->index(['sort_order']);
            $table->index(['requires_response']);
            $table->index(['auto_notify_department']);
            $table->unique(['company_id', 'name']); // Unique name per company
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback_categories');
    }
};
