<?php

namespace App\Http\Controllers\Api\Branch;

use App\Http\Controllers\Controller;
use App\Models\CaseModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BranchDashboardController extends Controller
{
    /**
     * Get dashboard statistics for the authenticated branch admin.
     */
    public function dashboard(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Ensure the request is authenticated and belongs to a branch admin
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            if ($user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only branch admins can view dashboard.'
                ], 403);
            }

            $branchId = $user->branch_id;

            // Quick overview numbers
            $overview = [
                'total_cases' => CaseModel::where('branch_id', $branchId)->count(),
                'active_cases' => CaseModel::where('branch_id', $branchId)
                    ->whereNotIn('status', ['closed', 'resolved'])
                    ->count(),
                'resolved_cases' => CaseModel::where('branch_id', $branchId)
                    ->whereIn('status', ['resolved', 'closed'])
                    ->count(),
                'unassigned_cases' => CaseModel::where('branch_id', $branchId)
                    ->whereNull('assigned_to')
                    ->count(),
            ];

            // Status counts
            $casesByStatus = [
                'labels' => ['Open', 'In Progress', 'Pending', 'Resolved', 'Closed'],
                'data' => [
                    CaseModel::where('branch_id', $branchId)->where('status', 'open')->count(),
                    CaseModel::where('branch_id', $branchId)->where('status', 'in_progress')->count(),
                    CaseModel::where('branch_id', $branchId)->where('status', 'pending')->count(),
                    CaseModel::where('branch_id', $branchId)->where('status', 'resolved')->count(),
                    CaseModel::where('branch_id', $branchId)->where('status', 'closed')->count(),
                ]
            ];

            // Priority distribution (1-4)
            $casesByPriority = [
                'labels' => ['1', '2', '3', '4'],
                'data' => [
                    CaseModel::where('branch_id', $branchId)->where('priority', 1)->count(),
                    CaseModel::where('branch_id', $branchId)->where('priority', 2)->count(),
                    CaseModel::where('branch_id', $branchId)->where('priority', 3)->count(),
                    CaseModel::where('branch_id', $branchId)->where('priority', 4)->count(),
                ]
            ];

            // Cases per category (incident + feedback)
            $incidentCategories = DB::table('case_categories')
                ->join('cases', 'case_categories.case_id', '=', 'cases.id')
                ->join('incident_categories', 'case_categories.category_id', '=', 'incident_categories.id')
                ->where('cases.branch_id', $branchId)
                ->where('case_categories.category_type', 'incident')
                ->whereNull('case_categories.deleted_at')
                ->select('incident_categories.name as category_name', DB::raw('count(*) as count'))
                ->groupBy('incident_categories.name')
                ->orderBy('count', 'desc')
                ->get();

            $feedbackCategories = DB::table('case_categories')
                ->join('cases', 'case_categories.case_id', '=', 'cases.id')
                ->join('feedback_categories', 'case_categories.category_id', '=', 'feedback_categories.id')
                ->where('cases.branch_id', $branchId)
                ->where('case_categories.category_type', 'feedback')
                ->whereNull('case_categories.deleted_at')
                ->select('feedback_categories.name as category_name', DB::raw('count(*) as count'))
                ->groupBy('feedback_categories.name')
                ->orderBy('count', 'desc')
                ->get();

            $casesPerCategory = [
                'labels' => $incidentCategories->merge($feedbackCategories)->pluck('category_name')->toArray(),
                'data' => $incidentCategories->merge($feedbackCategories)->pluck('count')->toArray()
            ];

            // Cases per department
            $departmentStats = DB::table('case_departments')
                ->join('cases', 'case_departments.case_id', '=', 'cases.id')
                ->join('departments', 'case_departments.department_id', '=', 'departments.id')
                ->where('cases.branch_id', $branchId)
                ->whereNull('case_departments.deleted_at')
                ->select('departments.name as department_name', DB::raw('count(*) as count'))
                ->groupBy('departments.name')
                ->orderBy('count', 'desc')
                ->get();

            $casesPerDepartment = [
                'labels' => $departmentStats->pluck('department_name')->toArray(),
                'data' => $departmentStats->pluck('count')->toArray()
            ];

            // Monthly new reports (last 12 months)
            $monthlyReports = [];
            for ($i = 11; $i >= 0; $i--) {
                $month = now()->subMonths($i);
                $monthlyReports[] = [
                    'month' => $month->format('M Y'),
                    'count' => CaseModel::where('branch_id', $branchId)
                        ->whereYear('created_at', $month->year)
                        ->whereMonth('created_at', $month->month)
                        ->count()
                ];
            }

            $monthlyChart = [
                'labels' => collect($monthlyReports)->pluck('month')->toArray(),
                'data' => collect($monthlyReports)->pluck('count')->toArray()
            ];

            // Investigator leaderboard for this branch
            $investigatorLeaderboard = DB::table('case_assignments')
                ->join('users', 'case_assignments.investigator_id', '=', 'users.id')
                ->join('cases', 'case_assignments.case_id', '=', 'cases.id')
                ->where('cases.branch_id', $branchId)
                ->where('case_assignments.status', 'active')
                ->select(
                    'users.id',
                    'users.name',
                    'users.email',
                    DB::raw('count(case_assignments.id) as total_cases'),
                    DB::raw('count(case when cases.status = \'closed\' then 1 end) as closed_cases'),
                    DB::raw('count(case when cases.status in (\'open\', \'in_progress\', \'pending\') then 1 end) as active_cases')
                )
                ->groupBy('users.id', 'users.name', 'users.email')
                ->orderBy('total_cases', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    return [
                        'investigator_id' => $item->id,
                        'investigator_name' => $item->name,
                        'investigator_email' => $item->email,
                        'total_cases' => $item->total_cases,
                        'closed_cases' => $item->closed_cases,
                        'active_cases' => $item->active_cases
                    ];
                });

            // Recent cases for quick view
            $recentCases = CaseModel::where('branch_id', $branchId)
                ->select('id', 'case_token', 'title', 'status', 'priority', 'created_at', 'resolved_at')
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'overview' => $overview,
                    'cases_by_status' => $casesByStatus,
                    'cases_by_priority' => $casesByPriority,
                    'cases_per_category' => $casesPerCategory,
                    'cases_per_department' => $casesPerDepartment,
                    'monthly_new_reports' => $monthlyChart,
                    'investigator_leaderboard' => $investigatorLeaderboard,
                    'recent_cases' => $recentCases
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve dashboard statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
