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
        Schema::create('incident_categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->onDelete('cascade');
            $table->string('name');
            $table->boolean('status')->default(true);
            $table->text('description')->nullable();
            $table->string('color', 7)->nullable(); // Hex color code
            $table->integer('priority_level')->default(1); // 1=Low, 2=Medium, 3=High, 4=Critical
            $table->foreignUlid('auto_assign_to_department')->nullable()->constrained('departments')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // Indexes for better performance
            $table->index(['company_id', 'status']);
            $table->index(['status']);
            $table->index(['priority_level']);
            $table->index(['auto_assign_to_department']);
            $table->unique(['company_id', 'name']); // Unique name per company
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incident_categories');
    }
};
