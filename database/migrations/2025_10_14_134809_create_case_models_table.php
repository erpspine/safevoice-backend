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
        Schema::create('cases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignUlid('branch_id')->nullable()->constrained('branches')->onDelete('set null');
            $table->foreignUlid('department_id')->nullable()->constrained('departments')->onDelete('set null');
            $table->enum('type', ['incident', 'feedback']);
            $table->ulid('category_id'); // Will reference incident_categories or feedback_categories
            $table->enum('status', ['open', 'in_progress', 'pending', 'resolved', 'closed'])->default('open');
            $table->text('note')->nullable();
            $table->json('attachment')->nullable(); // Array of file paths/URLs
            $table->integer('priority')->default(1); // 1=Low, 2=Medium, 3=High, 4=Critical
            $table->string('source')->nullable(); // web, mobile, email, phone, etc.
            $table->enum('created_by_type', ['anonymous', 'identified'])->default('anonymous');
            $table->json('created_by_contact_json')->nullable();
            $table->string('case_token')->unique();

            // Additional useful fields
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->foreignUlid('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->date('due_date')->nullable();
            $table->datetime('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->integer('severity_level')->default(1); // 1=Low, 2=Medium, 3=High, 4=Critical
            $table->string('location')->nullable();
            $table->json('witness_info')->nullable();
            $table->boolean('follow_up_required')->default(false);

            $table->timestamps();
            $table->softDeletes();

            // Indexes for better performance
            $table->index(['company_id', 'type', 'status']);
            $table->index(['type', 'category_id']);
            $table->index(['status']);
            $table->index(['priority']);
            $table->index(['assigned_to']);
            $table->index(['due_date']);
            $table->index(['created_by_type']);
            $table->index(['case_token']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cases');
    }
};
