<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CaseEscalationRule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class EscalationRuleController extends Controller
{
    /**
     * List all escalation rules
     */
    public function index(Request $request): JsonResponse
    {
        $query = CaseEscalationRule::with(['company', 'branch', 'createdBy']);

        // Filter by company
        if ($request->has('company_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('company_id', $request->company_id)
                    ->orWhere('is_global', true);
            });
        }

        // Filter by branch
        if ($request->has('branch_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('branch_id', $request->branch_id)
                    ->orWhereNull('branch_id');
            });
        }

        // Filter by stage
        if ($request->has('stage')) {
            $query->where('stage', $request->stage);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by global
        if ($request->has('is_global')) {
            $query->where('is_global', $request->boolean('is_global'));
        }

        $rules = $query->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $rules->map(function ($rule) {
                return $this->formatRule($rule);
            }),
        ]);
    }

    /**
     * Get a specific escalation rule
     */
    public function show(string $id): JsonResponse
    {
        $rule = CaseEscalationRule::with(['company', 'branch', 'createdBy', 'updatedBy', 'escalationToUser', 'autoReassignTo'])
            ->find($id);

        if (!$rule) {
            return response()->json([
                'status' => 'error',
                'message' => 'Escalation rule not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->formatRule($rule, true),
        ]);
    }

    /**
     * Create a new escalation rule
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'company_id' => 'nullable|string|exists:companies,id',
            'branch_id' => 'nullable|string|exists:branches,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
            'is_global' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:0|max:100',
            'stage' => 'required|string|in:submission,triage,assignment,investigation,resolution',
            'applies_to' => 'nullable|string|in:all,incident,feedback',
            'warning_threshold' => 'nullable|integer|min:1',
            'escalation_threshold' => 'required|integer|min:1',
            'critical_threshold' => 'nullable|integer|min:1',
            'use_business_hours' => 'nullable|boolean',
            'business_hours' => 'nullable|array',
            'exclude_weekends' => 'nullable|boolean',
            'exclude_holidays' => 'nullable|boolean',
            'escalation_level' => 'nullable|string|in:level_1,level_2,level_3',
            'escalation_to_roles' => 'nullable|array',
            'escalation_to_user_id' => 'nullable|string|exists:users,id',
            'notify_current_assignee' => 'nullable|boolean',
            'notify_branch_admin' => 'nullable|boolean',
            'notify_company_admin' => 'nullable|boolean',
            'notify_super_admin' => 'nullable|boolean',
            'notify_emails' => 'nullable|array',
            'notify_emails.*' => 'email',
            'auto_reassign' => 'nullable|boolean',
            'auto_reassign_to_id' => 'nullable|string|exists:users,id',
            'auto_change_priority' => 'nullable|boolean',
            'new_priority' => 'nullable|string|in:low,medium,high,urgent',
            'conditions' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['created_by'] = $request->user()->id;

        // Set defaults
        $data['is_active'] = $data['is_active'] ?? true;
        $data['is_global'] = $data['is_global'] ?? false;
        $data['priority'] = $data['priority'] ?? 0;
        $data['applies_to'] = $data['applies_to'] ?? 'all';
        $data['escalation_level'] = $data['escalation_level'] ?? 'level_1';
        $data['use_business_hours'] = $data['use_business_hours'] ?? true;
        $data['exclude_weekends'] = $data['exclude_weekends'] ?? true;
        $data['exclude_holidays'] = $data['exclude_holidays'] ?? true;
        $data['notify_current_assignee'] = $data['notify_current_assignee'] ?? true;
        $data['notify_branch_admin'] = $data['notify_branch_admin'] ?? true;
        $data['notify_company_admin'] = $data['notify_company_admin'] ?? false;
        $data['notify_super_admin'] = $data['notify_super_admin'] ?? false;
        $data['auto_reassign'] = $data['auto_reassign'] ?? false;
        $data['auto_change_priority'] = $data['auto_change_priority'] ?? false;

        $rule = CaseEscalationRule::create($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Escalation rule created successfully',
            'data' => $this->formatRule($rule->fresh(['company', 'branch', 'createdBy']), true),
        ], 201);
    }

    /**
     * Update an escalation rule
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $rule = CaseEscalationRule::find($id);

        if (!$rule) {
            return response()->json([
                'status' => 'error',
                'message' => 'Escalation rule not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'company_id' => 'nullable|string|exists:companies,id',
            'branch_id' => 'nullable|string|exists:branches,id',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
            'is_global' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:0|max:100',
            'stage' => 'sometimes|required|string|in:submission,triage,assignment,investigation,resolution',
            'applies_to' => 'nullable|string|in:all,incident,feedback',
            'warning_threshold' => 'nullable|integer|min:1',
            'escalation_threshold' => 'sometimes|required|integer|min:1',
            'critical_threshold' => 'nullable|integer|min:1',
            'use_business_hours' => 'nullable|boolean',
            'business_hours' => 'nullable|array',
            'exclude_weekends' => 'nullable|boolean',
            'exclude_holidays' => 'nullable|boolean',
            'escalation_level' => 'nullable|string|in:level_1,level_2,level_3',
            'escalation_to_roles' => 'nullable|array',
            'escalation_to_user_id' => 'nullable|string|exists:users,id',
            'notify_current_assignee' => 'nullable|boolean',
            'notify_branch_admin' => 'nullable|boolean',
            'notify_company_admin' => 'nullable|boolean',
            'notify_super_admin' => 'nullable|boolean',
            'notify_emails' => 'nullable|array',
            'notify_emails.*' => 'email',
            'auto_reassign' => 'nullable|boolean',
            'auto_reassign_to_id' => 'nullable|string|exists:users,id',
            'auto_change_priority' => 'nullable|boolean',
            'new_priority' => 'nullable|string|in:low,medium,high,urgent',
            'conditions' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['updated_by'] = $request->user()->id;

        $rule->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Escalation rule updated successfully',
            'data' => $this->formatRule($rule->fresh(['company', 'branch', 'createdBy', 'updatedBy']), true),
        ]);
    }

    /**
     * Delete an escalation rule
     */
    public function destroy(string $id): JsonResponse
    {
        $rule = CaseEscalationRule::find($id);

        if (!$rule) {
            return response()->json([
                'status' => 'error',
                'message' => 'Escalation rule not found',
            ], 404);
        }

        $rule->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Escalation rule deleted successfully',
        ]);
    }

    /**
     * Toggle rule active status
     */
    public function toggleActive(Request $request, string $id): JsonResponse
    {
        $rule = CaseEscalationRule::find($id);

        if (!$rule) {
            return response()->json([
                'status' => 'error',
                'message' => 'Escalation rule not found',
            ], 404);
        }

        $rule->update([
            'is_active' => !$rule->is_active,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Rule ' . ($rule->is_active ? 'activated' : 'deactivated') . ' successfully',
            'data' => [
                'id' => $rule->id,
                'is_active' => $rule->is_active,
            ],
        ]);
    }

    /**
     * Get available stages
     */
    public function getStages(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                ['value' => 'submission', 'label' => 'Submission'],
                ['value' => 'triage', 'label' => 'Triage'],
                ['value' => 'assignment', 'label' => 'Assignment'],
                ['value' => 'investigation', 'label' => 'Investigation'],
                ['value' => 'resolution', 'label' => 'Resolution'],
            ],
        ]);
    }

    /**
     * Get available escalation levels
     */
    public function getEscalationLevels(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => collect(CaseEscalationRule::getEscalationLevels())
                ->map(fn($label, $value) => ['value' => $value, 'label' => $label])
                ->values(),
        ]);
    }

    /**
     * Get default business hours
     */
    public function getDefaultBusinessHours(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => CaseEscalationRule::getDefaultBusinessHours(),
        ]);
    }

    /**
     * Format rule for response
     */
    protected function formatRule(CaseEscalationRule $rule, bool $detailed = false): array
    {
        $data = [
            'id' => $rule->id,
            'name' => $rule->name,
            'description' => $rule->description,
            'is_active' => $rule->is_active,
            'is_global' => $rule->is_global,
            'priority' => $rule->priority,
            'stage' => $rule->stage,
            'applies_to' => $rule->applies_to,
            'escalation_threshold' => $rule->escalation_threshold,
            'escalation_threshold_formatted' => $rule->getFormattedEscalationThreshold(),
            'escalation_level' => $rule->escalation_level,
            'company' => $rule->company ? [
                'id' => $rule->company->id,
                'name' => $rule->company->name,
            ] : null,
            'branch' => $rule->branch ? [
                'id' => $rule->branch->id,
                'name' => $rule->branch->name,
            ] : null,
            'created_at' => $rule->created_at->toISOString(),
        ];

        if ($detailed) {
            $data = array_merge($data, [
                'warning_threshold' => $rule->warning_threshold,
                'warning_threshold_formatted' => $rule->getFormattedWarningThreshold(),
                'critical_threshold' => $rule->critical_threshold,
                'critical_threshold_formatted' => $rule->getFormattedCriticalThreshold(),
                'use_business_hours' => $rule->use_business_hours,
                'business_hours' => $rule->business_hours,
                'exclude_weekends' => $rule->exclude_weekends,
                'exclude_holidays' => $rule->exclude_holidays,
                'escalation_to_roles' => $rule->escalation_to_roles,
                'escalation_to_user' => $rule->escalationToUser ? [
                    'id' => $rule->escalationToUser->id,
                    'name' => $rule->escalationToUser->name,
                ] : null,
                'notify_current_assignee' => $rule->notify_current_assignee,
                'notify_branch_admin' => $rule->notify_branch_admin,
                'notify_company_admin' => $rule->notify_company_admin,
                'notify_super_admin' => $rule->notify_super_admin,
                'notify_emails' => $rule->notify_emails,
                'auto_reassign' => $rule->auto_reassign,
                'auto_reassign_to' => $rule->autoReassignTo ? [
                    'id' => $rule->autoReassignTo->id,
                    'name' => $rule->autoReassignTo->name,
                ] : null,
                'auto_change_priority' => $rule->auto_change_priority,
                'new_priority' => $rule->new_priority,
                'conditions' => $rule->conditions,
                'created_by' => $rule->createdBy ? [
                    'id' => $rule->createdBy->id,
                    'name' => $rule->createdBy->name,
                ] : null,
                'updated_by' => $rule->updatedBy ? [
                    'id' => $rule->updatedBy->id,
                    'name' => $rule->updatedBy->name,
                ] : null,
                'updated_at' => $rule->updated_at->toISOString(),
            ]);
        }

        return $data;
    }
}
