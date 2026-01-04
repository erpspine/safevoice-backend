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
        Schema::create('case_escalation_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->nullable()->constrained('companies')->onDelete('cascade');
            $table->foreignUlid('branch_id')->nullable()->constrained('branches')->onDelete('cascade');

            // Rule identification
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_global')->default(false); // If true, applies to all companies
            $table->integer('priority')->default(0); // Higher priority rules are evaluated first

            // Stage and event targeting
            $table->string('stage', 50); // submission, assignment, investigation, resolution
            $table->string('applies_to', 50)->default('all'); // all, incident, feedback

            // Timing thresholds (in minutes)
            $table->integer('warning_threshold')->nullable(); // Send warning before escalation
            $table->integer('escalation_threshold'); // Escalate after this many minutes
            $table->integer('critical_threshold')->nullable(); // Mark as critical after this many minutes

            // Business hours consideration
            $table->boolean('use_business_hours')->default(true);
            $table->json('business_hours')->nullable(); // {"monday": {"start": "09:00", "end": "17:00"}, ...}
            $table->boolean('exclude_weekends')->default(true);
            $table->boolean('exclude_holidays')->default(true);

            // Escalation actions
            $table->string('escalation_level', 20)->default('level_1'); // level_1, level_2, level_3
            $table->json('escalation_to_roles')->nullable(); // ["company_admin", "super_admin"]
            $table->foreignUlid('escalation_to_user_id')->nullable()->constrained('users')->onDelete('set null');

            // Notification settings
            $table->boolean('notify_current_assignee')->default(true);
            $table->boolean('notify_branch_admin')->default(true);
            $table->boolean('notify_company_admin')->default(false);
            $table->boolean('notify_super_admin')->default(false);
            $table->json('notify_emails')->nullable(); // Additional emails to notify

            // Auto-actions
            $table->boolean('auto_reassign')->default(false);
            $table->foreignUlid('auto_reassign_to_id')->nullable()->constrained('users')->onDelete('set null');
            $table->boolean('auto_change_priority')->default(false);
            $table->string('new_priority', 20)->nullable(); // high, urgent

            // Conditions (when to apply this rule)
            $table->json('conditions')->nullable(); // {"case_type": "incident", "priority": ["high", "urgent"]}

            // Audit
            $table->foreignUlid('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['company_id', 'is_active']);
            $table->index(['branch_id', 'is_active']);
            $table->index(['stage', 'is_active']);
            $table->index(['is_global', 'is_active']);
            $table->index('priority');
        });

        // Table to track individual case escalations
        Schema::create('case_escalations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('case_id')->constrained('cases')->onDelete('cascade');
            $table->foreignUlid('escalation_rule_id')->nullable()->constrained('case_escalation_rules')->onDelete('set null');
            $table->foreignUlid('timeline_event_id')->nullable(); // Reference to the timeline event

            // Escalation details
            $table->string('stage', 50); // Stage when escalation occurred
            $table->string('escalation_level', 20); // level_1, level_2, level_3
            $table->string('reason');
            $table->integer('overdue_minutes'); // How many minutes overdue when escalated

            // Who was notified
            $table->json('notified_users')->nullable(); // Array of user IDs notified
            $table->json('notified_emails')->nullable(); // Array of emails notified

            // Resolution
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->foreignUlid('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('resolution_note')->nullable();

            // Actions taken
            $table->boolean('was_reassigned')->default(false);
            $table->foreignUlid('reassigned_to_id')->nullable()->constrained('users')->onDelete('set null');
            $table->boolean('priority_changed')->default(false);
            $table->string('old_priority', 20)->nullable();
            $table->string('new_priority', 20)->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['case_id', 'created_at']);
            $table->index(['case_id', 'is_resolved']);
            $table->index(['escalation_level', 'is_resolved']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_escalations');
        Schema::dropIfExists('case_escalation_rules');
    }
};
