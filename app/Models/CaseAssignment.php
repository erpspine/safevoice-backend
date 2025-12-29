<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaseAssignment extends BaseModel
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'case_id',
        'investigator_id',
        'assigned_by',
        'assigned_at',
        'unassigned_at',
        'unassigned_by',
        'assignment_note',
        'assignment_type',
        'priority_level',
        'estimated_hours',
        'deadline',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'assigned_at' => 'datetime',
        'unassigned_at' => 'datetime',
        'deadline' => 'datetime',
        'estimated_hours' => 'decimal:2',
        'priority_level' => 'integer',
    ];

    /**
     * Get the case that this assignment belongs to.
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    /**
     * Get the investigator assigned to the case.
     */
    public function investigator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investigator_id');
    }

    /**
     * Get the user who made the assignment.
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Get the user who unassigned the investigator.
     */
    public function unassignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unassigned_by');
    }

    /**
     * Scope a query to only include active assignments.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->whereNull('unassigned_at');
    }

    /**
     * Scope a query to filter by case.
     */
    public function scopeForCase($query, $caseId)
    {
        return $query->where('case_id', $caseId);
    }

    /**
     * Scope a query to filter by investigator.
     */
    public function scopeForInvestigator($query, $investigatorId)
    {
        return $query->where('investigator_id', $investigatorId);
    }

    /**
     * Scope a query to get assignments by priority.
     */
    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority_level', $priority);
    }

    /**
     * Scope a query to get overdue assignments.
     */
    public function scopeOverdue($query)
    {
        return $query->where('deadline', '<', now())
            ->where('status', 'active');
    }

    /**
     * Check if the assignment is currently active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && $this->unassigned_at === null;
    }

    /**
     * Check if the assignment is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->deadline &&
            $this->deadline->isPast() &&
            $this->isActive();
    }

    /**
     * Get the assignment duration in hours.
     */
    public function getDurationAttribute(): ?float
    {
        if (!$this->assigned_at) {
            return null;
        }

        $endTime = $this->unassigned_at ?: now();
        return $this->assigned_at->diffInHours($endTime, true);
    }

    /**
     * Mark assignment as completed.
     */
    public function complete(User $user, string $note = null): bool
    {
        return $this->update([
            'status' => 'completed',
            'unassigned_at' => now(),
            'unassigned_by' => $user->id,
            'assignment_note' => $note ? $this->assignment_note . "\n" . $note : $this->assignment_note,
        ]);
    }

    /**
     * Unassign the investigator.
     */
    public function unassign(User $user, string $reason = null): bool
    {
        return $this->update([
            'status' => 'unassigned',
            'unassigned_at' => now(),
            'unassigned_by' => $user->id,
            'assignment_note' => $reason ? $this->assignment_note . "\nUnassigned: " . $reason : $this->assignment_note,
        ]);
    }
}
