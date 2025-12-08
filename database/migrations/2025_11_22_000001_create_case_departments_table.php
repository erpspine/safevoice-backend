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
        Schema::create('case_departments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('case_id')->constrained('cases')->onDelete('cascade');
            $table->foreignUlid('department_id')->constrained('departments')->onDelete('cascade');
            $table->timestamp('assigned_at')->useCurrent();
            $table->foreignUlid('assigned_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('assignment_note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Prevent duplicate assignments
            $table->unique(['case_id', 'department_id']);

            // Indexes for performance
            $table->index('case_id');
            $table->index('department_id');
            $table->index('assigned_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_departments');
    }
};
