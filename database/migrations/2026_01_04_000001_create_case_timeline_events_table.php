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
        Schema::create('case_timeline_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('case_id')->constrained('cases')->onDelete('cascade');
            $table->foreignUlid('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignUlid('branch_id')->nullable()->constrained('branches')->onDelete('set null');

            // Event type and stage
            $table->string('event_type', 50); // submitted, assigned, investigation_started, escalated, closed, reopened, etc.
            $table->string('stage', 50); // submission, assignment, investigation, resolution, closed
            $table->string('previous_stage', 50)->nullable(); // For tracking stage transitions

            // Actor information (who triggered the event)
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('actor_type', 50)->default('user'); // user, system, scheduler

            // Related entities
            $table->foreignUlid('assigned_to_id')->nullable()->constrained('users')->onDelete('set null'); // For assignment events
            $table->foreignUlid('escalated_to_id')->nullable()->constrained('users')->onDelete('set null'); // For escalation events

            // Duration tracking
            $table->timestamp('event_at'); // When the event occurred
            $table->integer('duration_from_previous')->nullable(); // Duration in minutes from previous event
            $table->integer('duration_in_stage')->nullable(); // Total duration in the current stage (minutes)
            $table->integer('total_case_duration')->nullable(); // Total duration since case creation (minutes)

            // Escalation tracking
            $table->boolean('is_escalation')->default(false);
            $table->integer('escalation_level')->default(0); // 0 = no escalation, 1 = first level, 2 = second level, etc.
            $table->string('escalation_reason')->nullable();
            $table->foreignUlid('escalation_rule_id')->nullable(); // Reference to the rule that triggered escalation

            // SLA tracking
            $table->boolean('sla_breached')->default(false);
            $table->timestamp('sla_deadline')->nullable();
            $table->integer('sla_remaining_minutes')->nullable(); // Minutes remaining before SLA breach

            // Event details
            $table->string('title'); // Human-readable title
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Additional event-specific data
            $table->json('changes')->nullable(); // What changed (for status changes)

            // Visibility
            $table->boolean('is_internal')->default(false); // Internal events not shown to reporters
            $table->boolean('is_visible_to_reporter')->default(true);

            $table->timestamps();

            // Indexes for efficient querying
            $table->index(['case_id', 'event_at']);
            $table->index(['case_id', 'event_type']);
            $table->index(['case_id', 'stage']);
            $table->index(['company_id', 'event_at']);
            $table->index(['branch_id', 'event_at']);
            $table->index(['event_type', 'event_at']);
            $table->index(['is_escalation', 'event_at']);
            $table->index(['sla_breached', 'event_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_timeline_events');
    }
};
