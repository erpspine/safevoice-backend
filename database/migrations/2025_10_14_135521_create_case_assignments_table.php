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
        Schema::create('case_assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Core relationships
            $table->ulid('case_id');
            $table->ulid('investigator_id');

            // Assignment tracking
            $table->ulid('assigned_by_user_id');
            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();
            $table->ulid('unassigned_by_user_id')->nullable();

            // Assignment details
            $table->text('assignment_note')->nullable();
            $table->enum('assignment_type', ['primary', 'secondary', 'support', 'consultant'])
                ->default('primary');
            $table->integer('priority_level')->default(2); // 1=urgent, 2=normal, 3=low
            $table->decimal('estimated_hours', 8, 2)->nullable();
            $table->timestamp('deadline')->nullable();
            $table->enum('status', ['active', 'completed', 'unassigned', 'transferred'])
                ->default('active');

            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table->foreign('case_id')
                ->references('id')
                ->on('cases')
                ->onDelete('cascade');

            $table->foreign('investigator_id')
                ->references('id')
                ->on('investigators')
                ->onDelete('cascade');

            $table->foreign('assigned_by_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');

            $table->foreign('unassigned_by_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Indexes for performance
            $table->index(['case_id', 'status']);
            $table->index(['investigator_id', 'status']);
            $table->index(['assigned_by_user_id']);
            $table->index(['assigned_at']);
            $table->index(['deadline']);
            $table->index(['priority_level']);
            $table->index(['status']);

            // Composite indexes
            $table->index(['case_id', 'investigator_id', 'status']);
            $table->index(['investigator_id', 'assigned_at']);
            $table->index(['status', 'deadline']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_assignments');
    }
};
