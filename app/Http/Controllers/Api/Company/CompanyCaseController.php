<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\CaseModel;
use App\Models\CaseCategory;
use App\Models\CaseDepartment;
use App\Models\CaseAssignment;
use App\Models\Department;
use App\Models\IncidentCategory;
use App\Models\FeedbackCategory;
use App\Models\User;
use App\Mail\InvestigatorAssignedToCase;
use App\Mail\InvestigatorRemovedFromCase;
use App\Services\CaseTrackingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CompanyCaseController extends Controller
{
    /**
     * Display a listing of cases for the authenticated company.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Check if user is authenticated via token
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            // Ensure user has company access and proper role
            if ($user->role !== 'company_admin' || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only company admins can manage cases.'
                ], 403);
            }

            $companyId = $user->company_id;

            $query = CaseModel::where('company_id', $companyId);

            // Apply filters
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }

            if ($request->filled('priority')) {
                $query->where('priority', $request->priority);
            }

            if ($request->filled('assigned_to')) {
                $query->where('assigned_to', $request->assigned_to);
            }

            if ($request->filled('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('case_token', 'ILIKE', "%{$search}%")
                        ->orWhere('title', 'ILIKE', "%{$search}%")
                        ->orWhere('description', 'ILIKE', "%{$search}%");
                });
            }

            // Date range filter
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Load relationships
            $cases = $query->with([
                'assignee:id,name,email',
                'branch:id,name',
                'departments:id,name',
                'caseCategories.incidentCategory:id,name',
                'caseCategories.feedbackCategory:id,name',
                'assignments' => function ($query) {
                    $query->where('status', 'active')
                        ->with('investigator:id,name,email');
                }
            ])
                ->withCount(['files', 'departments', 'caseCategories', 'assignments' => function ($query) {
                    $query->where('status', 'active');
                }])
                ->orderBy('created_at', 'desc')
                ->get();

            // Calculate statistics for the company
            $statistics = $this->calculateCompanyCaseStatistics($companyId);

            return response()->json([
                'success' => true,
                'data' => $cases,
                'statistics' => $statistics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve cases',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Calculate case statistics for the company.
     */
    private function calculateCompanyCaseStatistics(string $companyId): array
    {
        // Total cases
        $totalCases = CaseModel::where('company_id', $companyId)->count();

        // Cases by status
        $byStatus = CaseModel::where('company_id', $companyId)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Cases by type (incident vs feedback)
        $byType = CaseModel::where('company_id', $companyId)
            ->select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        // Cases with/without investigators
        $casesWithInvestigators = CaseModel::where('company_id', $companyId)
            ->whereHas('assignments', function ($q) {
                $q->where('status', 'active');
            })
            ->count();

        $casesWithoutInvestigators = $totalCases - $casesWithInvestigators;

        // Cases with/without departments
        $casesWithDepartments = CaseModel::where('company_id', $companyId)
            ->whereHas('departments')
            ->count();

        $casesWithoutDepartments = $totalCases - $casesWithDepartments;

        // Cases with/without categories
        $casesWithCategories = CaseModel::where('company_id', $companyId)
            ->whereHas('caseCategories')
            ->count();

        $casesWithoutCategories = $totalCases - $casesWithCategories;

        // Cases by department (top 10)
        $byDepartment = DB::table('cases')
            ->join('case_departments', 'cases.id', '=', 'case_departments.case_id')
            ->join('departments', 'case_departments.department_id', '=', 'departments.id')
            ->where('cases.company_id', $companyId)
            ->whereNull('case_departments.deleted_at')
            ->whereNull('cases.deleted_at')
            ->select('departments.id', 'departments.name', DB::raw('count(*) as count'))
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'count' => $item->count
                ];
            })
            ->toArray();

        // Cases by category (top 10)
        $byCategory = DB::table('cases')
            ->join('case_categories', 'cases.id', '=', 'case_categories.case_id')
            ->leftJoin('incident_categories', 'case_categories.category_id', '=', 'incident_categories.id')
            ->leftJoin('feedback_categories', 'case_categories.category_id', '=', 'feedback_categories.id')
            ->where('cases.company_id', $companyId)
            ->whereNull('case_categories.deleted_at')
            ->whereNull('cases.deleted_at')
            ->select(
                'case_categories.category_id as id',
                'case_categories.category_type as type',
                DB::raw('COALESCE(incident_categories.name, feedback_categories.name) as name'),
                DB::raw('count(*) as count')
            )
            ->groupBy('case_categories.category_id', 'case_categories.category_type', 'incident_categories.name', 'feedback_categories.name')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'type' => $item->type,
                    'count' => $item->count
                ];
            })
            ->toArray();

        // Cases by branch
        $byBranch = DB::table('cases')
            ->join('branches', 'cases.branch_id', '=', 'branches.id')
            ->where('cases.company_id', $companyId)
            ->whereNull('cases.deleted_at')
            ->select('branches.id', 'branches.name', DB::raw('count(*) as count'))
            ->groupBy('branches.id', 'branches.name')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'count' => $item->count
                ];
            })
            ->toArray();

        // Priority breakdown
        $byPriority = CaseModel::where('company_id', $companyId)
            ->select('priority', DB::raw('count(*) as count'))
            ->groupBy('priority')
            ->pluck('count', 'priority')
            ->toArray();

        return [
            'total_cases' => $totalCases,
            'by_status' => $byStatus,
            'by_type' => [
                'incident' => $byType['incident'] ?? 0,
                'feedback' => $byType['feedback'] ?? 0
            ],
            'by_priority' => $byPriority,
            'investigator_assignment' => [
                'assigned' => $casesWithInvestigators,
                'not_assigned' => $casesWithoutInvestigators
            ],
            'department_assignment' => [
                'assigned' => $casesWithDepartments,
                'not_assigned' => $casesWithoutDepartments
            ],
            'category_assignment' => [
                'assigned' => $casesWithCategories,
                'not_assigned' => $casesWithoutCategories
            ],
            'by_department' => $byDepartment,
            'by_category' => $byCategory,
            'by_branch' => $byBranch
        ];
    }

    /**
     * Display the specified case with full details.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            // Check if user is authenticated via token
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            // Ensure user has company access and proper role
            if ($user->role !== 'company_admin' || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only company admins can view cases.'
                ], 403);
            }

            $case = CaseModel::where('id', $id)
                ->where('company_id', $user->company_id)
                ->with([
                    'assignee:id,name,email,phone',
                    'branch:id,name,location',
                    'involvedParties.user:id,name,email',
                    'additionalParties:id,case_id,name,email,phone,role',
                    'assignments.investigator:id,name,email,phone',
                    'assignments.assignedBy:id,name',
                    'departments:id,name,description',
                    'caseDepartments.assignedBy:id,name',
                    'caseCategories.incidentCategory:id,name,description',
                    'caseCategories.feedbackCategory:id,name,description',
                    'caseCategories.assignedBy:id,name',
                    'files'
                ])
                ->first();

            if (!$case) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case not found or access denied'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $case
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve case',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update case status or assignment.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        // Check if user is authenticated via token
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required. Please provide a valid authorization token.'
            ], 401);
        }

        // Ensure user has company access and proper role
        if ($user->role !== 'company_admin' || !$user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Only company admins can update cases.'
            ], 403);
        }

        $case = CaseModel::where('id', $id)
            ->where('company_id', $user->company_id)
            ->first();

        if (!$case) {
            return response()->json([
                'success' => false,
                'message' => 'Case not found or access denied'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|required|in:open,in_progress,pending,resolved,closed',
            'priority' => 'sometimes|required|integer|between:1,4',
            'assigned_to' => 'sometimes|nullable|ulid|exists:users,id',
            'note' => 'sometimes|nullable|string|max:1000',
            'resolution_note' => 'required_if:status,closed|nullable|string|max:1000',
            'case_close_classification' => 'required_if:status,closed|nullable|in:substantiated,partially_substantiated,unsubstantiated'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $updateData = [];

            if ($request->has('status')) {
                $updateData['status'] = $request->status;

                // Set resolved_at if status is resolved or closed
                if (in_array($request->status, ['resolved', 'closed'])) {
                    $updateData['resolved_at'] = now();
                }

                // Validate and set case classification for closed status
                if ($request->status === 'closed') {
                    if (!$request->has('case_close_classification')) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => 'Case classification is required when closing a case. Must be one of: substantiated, partially_substantiated, unsubstantiated'
                        ], 422);
                    }

                    if (!$request->has('resolution_note') || empty($request->resolution_note)) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => 'Resolution note is required when closing a case'
                        ], 422);
                    }

                    $updateData['case_close_classification'] = $request->case_close_classification;
                    $updateData['case_closed_at'] = now();
                    $updateData['closed_by'] = $user->id;
                }
            }

            if ($request->has('priority')) {
                $updateData['priority'] = $request->priority;
            }

            if ($request->has('assigned_to')) {
                // Verify the investigator belongs to the same company
                if ($request->assigned_to) {
                    $investigator = User::where('id', $request->assigned_to)
                        ->where('company_id', $user->company_id)
                        ->where('role', 'investigator')
                        ->first();

                    if (!$investigator) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => 'Invalid investigator. Must be from the same company.'
                        ], 422);
                    }
                }

                $updateData['assigned_to'] = $request->assigned_to;
            }

            if ($request->has('note')) {
                $updateData['note'] = $request->note;
            }

            if ($request->has('resolution_note')) {
                $updateData['resolution_note'] = $request->resolution_note;
            }

            $case->update($updateData);

            DB::commit();

            $case->load([
                'assignee:id,name,email',
                'branch:id,name'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Case updated successfully',
                'data' => $case
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update case',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get company dashboard statistics and analytics.
     */
    public function dashboard(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Check if user is authenticated via token
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            // Ensure user has company access and proper role
            if ($user->role !== 'company_admin' || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only company admins can view dashboard.'
                ], 403);
            }

            $companyId = $user->company_id;

            // 1. Open, In Progress, Closed counts
            $statusCounts = [
                'open' => CaseModel::where('company_id', $companyId)->where('status', 'open')->count(),
                'in_progress' => CaseModel::where('company_id', $companyId)->where('status', 'in_progress')->count(),
                'closed' => CaseModel::where('company_id', $companyId)->where('status', 'closed')->count(),
            ];

            // 2. Cases By Branches
            $casesByBranches = DB::table('cases')
                ->join('branches', 'cases.branch_id', '=', 'branches.id')
                ->where('cases.company_id', $companyId)
                ->select('branches.id', 'branches.name', DB::raw('count(*) as total_cases'))
                ->groupBy('branches.id', 'branches.name')
                ->orderBy('total_cases', 'desc')
                ->get()
                ->map(function ($item) {
                    return [
                        'branch_id' => $item->id,
                        'branch_name' => $item->name,
                        'total_cases' => $item->total_cases
                    ];
                });

            // 3. Cases per Category (Bar Chart) - Combined incident and feedback
            $incidentCategories = DB::table('case_categories')
                ->join('cases', 'case_categories.case_id', '=', 'cases.id')
                ->join('incident_categories', 'case_categories.category_id', '=', 'incident_categories.id')
                ->where('cases.company_id', $companyId)
                ->where('case_categories.category_type', 'incident')
                ->whereNull('case_categories.deleted_at')
                ->select('incident_categories.name as category_name', DB::raw('count(*) as count'))
                ->groupBy('incident_categories.name')
                ->get();

            $feedbackCategories = DB::table('case_categories')
                ->join('cases', 'case_categories.case_id', '=', 'cases.id')
                ->join('feedback_categories', 'case_categories.category_id', '=', 'feedback_categories.id')
                ->where('cases.company_id', $companyId)
                ->where('case_categories.category_type', 'feedback')
                ->whereNull('case_categories.deleted_at')
                ->select('feedback_categories.name as category_name', DB::raw('count(*) as count'))
                ->groupBy('feedback_categories.name')
                ->get();

            $casesPerCategory = [
                'labels' => $incidentCategories->merge($feedbackCategories)->pluck('category_name')->toArray(),
                'data' => $incidentCategories->merge($feedbackCategories)->pluck('count')->toArray()
            ];

            // 4. Cases per Department (Bar Chart)
            $casesPerDepartment = DB::table('case_departments')
                ->join('cases', 'case_departments.case_id', '=', 'cases.id')
                ->join('departments', 'case_departments.department_id', '=', 'departments.id')
                ->where('cases.company_id', $companyId)
                ->whereNull('case_departments.deleted_at')
                ->select('departments.name as department_name', DB::raw('count(*) as count'))
                ->groupBy('departments.name')
                ->orderBy('count', 'desc')
                ->get();

            $departmentChart = [
                'labels' => $casesPerDepartment->pluck('department_name')->toArray(),
                'data' => $casesPerDepartment->pluck('count')->toArray()
            ];

            // 5. Cases by Status (Donut Chart)
            $casesByStatus = [
                'labels' => ['Open', 'In Progress', 'Pending', 'Resolved', 'Closed'],
                'data' => [
                    CaseModel::where('company_id', $companyId)->where('status', 'open')->count(),
                    CaseModel::where('company_id', $companyId)->where('status', 'in_progress')->count(),
                    CaseModel::where('company_id', $companyId)->where('status', 'pending')->count(),
                    CaseModel::where('company_id', $companyId)->where('status', 'resolved')->count(),
                    CaseModel::where('company_id', $companyId)->where('status', 'closed')->count(),
                ]
            ];

            // 6. Monthly New Reports (Line Chart) - Last 12 months
            $monthlyReports = [];
            for ($i = 11; $i >= 0; $i--) {
                $month = now()->subMonths($i);
                $monthlyReports[] = [
                    'month' => $month->format('M Y'),
                    'count' => CaseModel::where('company_id', $companyId)
                        ->whereYear('created_at', $month->year)
                        ->whereMonth('created_at', $month->month)
                        ->count()
                ];
            }

            $monthlyChart = [
                'labels' => collect($monthlyReports)->pluck('month')->toArray(),
                'data' => collect($monthlyReports)->pluck('count')->toArray()
            ];

            // 7. Investigator Leaderboard
            $investigatorLeaderboard = DB::table('case_assignments')
                ->join('users', 'case_assignments.investigator_id', '=', 'users.id')
                ->join('cases', 'case_assignments.case_id', '=', 'cases.id')
                ->where('cases.company_id', $companyId)
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

            return response()->json([
                'success' => true,
                'data' => [
                    'status_counts' => $statusCounts,
                    'cases_by_branches' => $casesByBranches,
                    'cases_per_category' => $casesPerCategory,
                    'cases_per_department' => $departmentChart,
                    'cases_by_status' => $casesByStatus,
                    'monthly_new_reports' => $monthlyChart,
                    'investigator_leaderboard' => $investigatorLeaderboard,
                    'total_cases' => CaseModel::where('company_id', $companyId)->count(),
                    'company_id' => $companyId
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

    /**
     * Get case statistics and analytics for the company.
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Check if user is authenticated via token
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            // Ensure user has company access and proper role
            if ($user->role !== 'company_admin' || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only company admins can view statistics.'
                ], 403);
            }

            $companyId = $user->company_id;

            // Overall statistics
            $stats = [
                'total_cases' => CaseModel::where('company_id', $companyId)->count(),

                // By status
                'by_status' => [
                    'open' => CaseModel::where('company_id', $companyId)->where('status', 'open')->count(),
                    'in_progress' => CaseModel::where('company_id', $companyId)->where('status', 'in_progress')->count(),
                    'pending' => CaseModel::where('company_id', $companyId)->where('status', 'pending')->count(),
                    'resolved' => CaseModel::where('company_id', $companyId)->where('status', 'resolved')->count(),
                    'closed' => CaseModel::where('company_id', $companyId)->where('status', 'closed')->count(),
                ],

                // By type
                'by_type' => [
                    'incident' => CaseModel::where('company_id', $companyId)->where('type', 'incident')->count(),
                    'feedback' => CaseModel::where('company_id', $companyId)->where('type', 'feedback')->count(),
                ],

                // By priority
                'by_priority' => [
                    'low' => CaseModel::where('company_id', $companyId)->where('priority', 1)->count(),
                    'medium' => CaseModel::where('company_id', $companyId)->where('priority', 2)->count(),
                    'high' => CaseModel::where('company_id', $companyId)->where('priority', 3)->count(),
                    'critical' => CaseModel::where('company_id', $companyId)->where('priority', 4)->count(),
                ],

                // Assignment statistics
                'assignment' => [
                    'assigned' => CaseModel::where('company_id', $companyId)->whereNotNull('assigned_to')->count(),
                    'unassigned' => CaseModel::where('company_id', $companyId)->whereNull('assigned_to')->count(),
                ],

                // Recent activity
                'recent' => [
                    'last_7_days' => CaseModel::where('company_id', $companyId)
                        ->where('created_at', '>=', now()->subDays(7))
                        ->count(),
                    'last_30_days' => CaseModel::where('company_id', $companyId)
                        ->where('created_at', '>=', now()->subDays(30))
                        ->count(),
                ],
            ];

            // Top categories (from case_categories pivot table)
            $topIncidentCategories = DB::table('case_categories')
                ->join('cases', 'case_categories.case_id', '=', 'cases.id')
                ->join('incident_categories', 'case_categories.category_id', '=', 'incident_categories.id')
                ->where('cases.company_id', $companyId)
                ->where('case_categories.category_type', 'incident')
                ->select('incident_categories.id', 'incident_categories.name', DB::raw('count(*) as count'))
                ->groupBy('incident_categories.id', 'incident_categories.name')
                ->orderBy('count', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'category_id' => $item->id,
                        'category_name' => $item->name,
                        'count' => $item->count
                    ];
                });

            $topFeedbackCategories = DB::table('case_categories')
                ->join('cases', 'case_categories.case_id', '=', 'cases.id')
                ->join('feedback_categories', 'case_categories.category_id', '=', 'feedback_categories.id')
                ->where('cases.company_id', $companyId)
                ->where('case_categories.category_type', 'feedback')
                ->select('feedback_categories.id', 'feedback_categories.name', DB::raw('count(*) as count'))
                ->groupBy('feedback_categories.id', 'feedback_categories.name')
                ->orderBy('count', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'category_id' => $item->id,
                        'category_name' => $item->name,
                        'count' => $item->count
                    ];
                });

            // Investigator workload
            $investigatorWorkload = CaseModel::where('company_id', $companyId)
                ->whereNotNull('assigned_to')
                ->whereIn('status', ['open', 'in_progress', 'pending'])
                ->select('assigned_to', DB::raw('count(*) as active_cases'))
                ->groupBy('assigned_to')
                ->with('assignee:id,name,email')
                ->get()
                ->map(function ($item) {
                    return [
                        'investigator_id' => $item->assigned_to,
                        'investigator_name' => $item->assignee->name ?? 'Unknown',
                        'investigator_email' => $item->assignee->email ?? '',
                        'active_cases' => $item->active_cases
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'statistics' => $stats,
                    'top_incident_categories' => $topIncidentCategories,
                    'top_feedback_categories' => $topFeedbackCategories,
                    'investigator_workload' => $investigatorWorkload,
                    'company_id' => $companyId
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get available investigators for assignment.
     * Returns both internal (branch/company admins) and external (investigator role) investigators.
     */
    public function availableInvestigators(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Check if user is authenticated via token
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            // Ensure user has company access and proper role
            if ($user->role !== 'company_admin' || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only company admins can view investigators.'
                ], 403);
            }

            // Get case_id if provided (to filter out involved persons)
            $caseId = $request->query('case_id');
            $case = null;
            if ($caseId) {
                $case = CaseModel::where('id', $caseId)->where('company_id', $user->company_id)->first();
            }

            // Internal investigators: branch_admins across all company branches
            $internalQuery = User::where('company_id', $user->company_id)
                ->where('role', User::ROLE_BRANCH_ADMIN)
                ->where('status', 'active')
                ->where('id', '!=', $user->id);

            // If case provided, exclude involved persons
            if ($case) {
                // Exclude submitter
                if ($case->submitted_by) {
                    $internalQuery->where('id', '!=', $case->submitted_by);
                }

                // Exclude named persons by email
                $namedPersons = is_array($case->named_persons) ? $case->named_persons : [];
                $namedEmails = collect($namedPersons)->pluck('email')->filter()->map('strtolower')->toArray();
                if (!empty($namedEmails)) {
                    $internalQuery->whereRaw('LOWER(email) NOT IN (' . implode(',', array_fill(0, count($namedEmails), '?')) . ')', $namedEmails);
                }
            }

            $internalInvestigators = $internalQuery
                ->select('id', 'name', 'email', 'phone', 'branch_id')
                ->with('branch:id,name')
                ->orderBy('name')
                ->get()
                ->map(function ($admin) use ($user) {
                    $activeCases = CaseAssignment::where('investigator_id', $admin->id)
                        ->where('status', 'active')
                        ->count();

                    return [
                        'id' => $admin->id,
                        'name' => $admin->name,
                        'email' => $admin->email,
                        'phone' => $admin->phone,
                        'branch_id' => $admin->branch_id,
                        'branch_name' => $admin->branch?->name,
                        'active_cases' => $activeCases,
                        'investigator_type' => 'internal'
                    ];
                });

            // External investigators: users with investigator role assigned to this company via pivot
            $companyId = $user->company_id;
            $externalInvestigators = User::where('role', 'investigator')
                ->where('status', 'active')
                ->whereHas('investigator', function ($q) use ($companyId) {
                    $q->where('status', true)
                        ->whereHas('companies', function ($q2) use ($companyId) {
                            $q2->where('companies.id', $companyId);
                        });
                })
                ->select('id', 'name', 'email', 'phone')
                ->orderBy('name')
                ->get()
                ->map(function ($investigator) use ($user) {
                    $activeCases = CaseAssignment::where('investigator_id', $investigator->id)
                        ->where('status', 'active')
                        ->count();

                    return [
                        'id' => $investigator->id,
                        'name' => $investigator->name,
                        'email' => $investigator->email,
                        'phone' => $investigator->phone,
                        'active_cases' => $activeCases,
                        'investigator_type' => 'external'
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'internal_investigators' => $internalInvestigators,
                    'external_investigators' => $externalInvestigators,
                    'total_available' => [
                        'internal' => $internalInvestigators->count(),
                        'external' => $externalInvestigators->count()
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve investigators',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get case tracking/timeline information.
     */
    public function timeline(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            // Check if user is authenticated via token
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            // Ensure user has company access and proper role
            if ($user->role !== 'company_admin' || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only company admins can view case timeline.'
                ], 403);
            }

            $case = CaseModel::where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (!$case) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case not found or access denied'
                ], 404);
            }

            $trackingService = app(CaseTrackingService::class);
            $includeInternal = $request->boolean('include_internal', true);
            $timeline = $trackingService->getTimeline($case, $includeInternal);

            return response()->json([
                'success' => true,
                'data' => [
                    'case_id' => $case->id,
                    'case_token' => $case->case_token,
                    'case_title' => $case->title,
                    'current_status' => $case->status,
                    'timeline' => $timeline
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve case timeline',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get case duration summary.
     */
    public function getDurationSummary(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user || $user->role !== 'company_admin' || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only company admins can view case duration.'
                ], 403);
            }

            $case = CaseModel::where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (!$case) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case not found or access denied'
                ], 404);
            }

            $trackingService = app(CaseTrackingService::class);
            $summary = $trackingService->getDurationSummary($case);

            return response()->json([
                'success' => true,
                'data' => array_merge([
                    'case_id' => $case->id,
                    'case_token' => $case->case_token,
                    'case_title' => $case->title,
                ], $summary)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve duration summary',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get case escalations.
     */
    public function getCaseEscalations(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user || $user->role !== 'company_admin' || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only company admins can view case escalations.'
                ], 403);
            }

            $case = CaseModel::where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (!$case) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case not found or access denied'
                ], 404);
            }

            $escalations = \App\Models\CaseEscalation::where('case_id', $id)
                ->with(['escalationRule', 'resolvedBy', 'reassignedTo'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'case_id' => $case->id,
                    'case_token' => $case->case_token,
                    'case_title' => $case->title,
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
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve case escalations',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get full case tracking details (timeline + duration + escalations).
     */
    public function getFullTracking(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user || $user->role !== 'company_admin' || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only company admins can view case tracking.'
                ], 403);
            }

            $case = CaseModel::where('id', $id)
                ->where('company_id', $user->company_id)
                ->with(['company:id,name', 'branch:id,name', 'assignee:id,name,email'])
                ->first();

            if (!$case) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case not found or access denied'
                ], 404);
            }

            $trackingService = app(CaseTrackingService::class);

            // Get timeline
            $includeInternal = $request->boolean('include_internal', true);
            $timeline = $trackingService->getTimeline($case, $includeInternal);

            // Get duration summary
            $durationSummary = $trackingService->getDurationSummary($case);

            // Get current stage
            $currentStage = $trackingService->getCurrentStage($case);

            // Get escalations
            $escalations = \App\Models\CaseEscalation::where('case_id', $id)
                ->with(['escalationRule:id,name', 'resolvedBy:id,name'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($escalation) {
                    return [
                        'id' => $escalation->id,
                        'stage' => $escalation->stage,
                        'level_label' => $escalation->getLevelLabel(),
                        'reason' => $escalation->reason,
                        'overdue_duration' => $escalation->getFormattedOverdueDuration(),
                        'is_resolved' => $escalation->is_resolved,
                        'resolved_at' => $escalation->resolved_at?->toISOString(),
                        'resolved_by' => $escalation->resolvedBy?->name,
                        'created_at' => $escalation->created_at->toISOString(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'case' => [
                        'id' => $case->id,
                        'case_token' => $case->case_token,
                        'title' => $case->title,
                        'type' => $case->type,
                        'status' => $case->status,
                        'priority' => $case->priority,
                        'company' => $case->company ? [
                            'id' => $case->company->id,
                            'name' => $case->company->name,
                        ] : null,
                        'branch' => $case->branch ? [
                            'id' => $case->branch->id,
                            'name' => $case->branch->name,
                        ] : null,
                        'assignee' => $case->assignee ? [
                            'id' => $case->assignee->id,
                            'name' => $case->assignee->name,
                            'email' => $case->assignee->email,
                        ] : null,
                        'created_at' => $case->created_at->toISOString(),
                        'resolved_at' => $case->resolved_at?->toISOString(),
                    ],
                    'current_stage' => $currentStage,
                    'timeline' => $timeline,
                    'duration' => $durationSummary,
                    'escalations' => [
                        'list' => $escalations,
                        'total' => $escalations->count(),
                        'unresolved' => $escalations->where('is_resolved', false)->count(),
                    ],
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve case tracking',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Assign departments to a case.
     */
    public function assignDepartments(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            // Check authentication
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            // Check role and company access
            if ($user->role !== 'company_admin' || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only company admins can assign departments.'
                ], 403);
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'department_ids' => 'required|array|min:1',
                'department_ids.*' => 'required|exists:departments,id',
                'assignment_note' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get case and verify ownership
            $case = CaseModel::where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (!$case) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case not found or access denied'
                ], 404);
            }

            // Verify all departments belong to the company
            $departments = Department::whereIn('id', $request->department_ids)
                ->where('company_id', $user->company_id)
                ->pluck('id')
                ->toArray();

            if (count($departments) !== count($request->department_ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'One or more departments do not belong to your company'
                ], 422);
            }

            DB::beginTransaction();
            try {
                // Assign departments
                foreach ($request->department_ids as $departmentId) {
                    // Check if a soft-deleted assignment exists
                    $existingAssignment = CaseDepartment::withTrashed()
                        ->where('case_id', $case->id)
                        ->where('department_id', $departmentId)
                        ->first();

                    if ($existingAssignment) {
                        if ($existingAssignment->trashed()) {
                            // Restore soft-deleted assignment and update it
                            $existingAssignment->restore();
                            $existingAssignment->update([
                                'assigned_at' => now(),
                                'assigned_by' => $user->id,
                                'assignment_note' => $request->assignment_note
                            ]);
                        } else {
                            // Update existing active assignment
                            $existingAssignment->update([
                                'assigned_at' => now(),
                                'assigned_by' => $user->id,
                                'assignment_note' => $request->assignment_note
                            ]);
                        }
                    } else {
                        // Create new assignment
                        CaseDepartment::create([
                            'case_id' => $case->id,
                            'department_id' => $departmentId,
                            'assigned_at' => now(),
                            'assigned_by' => $user->id,
                            'assignment_note' => $request->assignment_note
                        ]);
                    }
                }

                DB::commit();

                // Load updated relationships
                $case->load(['departments:id,name,description', 'caseDepartments.assignedBy:id,name']);

                return response()->json([
                    'success' => true,
                    'message' => 'Departments assigned successfully',
                    'data' => $case
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign departments',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove a department assignment from a case.
     */
    public function unassignDepartment(Request $request, string $id, string $departmentId): JsonResponse
    {
        try {
            $user = $request->user();

            // Check authentication
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            // Check role and company access
            if ($user->role !== 'company_admin' || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only company admins can unassign departments.'
                ], 403);
            }

            // Get case and verify ownership
            $case = CaseModel::where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (!$case) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case not found or access denied'
                ], 404);
            }

            // Verify department belongs to the company
            $department = Department::where('id', $departmentId)
                ->where('company_id', $user->company_id)
                ->first();

            if (!$department) {
                return response()->json([
                    'success' => false,
                    'message' => 'Department not found or access denied'
                ], 404);
            }

            // Delete the assignment
            $deleted = CaseDepartment::where('case_id', $case->id)
                ->where('department_id', $departmentId)
                ->delete();

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Department was not assigned to this case'

                ], 404);
            }

            // Load updated relationships
            $case->load(['departments:id,name,description', 'caseDepartments.assignedBy:id,name']);

            return response()->json([
                'success' => true,
                'message' => 'Department unassigned successfully',
                'data' => $case
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to unassign department',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get all departments assigned to a case.
     */
    public function getCaseDepartments(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            // Check authentication
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            // Check role and company access
            if ($user->role !== 'company_admin' || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only company admins can view case departments.'
                ], 403);
            }

            // Get case and verify ownership
            $case = CaseModel::where('id', $id)
                ->where('company_id', $user->company_id)
                ->with([
                    'caseDepartments.department:id,name,description,status',
                    'caseDepartments.assignedBy:id,name,email'
                ])
                ->first();

            if (!$case) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case not found or access denied'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'case_id' => $case->id,
                    'case_token' => $case->case_token,
                    'departments' => $case->caseDepartments
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve case departments',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Assign categories to a case.
     */
    public function assignCategories(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            // Check authentication
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            // Check role and company access
            if ($user->role !== 'company_admin' || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only company admins can assign categories.'
                ], 403);
            }

            // Get case and verify ownership
            $case = CaseModel::where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (!$case) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case not found or access denied'
                ], 404);
            }

            // Determine category type from case type
            $categoryType = $case->type; // 'incident' or 'feedback'

            // Validate request
            $validator = Validator::make($request->all(), [
                'category_ids' => 'required|array|min:1',
                'category_ids.*' => 'required|string',
                'assignment_note' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();
            try {
                $assignedCategories = [];

                // Verify and assign categories based on case type
                foreach ($request->category_ids as $categoryId) {
                    // Verify category belongs to the company based on case type
                    if ($categoryType === 'incident') {
                        $category = IncidentCategory::where('id', $categoryId)
                            ->where('company_id', $user->company_id)
                            ->first();
                    } elseif ($categoryType === 'feedback') {
                        $category = FeedbackCategory::where('id', $categoryId)
                            ->where('company_id', $user->company_id)
                            ->first();
                    } else {
                        // Skip if case type is not incident or feedback
                        continue;
                    }

                    if (!$category) {
                        continue;
                    }

                    // Check if already assigned
                    $existingAssignment = CaseCategory::where('case_id', $case->id)
                        ->where('category_id', $categoryId)
                        ->where('category_type', $categoryType)
                        ->first();

                    if (!$existingAssignment) {
                        // Assign category
                        $caseCategory = CaseCategory::create([
                            'case_id' => $case->id,
                            'category_id' => $categoryId,
                            'category_type' => $categoryType,
                            'assigned_by' => $user->id,
                            'assigned_at' => now(),
                            'assignment_note' => $request->assignment_note
                        ]);

                        $assignedCategories[] = $caseCategory;
                    }
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Categories assigned successfully',
                    'data' => $assignedCategories
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign categories',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove a category assignment from a case.
     */
    public function unassignCategory(Request $request, string $id, string $categoryId): JsonResponse
    {
        try {
            $user = $request->user();

            // Check authentication
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            // Check role and company access
            if ($user->role !== 'company_admin' || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only company admins can unassign categories.'
                ], 403);
            }

            // Validate category type
            $validator = Validator::make($request->all(), [
                'category_type' => 'required|in:incident,feedback'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get case and verify ownership
            $case = CaseModel::where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (!$case) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case not found or access denied'
                ], 404);
            }

            // Verify category belongs to the company
            $categoryType = $request->category_type;
            if ($categoryType === 'incident') {
                $category = IncidentCategory::where('id', $categoryId)
                    ->where('company_id', $user->company_id)
                    ->first();
            } else {
                $category = FeedbackCategory::where('id', $categoryId)
                    ->where('company_id', $user->company_id)
                    ->first();
            }

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Category not found or access denied'
                ], 404);
            }

            // Delete the assignment
            $deleted = CaseCategory::where('case_id', $case->id)
                ->where('category_id', $categoryId)
                ->where('category_type', $categoryType)
                ->delete();

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Category was not assigned to this case'
                ], 404);
            }

            // Load updated relationships
            $case->load([
                'incidentCategories:id,name,description',
                'feedbackCategories:id,name,description',
                'caseCategories.assignedBy:id,name'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Category unassigned successfully',
                'data' => $case
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to unassign category',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get all categories assigned to a case.
     */
    public function getCaseCategories(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            // Check authentication
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            // Check role and company access
            if ($user->role !== 'company_admin' || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only company admins can view case categories.'
                ], 403);
            }

            // Get case and verify ownership
            $case = CaseModel::where('id', $id)
                ->where('company_id', $user->company_id)
                ->with([
                    'caseCategories.incidentCategory:id,name,description,status',
                    'caseCategories.feedbackCategory:id,name,description,status',
                    'caseCategories.assignedBy:id,name,email'
                ])
                ->first();

            if (!$case) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case not found or access denied'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'case_id' => $case->id,
                    'case_token' => $case->case_token,
                    'categories' => $case->caseCategories
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve case categories',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get all files attached to a case.
     */
    public function getCaseFiles(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            // Check authentication
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            // Check role and company access
            if ($user->role !== 'company_admin' || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only company admins can view case files.'
                ], 403);
            }

            // Get case and verify ownership
            $case = CaseModel::where('id', $id)
                ->where('company_id', $user->company_id)
                ->with('files')
                ->first();

            if (!$case) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case not found or access denied'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'case_id' => $case->id,
                    'case_token' => $case->case_token,
                    'files' => $case->files,
                    'total_files' => $case->files->count(),
                    'total_size' => $case->files->sum('file_size')
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve case files',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Assign investigators to a case.
     * Supports both internal (branch_admin) and external (investigator role) investigators.
     */
    public function assignInvestigators(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            // Check authentication
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            // Ensure user has company access and proper role
            if ($user->role !== 'company_admin' || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only company admins can assign investigators.'
                ], 403);
            }

            // Verify case exists and belongs to company
            $case = CaseModel::where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (!$case) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case not found or access denied'
                ], 404);
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'investigators' => 'required|array|min:1',
                'investigators.*.investigator_id' => 'required|ulid|exists:users,id',
                'investigators.*.investigator_type' => 'required|in:internal,external',
                'investigators.*.assignment_type' => 'sometimes|in:primary,secondary,support,consultant',
                'investigators.*.is_lead' => 'sometimes|boolean',
                'investigators.*.priority_level' => 'sometimes|integer|between:1,3',
                'investigators.*.assignment_note' => 'sometimes|nullable|string|max:500',
                'investigators.*.estimated_hours' => 'sometimes|nullable|numeric|min:0',
                'investigators.*.deadline' => 'sometimes|nullable|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Validate that only one lead investigator is assigned
            $leadCount = collect($request->investigators)->where('is_lead', true)->count();
            if ($leadCount > 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only one lead investigator can be assigned per case'
                ], 422);
            }

            DB::beginTransaction();

            $assignedInvestigators = [];
            $errors = [];

            foreach ($request->investigators as $investigatorData) {
                $investigatorType = $investigatorData['investigator_type'];
                $investigator = null;
                $internalSource = null;

                if ($investigatorType === 'internal') {
                    // Internal: branch_admin from any branch in the company who is NOT involved in the case
                    $investigator = User::where('id', $investigatorData['investigator_id'])
                        ->where('company_id', $user->company_id)
                        ->where('role', User::ROLE_BRANCH_ADMIN)
                        ->where('status', 'active')
                        ->first();

                    if (!$investigator) {
                        $errors[] = "Internal investigator {$investigatorData['investigator_id']} not found or not a branch admin in your company";
                        continue;
                    }

                    // Check they're not involved in the case (reporter or named)
                    if ($case->submitted_by === $investigator->id) {
                        $errors[] = "{$investigator->name} cannot be assigned as they submitted this case";
                        continue;
                    }

                    // Check named_persons
                    $namedPersons = is_array($case->named_persons) ? $case->named_persons : [];
                    $namedEmails = collect($namedPersons)->pluck('email')->filter()->map('strtolower')->toArray();
                    if (in_array(strtolower($investigator->email), $namedEmails)) {
                        $errors[] = "{$investigator->name} cannot be assigned as they are named in this case";
                        continue;
                    }

                    $internalSource = 'branch_admin';
                } else {
                    // External: investigator role assigned to company
                    $investigator = User::where('id', $investigatorData['investigator_id'])
                        ->where('company_id', $user->company_id)
                        ->where('role', 'investigator')
                        ->where('status', 'active')
                        ->first();

                    if (!$investigator) {
                        $errors[] = "External investigator {$investigatorData['investigator_id']} not found or doesn't belong to your company";
                        continue;
                    }
                }

                // Check if already assigned and active
                $existingAssignment = CaseAssignment::where('case_id', $id)
                    ->where('investigator_id', $investigator->id)
                    ->where('status', 'active')
                    ->first();

                if ($existingAssignment) {
                    $errors[] = "Investigator {$investigator->name} is already assigned to this case";
                    continue;
                }

                // If assigning as lead, remove lead status from existing lead
                $isLead = $investigatorData['is_lead'] ?? false;
                if ($isLead) {
                    CaseAssignment::where('case_id', $id)
                        ->where('is_lead_investigator', true)
                        ->where('status', 'active')
                        ->update(['is_lead_investigator' => false]);
                }

                // Create assignment
                $assignment = CaseAssignment::create([
                    'case_id' => $id,
                    'investigator_id' => $investigator->id,
                    'assigned_by' => $user->id,
                    'assigned_at' => now(),
                    'investigator_type' => $investigatorType,
                    'is_lead_investigator' => $isLead,
                    'internal_source' => $internalSource,
                    'assignment_type' => $investigatorData['assignment_type'] ?? 'primary',
                    'priority_level' => $investigatorData['priority_level'] ?? 2,
                    'assignment_note' => $investigatorData['assignment_note'] ?? null,
                    'estimated_hours' => $investigatorData['estimated_hours'] ?? null,
                    'deadline' => isset($investigatorData['deadline']) ? $investigatorData['deadline'] : null,
                    'status' => 'active'
                ]);

                $assignment->load(['investigator:id,name,email,phone,role', 'assignedBy:id,name']);
                $assignedInvestigators[] = [
                    'assignment' => $assignment,
                    'investigator' => $investigator
                ];
            }

            // Update case status to "in_progress" if investigators were assigned
            if (count($assignedInvestigators) > 0 && $case->status === 'open') {
                $case->update(['status' => 'in_progress']);
            }

            DB::commit();

            // Send email notifications and log timeline events (after commit)
            $caseTrackingService = app(CaseTrackingService::class);
            $case->load(['company', 'branch']);

            foreach ($assignedInvestigators as $assignmentData) {
                $assignment = $assignmentData['assignment'];
                $investigator = $assignmentData['investigator'];

                // Log timeline event
                try {
                    $caseTrackingService->logCaseAssigned(
                        $case,
                        $investigator,
                        $user,
                        false,
                        [
                            'investigator_type' => $assignment->investigator_type,
                            'is_lead' => $assignment->is_lead_investigator,
                            'assignment_type' => $assignment->assignment_type,
                        ]
                    );
                } catch (\Exception $e) {
                    Log::warning('Failed to log case assignment timeline event', [
                        'case_id' => $case->id,
                        'investigator_id' => $investigator->id,
                        'error' => $e->getMessage()
                    ]);
                }

                // Send email notification
                try {
                    Mail::to($investigator->email)->queue(
                        new InvestigatorAssignedToCase($case, $investigator, $user, $assignment)
                    );
                } catch (\Exception $e) {
                    Log::warning('Failed to send investigator assignment email', [
                        'case_id' => $case->id,
                        'investigator_id' => $investigator->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Summary of assignments
            $assignments = collect($assignedInvestigators)->pluck('assignment');
            $internalCount = $assignments->where('investigator_type', 'internal')->count();
            $externalCount = $assignments->where('investigator_type', 'external')->count();

            return response()->json([
                'success' => true,
                'message' => count($assignedInvestigators) > 0
                    ? 'Investigators assigned successfully'
                    : 'No investigators were assigned',
                'data' => [
                    'assigned' => $assignments->values(),
                    'summary' => [
                        'total_assigned' => count($assignedInvestigators),
                        'internal_investigators' => $internalCount,
                        'external_investigators' => $externalCount
                    ],
                    'errors' => $errors
                ]
            ], count($assignedInvestigators) > 0 ? 200 : 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign investigators',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove an investigator assignment from a case.
     */
    public function unassignInvestigator(Request $request, string $id, string $assignmentId): JsonResponse
    {
        try {
            $user = $request->user();

            // Check authentication
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            // Ensure user has company access and proper role
            if ($user->role !== 'company_admin' || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only company admins can unassign investigators.'
                ], 403);
            }

            // Verify case exists and belongs to company
            $case = CaseModel::where('id', $id)
                ->where('company_id', $user->company_id)
                ->with(['company', 'branch'])
                ->first();

            if (!$case) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case not found or access denied'
                ], 404);
            }

            // Optional reason for removal
            $removalReason = $request->input('reason');

            // Find the assignment
            $assignment = CaseAssignment::where('id', $assignmentId)
                ->where('case_id', $id)
                ->with('investigator')
                ->first();

            if (!$assignment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Assignment not found'
                ], 404);
            }

            $investigator = $assignment->investigator;

            // Verify investigator exists
            if (!$investigator) {
                return response()->json([
                    'success' => false,
                    'message' => 'Investigator not found'
                ], 404);
            }

            DB::beginTransaction();

            // Store investigator info before deletion
            $investigatorName = $investigator->name;
            $investigatorEmail = $investigator->email;
            $investigatorType = $assignment->investigator_type;

            // Delete the assignment completely
            $assignment->delete();

            DB::commit();

            // Log timeline event (after commit)
            try {
                $caseTrackingService = app(CaseTrackingService::class);
                $caseTrackingService->logInvestigatorUnassigned(
                    $case,
                    $investigator,
                    $user,
                    $removalReason
                );
            } catch (\Exception $e) {
                Log::warning('Failed to log investigator unassignment timeline event', [
                    'case_id' => $case->id,
                    'investigator_id' => $investigator->id,
                    'error' => $e->getMessage()
                ]);
            }

            // Send email notification
            try {
                Mail::to($investigatorEmail)->queue(
                    new InvestigatorRemovedFromCase($case, $investigator, $user, $removalReason)
                );
            } catch (\Exception $e) {
                Log::warning('Failed to send investigator removal email', [
                    'case_id' => $case->id,
                    'investigator_email' => $investigatorEmail,
                    'error' => $e->getMessage()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Investigator unassigned successfully',
                'data' => [
                    'investigator_name' => $investigatorName,
                    'investigator_type' => $investigatorType,
                    'removal_reason' => $removalReason
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to unassign investigator',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get all investigator assignments for a case with type information.
     */
    public function getCaseInvestigators(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            // Check authentication
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            // Ensure user has company access and proper role
            if ($user->role !== 'company_admin' || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only company admins can view case investigators.'
                ], 403);
            }

            // Verify case exists and belongs to company
            $case = CaseModel::where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (!$case) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case not found or access denied'
                ], 404);
            }

            // Get assignments with filters
            $query = CaseAssignment::where('case_id', $id)
                ->with([
                    'investigator:id,name,email,phone,role',
                    'assignedBy:id,name',
                    'unassignedBy:id,name'
                ]);

            // Filter by status if provided
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            } else {
                // Default to active assignments
                $query->where('status', 'active');
            }

            // Filter by investigator type if provided
            if ($request->filled('investigator_type')) {
                $query->where('investigator_type', $request->investigator_type);
            }

            $assignments = $query->orderBy('assigned_at', 'desc')->get();

            // Group by type for easier frontend consumption
            $internalAssignments = $assignments->where('investigator_type', 'internal')->values();
            $externalAssignments = $assignments->where('investigator_type', 'external')->values();
            $leadInvestigator = $assignments->where('is_lead_investigator', true)->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'case_id' => $id,
                    'case_token' => $case->case_token,
                    'all_assignments' => $assignments,
                    'internal_investigators' => $internalAssignments,
                    'external_investigators' => $externalAssignments,
                    'lead_investigator' => $leadInvestigator,
                    'summary' => [
                        'total' => $assignments->count(),
                        'active' => $assignments->where('status', 'active')->count(),
                        'internal_count' => $internalAssignments->count(),
                        'external_count' => $externalAssignments->count(),
                        'has_lead' => $leadInvestigator !== null
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve case investigators',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
