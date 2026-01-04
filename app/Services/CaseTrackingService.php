<?php

namespace App\Services;

use App\Models\CaseModel;
use App\Models\CaseTimelineEvent;
use App\Models\CaseEscalation;
use App\Models\CaseEscalationRule;
use App\Models\User;
use App\Mail\CaseEscalationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class CaseTrackingService
{
    /**
     * Log a timeline event for a case
     */
    public function logEvent(
        CaseModel $case,
        string $eventType,
        string $stage,
        array $options = []
    ): CaseTimelineEvent {
        $previousEvent = $this->getLastEvent($case);
        $previousStage = $previousEvent?->stage;

        // Calculate durations
        $durationFromPrevious = null;
        $durationInStage = null;
        $totalCaseDuration = null;

        if ($previousEvent) {
            $durationFromPrevious = $previousEvent->event_at->diffInMinutes(now());
            $totalCaseDuration = $case->created_at->diffInMinutes(now());

            // Calculate duration in current stage
            $firstEventInStage = $this->getFirstEventInStage($case, $stage);
            if ($firstEventInStage) {
                $durationInStage = $firstEventInStage->event_at->diffInMinutes(now());
            }
        }

        // Get SLA info if applicable
        $slaInfo = $this->calculateSlaInfo($case, $stage);

        $event = CaseTimelineEvent::create([
            'case_id' => $case->id,
            'company_id' => $case->company_id,
            'branch_id' => $case->branch_id,
            'event_type' => $eventType,
            'stage' => $stage,
            'previous_stage' => $previousStage !== $stage ? $previousStage : null,
            'actor_id' => $options['actor_id'] ?? null,
            'actor_type' => $options['actor_type'] ?? CaseTimelineEvent::ACTOR_USER,
            'assigned_to_id' => $options['assigned_to_id'] ?? null,
            'escalated_to_id' => $options['escalated_to_id'] ?? null,
            'event_at' => $options['event_at'] ?? now(),
            'duration_from_previous' => $durationFromPrevious,
            'duration_in_stage' => $durationInStage,
            'total_case_duration' => $totalCaseDuration,
            'is_escalation' => $options['is_escalation'] ?? false,
            'escalation_level' => $options['escalation_level'] ?? 0,
            'escalation_reason' => $options['escalation_reason'] ?? null,
            'escalation_rule_id' => $options['escalation_rule_id'] ?? null,
            'sla_breached' => $slaInfo['breached'] ?? false,
            'sla_deadline' => $slaInfo['deadline'] ?? null,
            'sla_remaining_minutes' => $slaInfo['remaining_minutes'] ?? null,
            'title' => $options['title'] ?? CaseTimelineEvent::getEventTypes()[$eventType] ?? $eventType,
            'description' => $options['description'] ?? null,
            'metadata' => $options['metadata'] ?? null,
            'changes' => $options['changes'] ?? null,
            'is_internal' => $options['is_internal'] ?? false,
            'is_visible_to_reporter' => $options['is_visible_to_reporter'] ?? true,
        ]);

        Log::info('Case timeline event logged', [
            'case_id' => $case->id,
            'event_type' => $eventType,
            'stage' => $stage,
            'event_id' => $event->id,
        ]);

        return $event;
    }

    /**
     * Log case submission event
     */
    public function logCaseSubmitted(CaseModel $case): CaseTimelineEvent
    {
        return $this->logEvent(
            $case,
            CaseTimelineEvent::EVENT_SUBMITTED,
            CaseTimelineEvent::STAGE_SUBMISSION,
            [
                'actor_type' => $case->is_anonymous ? CaseTimelineEvent::ACTOR_REPORTER : CaseTimelineEvent::ACTOR_USER,
                'title' => 'Case Submitted',
                'description' => 'A new ' . $case->type . ' case has been submitted.',
                'metadata' => [
                    'case_type' => $case->type,
                    'is_anonymous' => $case->is_anonymous,
                    'source' => $case->source,
                ],
            ]
        );
    }

    /**
     * Log case assignment event
     */
    public function logCaseAssigned(
        CaseModel $case,
        User $assignee,
        ?User $assignedBy = null,
        bool $isReassignment = false,
        array $additionalMeta = []
    ): CaseTimelineEvent {
        $eventType = $isReassignment
            ? CaseTimelineEvent::EVENT_REASSIGNED
            : CaseTimelineEvent::EVENT_ASSIGNED;

        return $this->logEvent(
            $case,
            $eventType,
            CaseTimelineEvent::STAGE_ASSIGNMENT,
            [
                'actor_id' => $assignedBy?->id,
                'assigned_to_id' => $assignee->id,
                'title' => $isReassignment ? 'Case Reassigned' : 'Investigator Assigned',
                'description' => 'Case ' . ($isReassignment ? 'reassigned' : 'assigned') . ' to ' . $assignee->name,
                'metadata' => array_merge([
                    'assignee_name' => $assignee->name,
                    'assignee_role' => $assignee->role,
                ], $additionalMeta),
            ]
        );
    }

    /**
     * Log investigator unassignment event
     */
    public function logInvestigatorUnassigned(
        CaseModel $case,
        User $investigator,
        ?User $unassignedBy = null,
        ?string $reason = null
    ): CaseTimelineEvent {
        return $this->logEvent(
            $case,
            CaseTimelineEvent::EVENT_UNASSIGNED,
            CaseTimelineEvent::STAGE_ASSIGNMENT,
            [
                'actor_id' => $unassignedBy?->id,
                'assigned_to_id' => $investigator->id,
                'title' => 'Investigator Removed',
                'description' => $investigator->name . ' has been removed from this case' . ($reason ? ": {$reason}" : ''),
                'metadata' => [
                    'investigator_name' => $investigator->name,
                    'investigator_role' => $investigator->role,
                    'removal_reason' => $reason,
                ],
            ]
        );
    }

    /**
     * Log investigation started event
     */
    public function logInvestigationStarted(CaseModel $case, User $investigator): CaseTimelineEvent
    {
        return $this->logEvent(
            $case,
            CaseTimelineEvent::EVENT_INVESTIGATION_STARTED,
            CaseTimelineEvent::STAGE_INVESTIGATION,
            [
                'actor_id' => $investigator->id,
                'title' => 'Investigation Started',
                'description' => 'Investigation has been started by ' . $investigator->name,
                'metadata' => [
                    'investigator_name' => $investigator->name,
                ],
            ]
        );
    }

    /**
     * Log case status change
     */
    public function logStatusChange(
        CaseModel $case,
        string $oldStatus,
        string $newStatus,
        ?User $changedBy = null,
        ?string $reason = null
    ): CaseTimelineEvent {
        $stage = $this->getStageFromStatus($newStatus);

        return $this->logEvent(
            $case,
            CaseTimelineEvent::EVENT_STATUS_CHANGED,
            $stage,
            [
                'actor_id' => $changedBy?->id,
                'title' => 'Status Changed',
                'description' => "Status changed from {$oldStatus} to {$newStatus}" . ($reason ? ": {$reason}" : ''),
                'changes' => [
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'reason' => $reason,
                ],
            ]
        );
    }

    /**
     * Log case closed event
     */
    public function logCaseClosed(
        CaseModel $case,
        ?User $closedBy = null,
        ?string $resolutionNote = null
    ): CaseTimelineEvent {
        return $this->logEvent(
            $case,
            CaseTimelineEvent::EVENT_CLOSED,
            CaseTimelineEvent::STAGE_CLOSED,
            [
                'actor_id' => $closedBy?->id,
                'title' => 'Case Closed',
                'description' => $resolutionNote ?? 'Case has been closed.',
                'metadata' => [
                    'resolution_note' => $resolutionNote,
                    'closed_at' => now()->toISOString(),
                ],
            ]
        );
    }

    /**
     * Log case reopened event
     */
    public function logCaseReopened(
        CaseModel $case,
        ?User $reopenedBy = null,
        ?string $reason = null
    ): CaseTimelineEvent {
        return $this->logEvent(
            $case,
            CaseTimelineEvent::EVENT_REOPENED,
            CaseTimelineEvent::STAGE_INVESTIGATION,
            [
                'actor_id' => $reopenedBy?->id,
                'title' => 'Case Reopened',
                'description' => $reason ?? 'Case has been reopened.',
                'metadata' => [
                    'reason' => $reason,
                    'reopened_at' => now()->toISOString(),
                ],
            ]
        );
    }

    /**
     * Log escalation event
     */
    public function logEscalation(
        CaseModel $case,
        CaseEscalationRule $rule,
        int $overdueMinutes,
        array $notifiedUsers = [],
        ?User $escalatedTo = null
    ): CaseTimelineEvent {
        return $this->logEvent(
            $case,
            CaseTimelineEvent::EVENT_ESCALATED,
            $rule->stage,
            [
                'actor_type' => CaseTimelineEvent::ACTOR_SYSTEM,
                'escalated_to_id' => $escalatedTo?->id,
                'is_escalation' => true,
                'escalation_level' => (int) str_replace('level_', '', $rule->escalation_level),
                'escalation_reason' => "Case overdue by " . $this->formatMinutes($overdueMinutes),
                'escalation_rule_id' => $rule->id,
                'title' => 'Case Escalated - ' . $rule->getEscalationLevels()[$rule->escalation_level],
                'description' => "Case escalated due to being overdue in {$rule->stage} stage. Rule: {$rule->name}",
                'metadata' => [
                    'rule_name' => $rule->name,
                    'overdue_minutes' => $overdueMinutes,
                    'threshold_minutes' => $rule->escalation_threshold,
                    'notified_users' => collect($notifiedUsers)->pluck('id')->toArray(),
                ],
                'is_internal' => true,
                'is_visible_to_reporter' => false,
            ]
        );
    }

    /**
     * Get the last event for a case
     */
    public function getLastEvent(CaseModel $case): ?CaseTimelineEvent
    {
        return CaseTimelineEvent::where('case_id', $case->id)
            ->orderBy('event_at', 'desc')
            ->first();
    }

    /**
     * Get first event in a specific stage
     */
    public function getFirstEventInStage(CaseModel $case, string $stage): ?CaseTimelineEvent
    {
        return CaseTimelineEvent::where('case_id', $case->id)
            ->where('stage', $stage)
            ->orderBy('event_at', 'asc')
            ->first();
    }

    /**
     * Get full timeline for a case
     */
    public function getTimeline(CaseModel $case, bool $includeInternal = true): array
    {
        $query = CaseTimelineEvent::where('case_id', $case->id)
            ->orderBy('event_at', 'asc');

        if (!$includeInternal) {
            $query->where('is_internal', false);
        }

        $events = $query->with(['actor', 'assignedTo', 'escalatedTo'])->get();

        return $events->map(function ($event) {
            return [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'event_label' => $event->getEventLabel(),
                'stage' => $event->stage,
                'stage_label' => $event->getStageLabel(),
                'title' => $event->title,
                'description' => $event->description,
                'event_at' => $event->event_at->toISOString(),
                'event_at_human' => $event->event_at->diffForHumans(),
                'actor' => $event->actor ? [
                    'id' => $event->actor->id,
                    'name' => $event->actor->name,
                ] : null,
                'actor_type' => $event->actor_type,
                'assigned_to' => $event->assignedTo ? [
                    'id' => $event->assignedTo->id,
                    'name' => $event->assignedTo->name,
                ] : null,
                'duration_from_previous' => $event->getFormattedDuration(),
                'duration_in_stage' => $event->getFormattedStageDuration(),
                'total_duration' => $event->getFormattedTotalDuration(),
                'is_escalation' => $event->is_escalation,
                'escalation_level' => $event->escalation_level,
                'sla_breached' => $event->sla_breached,
                'is_internal' => $event->is_internal,
                'metadata' => $event->metadata,
            ];
        })->toArray();
    }

    /**
     * Get case duration summary
     */
    public function getDurationSummary(CaseModel $case): array
    {
        $events = CaseTimelineEvent::where('case_id', $case->id)
            ->orderBy('event_at', 'asc')
            ->get();

        $stageDurations = [];
        $currentStage = null;
        $stageStartTime = null;

        foreach ($events as $event) {
            if ($currentStage !== $event->stage) {
                // End previous stage
                if ($currentStage && $stageStartTime) {
                    if (!isset($stageDurations[$currentStage])) {
                        $stageDurations[$currentStage] = 0;
                    }
                    $stageDurations[$currentStage] += $stageStartTime->diffInMinutes($event->event_at);
                }
                // Start new stage
                $currentStage = $event->stage;
                $stageStartTime = $event->event_at;
            }
        }

        // Handle current stage (still ongoing)
        if ($currentStage && $stageStartTime && $case->status !== 'closed') {
            if (!isset($stageDurations[$currentStage])) {
                $stageDurations[$currentStage] = 0;
            }
            $stageDurations[$currentStage] += $stageStartTime->diffInMinutes(now());
        }

        $totalDuration = $case->created_at->diffInMinutes(
            $case->status === 'closed' && $case->resolved_at
                ? $case->resolved_at
                : now()
        );

        return [
            'total_duration_minutes' => $totalDuration,
            'total_duration_formatted' => $this->formatMinutes($totalDuration),
            'stage_durations' => collect($stageDurations)->map(function ($minutes, $stage) {
                return [
                    'stage' => $stage,
                    'stage_label' => CaseTimelineEvent::getStages()[$stage] ?? $stage,
                    'duration_minutes' => $minutes,
                    'duration_formatted' => $this->formatMinutes($minutes),
                ];
            })->values()->toArray(),
            'current_stage' => $currentStage,
            'current_stage_duration_minutes' => $stageDurations[$currentStage] ?? 0,
            'current_stage_duration_formatted' => $this->formatMinutes($stageDurations[$currentStage] ?? 0),
            'events_count' => $events->count(),
            'escalations_count' => $events->where('is_escalation', true)->count(),
            'case_status' => $case->status,
        ];
    }

    /**
     * Check for overdue cases and trigger escalations
     */
    public function checkAndEscalateOverdueCases(): array
    {
        $escalatedCases = [];

        // Get all active escalation rules
        $rules = CaseEscalationRule::where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get();

        // Get open cases that haven't been escalated at the same level recently
        $openCases = CaseModel::whereIn('status', ['open', 'assigned', 'in_progress'])
            ->with(['company', 'branch', 'assignedInvestigator'])
            ->get();

        foreach ($openCases as $case) {
            $currentStage = $this->getCurrentStage($case);
            $applicableRules = $rules->filter(
                fn($rule) =>
                $rule->stage === $currentStage && $rule->appliesTo($case)
            );

            foreach ($applicableRules as $rule) {
                $overdueMinutes = $this->getOverdueMinutes($case, $rule);

                if ($overdueMinutes >= $rule->escalation_threshold) {
                    // Check if already escalated at this level
                    $existingEscalation = CaseEscalation::where('case_id', $case->id)
                        ->where('escalation_rule_id', $rule->id)
                        ->where('is_resolved', false)
                        ->exists();

                    if (!$existingEscalation) {
                        $this->triggerEscalation($case, $rule, $overdueMinutes);
                        $escalatedCases[] = [
                            'case_id' => $case->id,
                            'rule_id' => $rule->id,
                            'rule_name' => $rule->name,
                            'overdue_minutes' => $overdueMinutes,
                        ];
                    }
                }
            }
        }

        return $escalatedCases;
    }

    /**
     * Trigger an escalation
     */
    protected function triggerEscalation(
        CaseModel $case,
        CaseEscalationRule $rule,
        int $overdueMinutes
    ): CaseEscalation {
        $notifiedUsers = collect();
        $notifiedEmails = collect();

        // Gather recipients based on rule settings
        if ($rule->notify_current_assignee && $case->assigned_to) {
            $assignee = User::find($case->assigned_to);
            if ($assignee) {
                $notifiedUsers->push($assignee);
            }
        }

        if ($rule->notify_branch_admin && $case->branch_id) {
            $branchAdmins = User::where('branch_id', $case->branch_id)
                ->where('role', User::ROLE_BRANCH_ADMIN)
                ->where('status', 'active')
                ->get();
            $notifiedUsers = $notifiedUsers->merge($branchAdmins);
        }

        if ($rule->notify_company_admin && $case->company_id) {
            $companyAdmins = User::where('company_id', $case->company_id)
                ->where('role', User::ROLE_COMPANY_ADMIN)
                ->where('status', 'active')
                ->get();
            $notifiedUsers = $notifiedUsers->merge($companyAdmins);
        }

        if ($rule->notify_super_admin) {
            $superAdmins = User::where('role', User::ROLE_SUPER_ADMIN)
                ->where('status', 'active')
                ->get();
            $notifiedUsers = $notifiedUsers->merge($superAdmins);
        }

        if ($rule->escalation_to_user_id) {
            $escalationUser = User::find($rule->escalation_to_user_id);
            if ($escalationUser) {
                $notifiedUsers->push($escalationUser);
            }
        }

        if ($rule->notify_emails) {
            $notifiedEmails = collect($rule->notify_emails);
        }

        // Remove duplicates
        $notifiedUsers = $notifiedUsers->unique('id');

        // Create escalation record
        $escalation = CaseEscalation::create([
            'case_id' => $case->id,
            'escalation_rule_id' => $rule->id,
            'stage' => $rule->stage,
            'escalation_level' => $rule->escalation_level,
            'reason' => "Case overdue in {$rule->stage} stage. Threshold: {$rule->escalation_threshold} minutes. Overdue by: {$overdueMinutes} minutes.",
            'overdue_minutes' => $overdueMinutes,
            'notified_users' => $notifiedUsers->pluck('id')->toArray(),
            'notified_emails' => $notifiedEmails->toArray(),
        ]);

        // Log timeline event
        $timelineEvent = $this->logEscalation(
            $case,
            $rule,
            $overdueMinutes,
            $notifiedUsers->toArray(),
            $notifiedUsers->first()
        );

        // Update escalation with timeline event
        $escalation->update(['timeline_event_id' => $timelineEvent->id]);

        // Handle auto-actions
        if ($rule->auto_reassign && $rule->auto_reassign_to_id) {
            $newAssignee = User::find($rule->auto_reassign_to_id);
            if ($newAssignee) {
                $case->update(['assigned_to' => $newAssignee->id]);
                $escalation->update([
                    'was_reassigned' => true,
                    'reassigned_to_id' => $newAssignee->id,
                ]);
            }
        }

        if ($rule->auto_change_priority && $rule->new_priority) {
            $oldPriority = $case->priority;
            $case->update(['priority' => $this->mapPriorityToInt($rule->new_priority)]);
            $escalation->update([
                'priority_changed' => true,
                'old_priority' => $this->mapPriorityToString($oldPriority),
                'new_priority' => $rule->new_priority,
            ]);
        }

        // Send notifications
        foreach ($notifiedUsers as $user) {
            try {
                Mail::to($user->email)->queue(new CaseEscalationNotification($case, $escalation, $user));
            } catch (\Exception $e) {
                Log::error('Failed to send escalation notification', [
                    'case_id' => $case->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        foreach ($notifiedEmails as $email) {
            try {
                Mail::to($email)->queue(new CaseEscalationNotification($case, $escalation));
            } catch (\Exception $e) {
                Log::error('Failed to send escalation notification to email', [
                    'case_id' => $case->id,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Case escalated', [
            'case_id' => $case->id,
            'escalation_id' => $escalation->id,
            'rule_id' => $rule->id,
            'overdue_minutes' => $overdueMinutes,
            'notified_count' => $notifiedUsers->count() + $notifiedEmails->count(),
        ]);

        return $escalation;
    }

    /**
     * Get current stage for a case based on status
     */
    public function getCurrentStage(CaseModel $case): string
    {
        return match ($case->status) {
            'open' => CaseTimelineEvent::STAGE_TRIAGE,
            'assigned' => CaseTimelineEvent::STAGE_ASSIGNMENT,
            'in_progress' => CaseTimelineEvent::STAGE_INVESTIGATION,
            'resolved', 'pending_closure' => CaseTimelineEvent::STAGE_RESOLUTION,
            'closed' => CaseTimelineEvent::STAGE_CLOSED,
            default => CaseTimelineEvent::STAGE_SUBMISSION,
        };
    }

    /**
     * Get stage from status string
     */
    protected function getStageFromStatus(string $status): string
    {
        return match ($status) {
            'open' => CaseTimelineEvent::STAGE_TRIAGE,
            'assigned' => CaseTimelineEvent::STAGE_ASSIGNMENT,
            'in_progress' => CaseTimelineEvent::STAGE_INVESTIGATION,
            'resolved', 'pending_closure' => CaseTimelineEvent::STAGE_RESOLUTION,
            'closed' => CaseTimelineEvent::STAGE_CLOSED,
            default => CaseTimelineEvent::STAGE_SUBMISSION,
        };
    }

    /**
     * Calculate how many minutes overdue based on rule
     */
    protected function getOverdueMinutes(CaseModel $case, CaseEscalationRule $rule): int
    {
        $stageStartEvent = $this->getFirstEventInStage($case, $rule->stage);

        if (!$stageStartEvent) {
            // If no event for this stage, use case creation time
            $startTime = $case->created_at;
        } else {
            $startTime = $stageStartEvent->event_at;
        }

        $elapsedMinutes = $startTime->diffInMinutes(now());

        // TODO: Implement business hours calculation if needed
        if ($rule->use_business_hours) {
            // For now, just use elapsed minutes
            // Future: Calculate only business hours
        }

        return $elapsedMinutes;
    }

    /**
     * Calculate SLA info for a case in a specific stage
     */
    protected function calculateSlaInfo(CaseModel $case, string $stage): array
    {
        // Get applicable rule
        $rule = CaseEscalationRule::where('is_active', true)
            ->where('stage', $stage)
            ->where(function ($q) use ($case) {
                $q->where('company_id', $case->company_id)
                    ->orWhere('is_global', true);
            })
            ->orderBy('priority', 'desc')
            ->first();

        if (!$rule) {
            return ['breached' => false, 'deadline' => null, 'remaining_minutes' => null];
        }

        $stageStartEvent = $this->getFirstEventInStage($case, $stage);
        $startTime = $stageStartEvent?->event_at ?? $case->created_at;

        $deadline = $startTime->copy()->addMinutes($rule->escalation_threshold);
        $remainingMinutes = now()->diffInMinutes($deadline, false);
        $breached = $remainingMinutes < 0;

        return [
            'breached' => $breached,
            'deadline' => $deadline,
            'remaining_minutes' => $remainingMinutes,
        ];
    }

    /**
     * Format minutes to human readable
     */
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

    /**
     * Map priority string to int
     */
    protected function mapPriorityToInt(string $priority): int
    {
        return match ($priority) {
            'low' => 1,
            'medium' => 2,
            'high' => 3,
            'urgent', 'critical' => 4,
            default => 2,
        };
    }

    /**
     * Map priority int to string
     */
    protected function mapPriorityToString(int $priority): string
    {
        return match ($priority) {
            1 => 'low',
            2 => 'medium',
            3 => 'high',
            4 => 'urgent',
            default => 'medium',
        };
    }
}
