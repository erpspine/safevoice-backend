<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaseEscalationRule extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'description',
        'is_active',
        'is_global',
        'priority',
        'stage',
        'applies_to',
        'warning_threshold',
        'escalation_threshold',
        'critical_threshold',
        'use_business_hours',
        'business_hours',
        'exclude_weekends',
        'exclude_holidays',
        'escalation_level',
        'escalation_to_roles',
        'escalation_to_user_id',
        'notify_current_assignee',
        'notify_branch_admin',
        'notify_company_admin',
        'notify_super_admin',
        'notify_emails',
        'auto_reassign',
        'auto_reassign_to_id',
        'auto_change_priority',
        'new_priority',
        'conditions',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_global' => 'boolean',
        'use_business_hours' => 'boolean',
        'exclude_weekends' => 'boolean',
        'exclude_holidays' => 'boolean',
        'notify_current_assignee' => 'boolean',
        'notify_branch_admin' => 'boolean',
        'notify_company_admin' => 'boolean',
        'notify_super_admin' => 'boolean',
        'auto_reassign' => 'boolean',
        'auto_change_priority' => 'boolean',
        'business_hours' => 'array',
        'escalation_to_roles' => 'array',
        'notify_emails' => 'array',
        'conditions' => 'array',
    ];

    // Escalation levels
    public const LEVEL_1 = 'level_1';
    public const LEVEL_2 = 'level_2';
    public const LEVEL_3 = 'level_3';

    // Stages
    public const STAGE_SUBMISSION = 'submission';
    public const STAGE_TRIAGE = 'triage';
    public const STAGE_ASSIGNMENT = 'assignment';
    public const STAGE_INVESTIGATION = 'investigation';
    public const STAGE_RESOLUTION = 'resolution';

    /**
     * Get escalation level options
     */
    public static function getEscalationLevels(): array
    {
        return [
            self::LEVEL_1 => 'Level 1 - Branch Admin',
            self::LEVEL_2 => 'Level 2 - Company Admin',
            self::LEVEL_3 => 'Level 3 - System Admin',
        ];
    }

    /**
     * Get default business hours
     */
    public static function getDefaultBusinessHours(): array
    {
        return [
            'monday' => ['start' => '09:00', 'end' => '17:00'],
            'tuesday' => ['start' => '09:00', 'end' => '17:00'],
            'wednesday' => ['start' => '09:00', 'end' => '17:00'],
            'thursday' => ['start' => '09:00', 'end' => '17:00'],
            'friday' => ['start' => '09:00', 'end' => '17:00'],
            'saturday' => null,
            'sunday' => null,
        ];
    }

    /**
     * Relationships
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function escalationToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalation_to_user_id');
    }

    public function autoReassignTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auto_reassign_to_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(CaseEscalation::class, 'escalation_rule_id');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeGlobal($query)
    {
        return $query->where('is_global', true);
    }

    public function scopeForCompany($query, string $companyId)
    {
        return $query->where(function ($q) use ($companyId) {
            $q->where('company_id', $companyId)
                ->orWhere('is_global', true);
        });
    }

    public function scopeForBranch($query, string $branchId)
    {
        return $query->where(function ($q) use ($branchId) {
            $q->where('branch_id', $branchId)
                ->orWhereNull('branch_id');
        });
    }

    public function scopeForStage($query, string $stage)
    {
        return $query->where('stage', $stage);
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    /**
     * Check if rule applies to a case
     */
    public function appliesTo(CaseModel $case): bool
    {
        // Check case type
        if ($this->applies_to !== 'all' && $this->applies_to !== $case->type) {
            return false;
        }

        // Check company
        if ($this->company_id && $this->company_id !== $case->company_id) {
            return false;
        }

        // Check branch
        if ($this->branch_id && $this->branch_id !== $case->branch_id) {
            return false;
        }

        // Check additional conditions
        if ($this->conditions) {
            foreach ($this->conditions as $field => $allowedValues) {
                $caseValue = $case->{$field};
                if (!in_array($caseValue, (array) $allowedValues)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get formatted threshold
     */
    public function getFormattedEscalationThreshold(): string
    {
        return $this->formatMinutes($this->escalation_threshold);
    }

    public function getFormattedWarningThreshold(): ?string
    {
        return $this->warning_threshold ? $this->formatMinutes($this->warning_threshold) : null;
    }

    public function getFormattedCriticalThreshold(): ?string
    {
        return $this->critical_threshold ? $this->formatMinutes($this->critical_threshold) : null;
    }

    protected function formatMinutes(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' minutes';
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($hours < 24) {
            return $hours . ' hours' . ($remainingMinutes > 0 ? ' ' . $remainingMinutes . ' min' : '');
        }

        $days = floor($hours / 24);
        $remainingHours = $hours % 24;

        return $days . ' days' . ($remainingHours > 0 ? ' ' . $remainingHours . 'h' : '');
    }
}
