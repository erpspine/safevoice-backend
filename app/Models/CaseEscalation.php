<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseEscalation extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'case_id',
        'escalation_rule_id',
        'timeline_event_id',
        'stage',
        'escalation_level',
        'reason',
        'overdue_minutes',
        'notified_users',
        'notified_emails',
        'is_resolved',
        'resolved_at',
        'resolved_by',
        'resolution_note',
        'was_reassigned',
        'reassigned_to_id',
        'priority_changed',
        'old_priority',
        'new_priority',
    ];

    protected $casts = [
        'notified_users' => 'array',
        'notified_emails' => 'array',
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
        'was_reassigned' => 'boolean',
        'priority_changed' => 'boolean',
    ];

    /**
     * Relationships
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    public function escalationRule(): BelongsTo
    {
        return $this->belongsTo(CaseEscalationRule::class, 'escalation_rule_id');
    }

    public function timelineEvent(): BelongsTo
    {
        return $this->belongsTo(CaseTimelineEvent::class, 'timeline_event_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function reassignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reassigned_to_id');
    }

    /**
     * Scopes
     */
    public function scopeForCase($query, string $caseId)
    {
        return $query->where('case_id', $caseId);
    }

    public function scopeUnresolved($query)
    {
        return $query->where('is_resolved', false);
    }

    public function scopeResolved($query)
    {
        return $query->where('is_resolved', true);
    }

    public function scopeOfLevel($query, string $level)
    {
        return $query->where('escalation_level', $level);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Mark as resolved
     */
    public function resolve(string $userId, ?string $note = null): self
    {
        $this->update([
            'is_resolved' => true,
            'resolved_at' => now(),
            'resolved_by' => $userId,
            'resolution_note' => $note,
        ]);

        return $this;
    }

    /**
     * Get formatted overdue duration
     */
    public function getFormattedOverdueDuration(): string
    {
        $minutes = $this->overdue_minutes;

        if ($minutes < 60) {
            return $minutes . ' minutes';
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($hours < 24) {
            return $hours . 'h ' . $remainingMinutes . 'm overdue';
        }

        $days = floor($hours / 24);
        $remainingHours = $hours % 24;

        return $days . 'd ' . $remainingHours . 'h overdue';
    }

    /**
     * Get escalation level label
     */
    public function getLevelLabel(): string
    {
        return CaseEscalationRule::getEscalationLevels()[$this->escalation_level] ?? $this->escalation_level;
    }
}
