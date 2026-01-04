<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CaseModel;
use App\Models\CaseTimelineEvent;
use App\Models\CaseEscalation;
use App\Models\CaseEscalationRule;
use App\Services\CaseTrackingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class CaseTrackingController extends Controller
{
    protected CaseTrackingService $trackingService;

    public function __construct(CaseTrackingService $trackingService)
    {
        $this->trackingService = $trackingService;
    }

    /**
     * Get timeline for a specific case
     */
    public function getTimeline(Request $request, string $caseId): JsonResponse
    {
        $case = CaseModel::find($caseId);

        if (!$case) {
            return response()->json([
                'status' => 'error',
                'message' => 'Case not found',
            ], 404);
        }

        $includeInternal = $request->boolean('include_internal', true);
        $timeline = $this->trackingService->getTimeline($case, $includeInternal);

        return response()->json([
            'status' => 'success',
            'data' => [
                'case_id' => $case->id,
                'case_token' => $case->case_token,
                'timeline' => $timeline,
            ],
        ]);
    }

    /**
     * Get duration summary for a specific case
     */
    public function getDurationSummary(string $caseId): JsonResponse
    {
        $case = CaseModel::find($caseId);

        if (!$case) {
            return response()->json([
                'status' => 'error',
                'message' => 'Case not found',
            ], 404);
        }

        $summary = $this->trackingService->getDurationSummary($case);

        return response()->json([
            'status' => 'success',
            'data' => array_merge(['case_id' => $case->id], $summary),
        ]);
    }

    /**
     * Get escalations for a specific case
     */
    public function getCaseEscalations(string $caseId): JsonResponse
    {
        $case = CaseModel::find($caseId);

        if (!$case) {
            return response()->json([
                'status' => 'error',
                'message' => 'Case not found',
            ], 404);
        }

        $escalations = CaseEscalation::where('case_id', $caseId)
            ->with(['escalationRule', 'resolvedBy', 'reassignedTo'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'case_id' => $caseId,
                'escalations' => $escalations->map(function ($escalation) {
                    return [
                        'id' => $escalation->id,
                        'stage' => $escalation->stage,
                        'escalation_level' => $escalation->escalation_level,
                        'level_label' => $escalation->getLevelLabel(),
                        'reason' => $escalation->reason,
                        'overdue_duration' => $escalation->getFormattedOverdueDuration(),
                        'is_resolved' => $escalation->is_resolved,
                        'resolved_at' => $escalation->resolved_at?->toISOString(),
                        'resolved_by' => $escalation->resolvedBy ? [
                            'id' => $escalation->resolvedBy->id,
                            'name' => $escalation->resolvedBy->name,
                        ] : null,
                        'resolution_note' => $escalation->resolution_note,
                        'was_reassigned' => $escalation->was_reassigned,
                        'reassigned_to' => $escalation->reassignedTo ? [
                            'id' => $escalation->reassignedTo->id,
                            'name' => $escalation->reassignedTo->name,
                        ] : null,
                        'priority_changed' => $escalation->priority_changed,
                        'old_priority' => $escalation->old_priority,
                        'new_priority' => $escalation->new_priority,
                        'rule' => $escalation->escalationRule ? [
                            'id' => $escalation->escalationRule->id,
                            'name' => $escalation->escalationRule->name,
                        ] : null,
                        'created_at' => $escalation->created_at->toISOString(),
                    ];
                }),
                'unresolved_count' => $escalations->where('is_resolved', false)->count(),
            ],
        ]);
    }

    /**
     * Resolve an escalation
     */
    public function resolveEscalation(Request $request, string $escalationId): JsonResponse
    {
        $escalation = CaseEscalation::find($escalationId);

        if (!$escalation) {
            return response()->json([
                'status' => 'error',
                'message' => 'Escalation not found',
            ], 404);
        }

        if ($escalation->is_resolved) {
            return response()->json([
                'status' => 'error',
                'message' => 'Escalation is already resolved',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'resolution_note' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $escalation->resolve(
            $request->user()->id,
            $request->resolution_note
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Escalation resolved successfully',
            'data' => $escalation->fresh(['resolvedBy']),
        ]);
    }

    /**
     * Get tracking dashboard statistics
     */
    public function getDashboardStats(Request $request): JsonResponse
    {
        $companyId = $request->query('company_id');
        $branchId = $request->query('branch_id');
        $days = (int) $request->query('days', 30);

        $casesQuery = CaseModel::query();
        $eventsQuery = CaseTimelineEvent::query();
        $escalationsQuery = CaseEscalation::query();

        if ($companyId) {
            $casesQuery->where('company_id', $companyId);
            $eventsQuery->where('company_id', $companyId);
            $escalationsQuery->whereHas('case', fn($q) => $q->where('company_id', $companyId));
        }

        if ($branchId) {
            $casesQuery->where('branch_id', $branchId);
            $eventsQuery->where('branch_id', $branchId);
            $escalationsQuery->whereHas('case', fn($q) => $q->where('branch_id', $branchId));
        }

        // Cases stats
        $totalCases = (clone $casesQuery)->where('created_at', '>=', now()->subDays($days))->count();
        $openCases = (clone $casesQuery)->whereIn('status', ['open', 'assigned', 'in_progress'])->count();
        $closedCases = (clone $casesQuery)
            ->where('status', 'closed')
            ->where('updated_at', '>=', now()->subDays($days))
            ->count();

        // Escalation stats
        $totalEscalations = (clone $escalationsQuery)->where('created_at', '>=', now()->subDays($days))->count();
        $unresolvedEscalations = (clone $escalationsQuery)->where('is_resolved', false)->count();
        $escalationsByLevel = (clone $escalationsQuery)
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('escalation_level, count(*) as count')
            ->groupBy('escalation_level')
            ->pluck('count', 'escalation_level');

        // SLA stats
        $slaBreachedCount = (clone $eventsQuery)
            ->where('sla_breached', true)
            ->where('created_at', '>=', now()->subDays($days))
            ->count();

        // Average durations by stage
        $avgDurations = CaseTimelineEvent::selectRaw('stage, AVG(duration_in_stage) as avg_duration')
            ->where('created_at', '>=', now()->subDays($days))
            ->whereNotNull('duration_in_stage')
            ->groupBy('stage')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->stage => round($item->avg_duration)];
            });

        // Cases by current stage
        $casesByStage = [];
        $openCasesQuery = (clone $casesQuery)->whereIn('status', ['open', 'assigned', 'in_progress'])->get();
        foreach ($openCasesQuery as $case) {
            $stage = $this->trackingService->getCurrentStage($case);
            if (!isset($casesByStage[$stage])) {
                $casesByStage[$stage] = 0;
            }
            $casesByStage[$stage]++;
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'period_days' => $days,
                'cases' => [
                    'total_new' => $totalCases,
                    'currently_open' => $openCases,
                    'closed_in_period' => $closedCases,
                    'by_stage' => $casesByStage,
                ],
                'escalations' => [
                    'total_in_period' => $totalEscalations,
                    'unresolved' => $unresolvedEscalations,
                    'by_level' => $escalationsByLevel,
                ],
                'sla' => [
                    'breached_count' => $slaBreachedCount,
                ],
                'avg_duration_by_stage' => $avgDurations->map(function ($minutes, $stage) {
                    return [
                        'stage' => $stage,
                        'avg_minutes' => $minutes,
                        'avg_formatted' => $this->formatMinutes($minutes),
                    ];
                })->values(),
            ],
        ]);
    }

    /**
     * Get overdue cases
     */
    public function getOverdueCases(Request $request): JsonResponse
    {
        $companyId = $request->query('company_id');
        $branchId = $request->query('branch_id');

        $rules = CaseEscalationRule::where('is_active', true)->get();
        $overdueCases = [];

        $casesQuery = CaseModel::whereIn('status', ['open', 'assigned', 'in_progress'])
            ->with(['company', 'branch', 'assignedInvestigator']);

        if ($companyId) {
            $casesQuery->where('company_id', $companyId);
        }

        if ($branchId) {
            $casesQuery->where('branch_id', $branchId);
        }

        foreach ($casesQuery->get() as $case) {
            $currentStage = $this->trackingService->getCurrentStage($case);
            $applicableRules = $rules->filter(
                fn($rule) =>
                $rule->stage === $currentStage && $rule->appliesTo($case)
            );

            foreach ($applicableRules as $rule) {
                $overdueMinutes = $this->getOverdueMinutes($case, $rule);

                if ($overdueMinutes >= $rule->escalation_threshold) {
                    $overdueCases[] = [
                        'case_id' => $case->id,
                        'case_token' => $case->case_token,
                        'type' => $case->type,
                        'status' => $case->status,
                        'current_stage' => $currentStage,
                        'company' => $case->company ? [
                            'id' => $case->company->id,
                            'name' => $case->company->name,
                        ] : null,
                        'branch' => $case->branch ? [
                            'id' => $case->branch->id,
                            'name' => $case->branch->name,
                        ] : null,
                        'assigned_to' => $case->assignedInvestigator ? [
                            'id' => $case->assignedInvestigator->id,
                            'name' => $case->assignedInvestigator->name,
                        ] : null,
                        'overdue_minutes' => $overdueMinutes,
                        'overdue_formatted' => $this->formatMinutes($overdueMinutes),
                        'threshold_minutes' => $rule->escalation_threshold,
                        'rule_name' => $rule->name,
                        'created_at' => $case->created_at->toISOString(),
                    ];
                    break; // Only count once per case
                }
            }
        }

        // Sort by overdue minutes descending
        usort($overdueCases, fn($a, $b) => $b['overdue_minutes'] - $a['overdue_minutes']);

        return response()->json([
            'status' => 'success',
            'data' => [
                'overdue_cases' => $overdueCases,
                'total_overdue' => count($overdueCases),
            ],
        ]);
    }

    /**
     * Helper to get overdue minutes
     */
    protected function getOverdueMinutes(CaseModel $case, CaseEscalationRule $rule): int
    {
        $stageStartEvent = $this->trackingService->getFirstEventInStage($case, $rule->stage);
        $startTime = $stageStartEvent?->event_at ?? $case->created_at;
        return $startTime->diffInMinutes(now());
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
}
