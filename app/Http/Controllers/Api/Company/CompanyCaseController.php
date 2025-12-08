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
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

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

            $query = CaseModel::where('company_id', $user->company_id);

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
                'incidentCategories:id,name',
                'feedbackCategories:id,name',
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

            return response()->json([
                'success' => true,
                'data' => $cases
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
                    'assignments.assignedByUser:id,name',
                    'departments:id,name,description',
                    'caseDepartments.assignedBy:id,name',
                    'incidentCategories:id,name,description',
                    'feedbackCategories:id,name,description',
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

            // Get investigators from the same company
            $investigators = User::where('company_id', $user->company_id)
                ->where('role', 'investigator')
                ->where('status', 'active')
                ->select('id', 'name', 'email', 'phone')
                ->orderBy('name')
                ->get()
                ->map(function ($investigator) use ($user) {
                    $activeCases = CaseModel::where('assigned_to', $investigator->id)
                        ->where('company_id', $user->company_id)
                        ->whereIn('status', ['open', 'in_progress', 'pending'])
                        ->count();

                    return [
                        'id' => $investigator->id,
                        'name' => $investigator->name,
                        'email' => $investigator->email,
                        'phone' => $investigator->phone,
                        'active_cases' => $activeCases
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $investigators
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

            // Build timeline
            $timeline = [
                [
                    'stage' => 'Submitted',
                    'status' => 'completed',
                    'date' => $case->created_at,
                    'description' => 'Case submitted to the system'
                ]
            ];

            // Check assignment
            if ($case->assigned_to) {
                $timeline[] = [
                    'stage' => 'Assigned',
                    'status' => 'completed',
                    'date' => $case->updated_at,
                    'description' => 'Case assigned to investigator',
                    'assignee' => $case->assignee ? $case->assignee->name : 'Unknown'
                ];
            } else {
                $timeline[] = [
                    'stage' => 'Assignment Pending',
                    'status' => 'pending',
                    'date' => null,
                    'description' => 'Awaiting investigator assignment'
                ];
            }

            // Check investigation status
            if ($case->status === 'in_progress') {
                $timeline[] = [
                    'stage' => 'Under Investigation',
                    'status' => 'active',
                    'date' => $case->updated_at,
                    'description' => 'Case is being investigated'
                ];
            } elseif ($case->status === 'open') {
                $timeline[] = [
                    'stage' => 'Investigation',
                    'status' => 'pending',
                    'date' => null,
                    'description' => 'Investigation not yet started'
                ];
            } else {
                $timeline[] = [
                    'stage' => 'Investigation',
                    'status' => 'completed',
                    'date' => $case->updated_at,
                    'description' => 'Investigation completed'
                ];
            }

            // Check resolution status
            if (in_array($case->status, ['resolved', 'closed'])) {
                $timeline[] = [
                    'stage' => 'Closed',
                    'status' => 'completed',
                    'date' => $case->resolved_at,
                    'description' => 'Case has been ' . $case->status,
                    'resolution_note' => $case->resolution_note
                ];
            } else {
                $timeline[] = [
                    'stage' => 'Resolution',
                    'status' => 'pending',
                    'date' => null,
                    'description' => 'Case not yet resolved'
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'case_id' => $case->id,
                    'case_number' => $case->case_token,
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

            // Validate request
            $validator = Validator::make($request->all(), [
                'categories' => 'required|array|min:1',
                'categories.*.category_id' => 'required|string',
                'categories.*.category_type' => 'required|in:incident,feedback',
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

            DB::beginTransaction();
            try {
                // Verify and assign categories
                foreach ($request->categories as $categoryData) {
                    $categoryType = $categoryData['category_type'];
                    $categoryId = $categoryData['category_id'];

                    // Verify category belongs to the company
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
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => "Category {$categoryId} not found or does not belong to your company"
                        ], 422);
                    }

                    // Assign category
                    CaseCategory::updateOrCreate(
                        [
                            'case_id' => $case->id,
                            'category_id' => $categoryId,
                            'category_type' => $categoryType
                        ],
                        [
                            'assigned_at' => now(),
                            'assigned_by' => $user->id,
                            'assignment_note' => $request->assignment_note
                        ]
                    );
                }

                DB::commit();

                // Load updated relationships
                $case->load([
                    'incidentCategories:id,name,description',
                    'feedbackCategories:id,name,description',
                    'caseCategories.assignedBy:id,name'
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Categories assigned successfully',
                    'data' => $case
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
                'investigators.*.assignment_type' => 'sometimes|in:primary,secondary,support,consultant',
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

            DB::beginTransaction();

            $assignedInvestigators = [];
            $errors = [];

            foreach ($request->investigators as $investigatorData) {
                // Verify investigator belongs to company and has investigator role
                $investigator = User::where('id', $investigatorData['investigator_id'])
                    ->where('company_id', $user->company_id)
                    ->where('role', 'investigator')
                    ->first();

                if (!$investigator) {
                    $errors[] = "Investigator {$investigatorData['investigator_id']} not found or doesn't belong to your company";
                    continue;
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

                // Create assignment
                $assignment = CaseAssignment::create([
                    'case_id' => $id,
                    'investigator_id' => $investigator->id,
                    'assigned_by_user_id' => $user->id,
                    'assigned_at' => now(),
                    'assignment_type' => $investigatorData['assignment_type'] ?? 'primary',
                    'priority_level' => $investigatorData['priority_level'] ?? 2,
                    'assignment_note' => $investigatorData['assignment_note'] ?? null,
                    'estimated_hours' => $investigatorData['estimated_hours'] ?? null,
                    'deadline' => isset($investigatorData['deadline']) ? $investigatorData['deadline'] : null,
                    'status' => 'active'
                ]);

                $assignment->load(['investigator:id,name,email,phone', 'assignedByUser:id,name']);
                $assignedInvestigators[] = $assignment;
            }

            // Update case status to "in_progress" if investigators were assigned
            if (count($assignedInvestigators) > 0 && $case->status === 'open') {
                $case->update(['status' => 'in_progress']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($assignedInvestigators) > 0
                    ? 'Investigators assigned successfully'
                    : 'No investigators were assigned',
                'data' => [
                    'assigned' => $assignedInvestigators,
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
                ->first();

            if (!$case) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case not found or access denied'
                ], 404);
            }

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

            // Verify investigator belongs to company
            if ($assignment->investigator->company_id !== $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied'
                ], 403);
            }

            DB::beginTransaction();

            // Delete the assignment completely
            $assignment->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Investigator unassigned successfully',
                'data' => $assignment->load('unassignedByUser:id,name')
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
     * Get all investigator assignments for a case.
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
                    'investigator:id,name,email,phone',
                    'assignedByUser:id,name',
                    'unassignedByUser:id,name'
                ]);

            // Filter by status if provided
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            } else {
                // Default to active assignments
                $query->where('status', 'active');
            }

            $assignments = $query->orderBy('assigned_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'case_id' => $id,
                    'case_token' => $case->case_token,
                    'assignments' => $assignments,
                    'total_assignments' => $assignments->count(),
                    'active_count' => $assignments->where('status', 'active')->count()
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
