<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseTimelineEvent extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'case_id',
        'company_id',
        'branch_id',
        'event_type',
        'stage',
        'previous_stage',
        'actor_id',
        'actor_type',
        'assigned_to_id',
        'escalated_to_id',
        'event_at',
        'duration_from_previous',
        'duration_in_stage',
        'total_case_duration',
        'is_escalation',
        'escalation_level',
        'escalation_reason',
        'escalation_rule_id',
        'sla_breached',
        'sla_deadline',
        'sla_remaining_minutes',
        'title',
        'description',
        'metadata',
        'changes',
        'is_internal',
        'is_visible_to_reporter',
    ];

    protected $casts = [
        'event_at' => 'datetime',
        'sla_deadline' => 'datetime',
        'metadata' => 'array',
        'changes' => 'array',
        'is_escalation' => 'boolean',
        'sla_breached' => 'boolean',
        'is_internal' => 'boolean',
        'is_visible_to_reporter' => 'boolean',
    ];

    // Event types
    public const EVENT_SUBMITTED = 'submitted';
    public const EVENT_ACKNOWLEDGED = 'acknowledged';
    public const EVENT_ASSIGNED = 'assigned';
    public const EVENT_REASSIGNED = 'reassigned';
    public const EVENT_UNASSIGNED = 'unassigned';
    public const EVENT_INVESTIGATION_STARTED = 'investigation_started';
    public const EVENT_INVESTIGATION_UPDATED = 'investigation_updated';
    public const EVENT_EVIDENCE_ADDED = 'evidence_added';
    public const EVENT_INTERVIEW_SCHEDULED = 'interview_scheduled';
    public const EVENT_INTERVIEW_COMPLETED = 'interview_completed';
    public const EVENT_ESCALATED = 'escalated';
    public const EVENT_PRIORITY_CHANGED = 'priority_changed';
    public const EVENT_STATUS_CHANGED = 'status_changed';
    public const EVENT_RESOLVED = 'resolved';
    public const EVENT_CLOSED = 'closed';
    public const EVENT_REOPENED = 'reopened';
    public const EVENT_COMMENT_ADDED = 'comment_added';
    public const EVENT_SLA_WARNING = 'sla_warning';
    public const EVENT_SLA_BREACHED = 'sla_breached';

    // Stages
    public const STAGE_SUBMISSION = 'submission';
    public const STAGE_TRIAGE = 'triage';
    public const STAGE_ASSIGNMENT = 'assignment';
    public const STAGE_INVESTIGATION = 'investigation';
    public const STAGE_RESOLUTION = 'resolution';
    public const STAGE_CLOSED = 'closed';

    // Actor types
    public const ACTOR_USER = 'user';
    public const ACTOR_SYSTEM = 'system';
    public const ACTOR_SCHEDULER = 'scheduler';
    public const ACTOR_REPORTER = 'reporter';

    /**
     * Get all available event types
     */
    public static function getEventTypes(): array
    {
        return [
            self::EVENT_SUBMITTED => 'Case Submitted',
            self::EVENT_ACKNOWLEDGED => 'Case Acknowledged',
            self::EVENT_ASSIGNED => 'Assigned to Investigator',
            self::EVENT_REASSIGNED => 'Reassigned',
            self::EVENT_UNASSIGNED => 'Investigator Removed',
            self::EVENT_INVESTIGATION_STARTED => 'Investigation Started',
            self::EVENT_INVESTIGATION_UPDATED => 'Investigation Updated',
            self::EVENT_EVIDENCE_ADDED => 'Evidence Added',
            self::EVENT_INTERVIEW_SCHEDULED => 'Interview Scheduled',
            self::EVENT_INTERVIEW_COMPLETED => 'Interview Completed',
            self::EVENT_ESCALATED => 'Case Escalated',
            self::EVENT_PRIORITY_CHANGED => 'Priority Changed',
            self::EVENT_STATUS_CHANGED => 'Status Changed',
            self::EVENT_RESOLVED => 'Case Resolved',
            self::EVENT_CLOSED => 'Case Closed',
            self::EVENT_REOPENED => 'Case Reopened',
            self::EVENT_COMMENT_ADDED => 'Comment Added',
            self::EVENT_SLA_WARNING => 'SLA Warning',
            self::EVENT_SLA_BREACHED => 'SLA Breached',
        ];
    }

    /**
     * Get all available stages
     */
    public static function getStages(): array
    {
        return [
            self::STAGE_SUBMISSION => 'Submission',
            self::STAGE_TRIAGE => 'Triage',
            self::STAGE_ASSIGNMENT => 'Assignment',
            self::STAGE_INVESTIGATION => 'Investigation',
            self::STAGE_RESOLUTION => 'Resolution',
            self::STAGE_CLOSED => 'Closed',
        ];
    }

    /**
     * Relationships
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function escalatedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_to_id');
    }

    public function escalationRule(): BelongsTo
    {
        return $this->belongsTo(CaseEscalationRule::class, 'escalation_rule_id');
    }

    /**
     * Scopes
     */
    public function scopeForCase($query, string $caseId)
    {
        return $query->where('case_id', $caseId);
    }

    public function scopeForCompany($query, string $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForBranch($query, string $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeOfType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public function scopeInStage($query, string $stage)
    {
        return $query->where('stage', $stage);
    }

    public function scopeEscalations($query)
    {
        return $query->where('is_escalation', true);
    }

    public function scopeSlaBreached($query)
    {
        return $query->where('sla_breached', true);
    }

    public function scopeVisibleToReporter($query)
    {
        return $query->where('is_visible_to_reporter', true);
    }

    public function scopeInternal($query)
    {
        return $query->where('is_internal', true);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('event_at', '>=', now()->subDays($days));
    }

    /**
     * Helper methods
     */
    public function getFormattedDuration(): string
    {
        if (!$this->duration_from_previous) {
            return '-';
        }

        return $this->formatMinutes($this->duration_from_previous);
    }

    public function getFormattedTotalDuration(): string
    {
        if (!$this->total_case_duration) {
            return '-';
        }

        return $this->formatMinutes($this->total_case_duration);
    }

    public function getFormattedStageDuration(): string
    {
        if (!$this->duration_in_stage) {
            return '-';
        }

        return $this->formatMinutes($this->duration_in_stage);
    }

    protected function formatMinutes(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' min';
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($hours < 24) {
            return $hours . 'h ' . $remainingMinutes . 'm';
        }

        $days = floor($hours / 24);
        $remainingHours = $hours % 24;

        return $days . 'd ' . $remainingHours . 'h ' . $remainingMinutes . 'm';
    }

    public function getEventLabel(): string
    {
        return self::getEventTypes()[$this->event_type] ?? $this->event_type;
    }

    public function getStageLabel(): string
    {
        return self::getStages()[$this->stage] ?? $this->stage;
    }

    public function isOverdue(): bool
    {
        return $this->sla_breached || ($this->sla_deadline && now()->isAfter($this->sla_deadline));
    }
}
