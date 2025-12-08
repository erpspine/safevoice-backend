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
        Schema::create('routing_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Required relationships
            $table->ulid('company_id');

            // Routing rule configuration
            $table->enum('type', [
                'incident',
                'feedback',
                'general',
                'department',
                'branch',
                'category'
            ]);

            // Optional filtering criteria (nullable as requested)
            $table->ulid('category_id')->nullable(); // Can reference incident or feedback categories
            $table->ulid('department_id')->nullable();
            $table->ulid('branch_id')->nullable();

            // Recipients configuration (as requested)
            $table->json('recipients_json'); // Array of recipient IDs

            // Additional metadata
            $table->string('name')->nullable(); // Friendly name for the rule
            $table->text('description')->nullable(); // Rule description
            $table->boolean('is_active')->default(true); // Enable/disable rule
            $table->integer('priority')->default(0); // Rule priority (higher numbers = higher priority)

            // Additional conditions
            $table->json('conditions_json')->nullable(); // Additional matching conditions
            $table->json('metadata')->nullable(); // Extra rule metadata

            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('cascade');

            $table->foreign('department_id')
                ->references('id')
                ->on('departments')
                ->onDelete('cascade');

            $table->foreign('branch_id')
                ->references('id')
                ->on('branches')
                ->onDelete('cascade');

            // Note: category_id can reference either incident_categories or feedback_categories
            // We'll handle this with application logic rather than foreign key constraints
            // since it's polymorphic

            // Indexes for performance
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'type']);
            $table->index(['company_id', 'type', 'is_active']);
            $table->index(['type']);
            $table->index(['category_id']);
            $table->index(['department_id']);
            $table->index(['branch_id']);
            $table->index(['is_active']);
            $table->index(['priority']);
            $table->index(['created_at']);

            // Composite indexes for common routing queries
            $table->index(['company_id', 'type', 'category_id']);
            $table->index(['company_id', 'type', 'department_id']);
            $table->index(['company_id', 'type', 'branch_id']);
            $table->index(['company_id', 'category_id', 'department_id']);
            $table->index(['company_id', 'branch_id', 'department_id']);
            $table->index(['type', 'is_active', 'priority']);

            // Complex routing index
            $table->index([
                'company_id',
                'type',
                'category_id',
                'department_id',
                'branch_id',
                'is_active'
            ], 'routing_rules_full_match_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routing_rules');
    }
};
