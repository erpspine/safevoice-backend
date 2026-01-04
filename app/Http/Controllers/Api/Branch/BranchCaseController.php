<?php

namespace App\Http\Controllers\Api\Branch;

use App\Http\Controllers\Controller;
use App\Models\CaseModel;
use App\Models\CaseAssignment;
use App\Models\CaseDepartment;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\Thread;
use App\Models\CaseMessage;
use App\Models\MessageRead;
use App\Models\User;
use App\Models\Department;
use App\Models\IncidentCategory;
use App\Models\FeedbackCategory;
use App\Mail\InvestigatorAssignedToCase;
use App\Mail\InvestigatorRemovedFromCase;
use App\Services\CaseTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BranchCaseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only branch admins can access this endpoint.'
                ], 403);
            }

            $branchId = $user->branch_id;

            $query = CaseModel::with([
                'company:id,name,email',
                'branch:id,name,location',
                'departments:id,name',
                'caseCategories.incidentCategory:id,name',
                'caseCategories.feedbackCategory:id,name',
                'caseCategories.assignedBy:id,name',
                'assignments.investigator:id,name,email'
            ])->where('branch_id', $branchId);

            if ($request->has('status') && $request->status !== '') {
                $query->where('status', $request->status);
            }

            if ($request->has('priority') && $request->priority !== '') {
                $query->where('priority', $request->priority);
            }

            if ($request->has('type') && $request->type !== '') {
                $query->where('type', $request->type);
            }

            if ($request->has('search') && $request->search !== '') {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'ILIKE', '%' . $search . '%')
                        ->orWhere('description', 'ILIKE', '%' . $search . '%')
                        ->orWhere('case_number', 'ILIKE', '%' . $search . '%');
                });
            }

            if ($request->has('date_from') && $request->date_from !== '') {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to') && $request->date_to !== '') {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $cases = $query->get();

            // Calculate statistics for the branch
            $statistics = $this->calculateBranchCaseStatistics($branchId);

            return response()->json([
                'success' => true,
                'message' => 'Cases retrieved successfully',
                'data' => $cases,
                'statistics' => $statistics
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve branch cases', [
                'error' => $e->getMessage(),
                'branch_id' => $user->branch_id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve cases',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Calculate case statistics for the branch.
     */
    private function calculateBranchCaseStatistics(string $branchId): array
    {
        // Total cases
        $totalCases = CaseModel::where('branch_id', $branchId)->count();

        // Cases by status
        $byStatus = CaseModel::where('branch_id', $branchId)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Cases by type (incident vs feedback)
        $byType = CaseModel::where('branch_id', $branchId)
            ->select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        // Cases with/without investigators
        $casesWithInvestigators = CaseModel::where('branch_id', $branchId)
            ->whereHas('assignments', function ($q) {
                $q->where('status', 'active');
            })
            ->count();

        $casesWithoutInvestigators = $totalCases - $casesWithInvestigators;

        // Cases with/without departments
        $casesWithDepartments = CaseModel::where('branch_id', $branchId)
            ->whereHas('departments')
            ->count();

        $casesWithoutDepartments = $totalCases - $casesWithDepartments;

        // Cases with/without categories
        $casesWithCategories = CaseModel::where('branch_id', $branchId)
            ->whereHas('caseCategories')
            ->count();

        $casesWithoutCategories = $totalCases - $casesWithCategories;

        // Cases by department (top 10)
        $byDepartment = DB::table('cases')
            ->join('case_departments', 'cases.id', '=', 'case_departments.case_id')
            ->join('departments', 'case_departments.department_id', '=', 'departments.id')
            ->where('cases.branch_id', $branchId)
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
            ->where('cases.branch_id', $branchId)
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

        // Priority breakdown
        $byPriority = CaseModel::where('branch_id', $branchId)
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
            'by_category' => $byCategory
        ];
    }

    public function dashboard(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied.'
                ], 403);
            }

            $branchId = $user->branch_id;

            $statusCounts = CaseModel::where('branch_id', $branchId)
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            $typeCounts = CaseModel::where('branch_id', $branchId)
                ->select('type', DB::raw('count(*) as count'))
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray();

            $casesByDepartment = CaseModel::where('cases.branch_id', $branchId)
                ->join('case_departments', 'cases.id', '=', 'case_departments.case_id')
                ->join('departments', 'case_departments.department_id', '=', 'departments.id')
                ->whereNull('case_departments.deleted_at')
                ->select('departments.name', DB::raw('count(*) as count'))
                ->groupBy('departments.name')
                ->pluck('count', 'name')
                ->toArray();

            $monthlyData = CaseModel::where('branch_id', $branchId)
                ->where('created_at', '>=', now()->subMonths(6))
                ->select(
                    DB::raw('TO_CHAR(created_at, \'YYYY-MM\') as month'),
                    DB::raw('count(*) as count')
                )
                ->groupBy(DB::raw('TO_CHAR(created_at, \'YYYY-MM\')'))
                ->orderBy('month')
                ->get()
                ->pluck('count', 'month')
                ->toArray();

            return response()->json([
                'success' => true,
                'message' => 'Branch dashboard data retrieved successfully',
                'data' => [
                    'status_counts' => $statusCounts,
                    'type_counts' => $typeCounts,
                    'cases_by_department' => [
                        'labels' => array_keys($casesByDepartment),
                        'data' => array_values($casesByDepartment)
                    ],
                    'monthly_new_reports' => [
                        'labels' => array_keys($monthlyData),
                        'data' => array_values($monthlyData)
                    ],
                    'branch_info' => [
                        'id' => $user->branch->id,
                        'name' => $user->branch->name,
                        'location' => $user->branch->location
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve branch dashboard data', [
                'error' => $e->getMessage(),
                'branch_id' => $user->branch_id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve dashboard data',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function getCasesWithThreads(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only branch admins can access this endpoint.'
                ], 403);
            }

            $query = CaseModel::with([
                'company:id,name,email',
                'branch:id,name,location',
                'departments:id,name',
                'caseCategories.incidentCategory:id,name',
                'caseCategories.feedbackCategory:id,name',
                'caseCategories.assignedBy:id,name',
                'assignments.investigator:id,name,email'
            ])->where('branch_id', $user->branch_id);

            // Apply filters
            if ($request->has('status') && $request->status !== '') {
                $query->where('status', $request->status);
            }

            if ($request->has('priority') && $request->priority !== '') {
                $query->where('priority', $request->priority);
            }

            if ($request->has('type') && $request->type !== '') {
                $query->where('type', $request->type);
            }

            if ($request->has('case_type') && $request->case_type !== '') {
                $query->where('case_type', $request->case_type);
            }

            if ($request->has('search') && $request->search !== '') {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'ILIKE', '%' . $search . '%')
                        ->orWhere('description', 'ILIKE', '%' . $search . '%')
                        ->orWhere('case_number', 'ILIKE', '%' . $search . '%');
                });
            }

            if ($request->has('date_from') && $request->date_from !== '') {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to') && $request->date_to !== '') {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Add thread statistics using subqueries
            $query->addSelect([
                'thread_count' => Thread::selectRaw('count(*)')
                    ->whereColumn('case_id', 'cases.id'),
                'total_messages' => CaseMessage::selectRaw('count(*)')
                    ->whereColumn('case_id', 'cases.id'),
                'unread_messages' => DB::table('case_messages')
                    ->selectRaw('count(*)')
                    ->whereColumn('case_id', 'cases.id')
                    ->whereNotExists(function ($query) use ($user) {
                        $query->select(DB::raw(1))
                            ->from('message_reads')
                            ->whereColumn('message_reads.message_id', 'case_messages.id')
                            ->where('message_reads.user_id', $user->id);
                    }),
                'latest_message_date' => CaseMessage::select('created_at')
                    ->whereColumn('case_id', 'cases.id')
                    ->latest()
                    ->limit(1),
                'active_threads' => Thread::selectRaw('count(*)')
                    ->whereColumn('case_id', 'cases.id')
                    ->where('status', 'active'),
                'closed_threads' => Thread::selectRaw('count(*)')
                    ->whereColumn('case_id', 'cases.id')
                    ->where('status', 'closed')
            ]);

            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->get('per_page', 15);
            $cases = $query->paginate($perPage);

            // Transform the data to include thread statistics
            $cases->getCollection()->transform(function ($case) {
                return [
                    'id' => $case->id,
                    'case_token' => $case->case_token,
                    'case_number' => $case->case_number,
                    'title' => $case->title,
                    'type' => $case->type,
                    'case_type' => $case->case_type,
                    'status' => $case->status,
                    'priority' => $case->priority,
                    'created_at' => $case->created_at,
                    'updated_at' => $case->updated_at,
                    'company' => $case->company,
                    'branch' => $case->branch,
                    'departments' => $case->departments,
                    'categories' => $case->caseCategories->map(function ($caseCategory) {
                        $category = $caseCategory->category_type === 'incident'
                            ? $caseCategory->incidentCategory
                            : $caseCategory->feedbackCategory;
                        return [
                            'id' => $category->id ?? null,
                            'name' => $category->name ?? null,
                            'type' => $caseCategory->category_type,
                            'assigned_by' => $caseCategory->assignedBy,
                            'assigned_at' => $caseCategory->assigned_at
                        ];
                    }),
                    'assignments' => $case->assignments,
                    'thread_statistics' => [
                        'total_threads' => (int) $case->thread_count,
                        'active_threads' => (int) $case->active_threads,
                        'closed_threads' => (int) $case->closed_threads,
                        'total_messages' => (int) $case->total_messages,
                        'unread_messages' => (int) $case->unread_messages,
                        'latest_message_date' => $case->latest_message_date,
                        'has_unread_messages' => (int) $case->unread_messages > 0,
                        'has_active_threads' => (int) $case->active_threads > 0
                    ]
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Cases with thread statistics retrieved successfully',
                'data' => $cases
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve cases with thread statistics', [
                'error' => $e->getMessage(),
                'branch_id' => $user->branch_id ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve cases with thread statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function show(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied.'
                ], 403);
            }

            $case = CaseModel::with([
                'company:id,name,email,contact,address',
                'branch:id,name,location',
                'departments:id,name,description',
                'caseCategories.incidentCategory:id,name,description',
                'caseCategories.feedbackCategory:id,name,description',
                'caseCategories.assignedBy:id,name',
                'assignments.investigator:id,name,email,phone',
                'assignments.assignedBy:id,name',
                'files'
            ])->where('branch_id', $user->branch_id);

            // Add thread statistics
            $case->addSelect([
                'thread_count' => Thread::selectRaw('count(*)')
                    ->whereColumn('case_id', 'cases.id'),
                'total_messages' => CaseMessage::selectRaw('count(*)')
                    ->whereColumn('case_id', 'cases.id'),
                'unread_messages' => DB::table('case_messages')
                    ->selectRaw('count(*)')
                    ->whereColumn('case_id', 'cases.id')
                    ->whereNotExists(function ($query) use ($user) {
                        $query->select(DB::raw(1))
                            ->from('message_reads')
                            ->whereColumn('message_reads.message_id', 'case_messages.id')
                            ->where('message_reads.user_id', $user->id);
                    }),
                'latest_message_date' => CaseMessage::select('created_at')
                    ->whereColumn('case_id', 'cases.id')
                    ->latest()
                    ->limit(1),
                'active_threads' => Thread::selectRaw('count(*)')
                    ->whereColumn('case_id', 'cases.id')
                    ->where('status', 'active'),
                'closed_threads' => Thread::selectRaw('count(*)')
                    ->whereColumn('case_id', 'cases.id')
                    ->where('status', 'closed')
            ]);

            $case = $case->find($id);

            if (!$case) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case not found or access denied'
                ], 404);
            }

            // Format the response with thread statistics and categories
            $caseData = $case->toArray();
            $caseData['thread_statistics'] = [
                'total_threads' => (int) $case->thread_count,
                'active_threads' => (int) $case->active_threads,
                'closed_threads' => (int) $case->closed_threads,
                'total_messages' => (int) $case->total_messages,
                'unread_messages' => (int) $case->unread_messages,
                'latest_message_date' => $case->latest_message_date,
                'has_unread_messages' => (int) $case->unread_messages > 0,
                'has_active_threads' => (int) $case->active_threads > 0
            ];

            // Format categories properly
            $caseData['categories'] = $case->caseCategories->map(function ($caseCategory) {
                $category = $caseCategory->category_type === 'incident'
                    ? $caseCategory->incidentCategory
                    : $caseCategory->feedbackCategory;
                return [
                    'id' => $category->id ?? null,
                    'name' => $category->name ?? null,
                    'description' => $category->description ?? null,
                    'type' => $caseCategory->category_type,
                    'assigned_by' => $caseCategory->assignedBy,
                    'assigned_at' => $caseCategory->assigned_at
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Case retrieved successfully',
                'data' => $caseData
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve case details', [
                'error' => $e->getMessage(),
                'case_id' => $id,
                'branch_id' => $user->branch_id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve case details',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function getCaseThreadActivity(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied.'
                ], 403);
            }

            $case = CaseModel::where('branch_id', $user->branch_id)->find($id);

            if (!$case) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case not found or access denied'
                ], 404);
            }

            // Get recent threads with message counts
            $threads = Thread::with([
                'creator:id,name,email',
                'investigator:id,name,email'
            ])
                ->where('case_id', $id)
                ->withCount([
                    'messages',
                    'messages as unread_count' => function ($query) use ($user) {
                        $query->whereNotExists(function ($q) use ($user) {
                            $q->select(DB::raw(1))
                                ->from('message_reads')
                                ->whereColumn('message_reads.message_id', 'messages.id')
                                ->where('message_reads.user_id', $user->id);
                        });
                    }
                ])
                ->addSelect([
                    'latest_message_date' => CaseMessage::select('created_at')
                        ->whereColumn('thread_id', 'threads.id')
                        ->latest()
                        ->limit(1),
                    'latest_message_content' => CaseMessage::select('message')
                        ->whereColumn('thread_id', 'threads.id')
                        ->latest()
                        ->limit(1)
                ])
                ->orderBy('updated_at', 'desc')
                ->get();

            // Get recent messages across all threads for this case
            $recentMessages = CaseMessage::with([
                'thread:id,title,description',
                'sender'
            ])
                ->where('case_id', $id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Case thread activity retrieved successfully',
                'data' => [
                    'case_info' => [
                        'id' => $case->id,
                        'case_number' => $case->case_number,
                        'title' => $case->title,
                        'status' => $case->status
                    ],
                    'threads' => $threads->map(function ($thread) {
                        return [
                            'id' => $thread->id,
                            'title' => $thread->title,
                            'description' => $thread->description,
                            'status' => $thread->status,
                            'created_at' => $thread->created_at,
                            'updated_at' => $thread->updated_at,
                            'creator' => $thread->creator,
                            'investigator' => $thread->investigator,
                            'message_count' => $thread->messages_count,
                            'unread_count' => $thread->unread_count,
                            'latest_message_date' => $thread->latest_message_date,
                            'latest_message_preview' => $thread->latest_message_content ?
                                (strlen($thread->latest_message_content) > 100 ?
                                    substr($thread->latest_message_content, 0, 100) . '...' :
                                    $thread->latest_message_content) : null,
                            'has_unread_messages' => $thread->unread_count > 0
                        ];
                    }),
                    'recent_messages' => $recentMessages->map(function ($message) {
                        return [
                            'id' => $message->id,
                            'message' => strlen($message->message) > 150 ?
                                substr($message->message, 0, 150) . '...' :
                                $message->message,
                            'sender_type' => $message->sender_type,
                            'sender' => $message->sender,
                            'thread' => $message->thread,
                            'created_at' => $message->created_at,
                            'has_attachments' => $message->has_attachments
                        ];
                    })
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve case thread activity', [
                'error' => $e->getMessage(),
                'case_id' => $id,
                'branch_id' => $user->branch_id ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve case thread activity',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied.'
                ], 403);
            }

            $case = CaseModel::where('branch_id', $user->branch_id)->find($id);

            if (!$case) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case not found or access denied'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'status' => 'sometimes|in:open,in_progress,pending,resolved,closed',
                'priority' => 'sometimes|integer|between:1,4',
                'case_close_classification' => 'required_if:status,closed|in:substantiated,partially_substantiated,unsubstantiated',
                'resolution_note' => 'required_if:status,closed|string|max:2000',
                'internal_notes' => 'sometimes|string|max:2000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = $request->only(['status', 'priority', 'internal_notes']);

            if ($request->status === 'closed') {
                $updateData['case_close_classification'] = $request->case_close_classification;
                $updateData['resolution_note'] = $request->resolution_note;
                $updateData['case_closed_at'] = now();
                $updateData['closed_by'] = $user->id;
            }

            $case->update($updateData);

            $case->load([
                'company:id,name,email',
                'branch:id,name,location',
                'departments:id,name',
                'caseCategories.incidentCategory:id,name',
                'caseCategories.feedbackCategory:id,name',
                'caseCategories.assignedBy:id,name',
                'assignments.investigator:id,name,email'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Case updated successfully',
                'data' => $case
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update case', [
                'error' => $e->getMessage(),
                'case_id' => $id,
                'branch_id' => $user->branch_id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update case',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get available investigators (both internal and external) for case assignment.
     * 
     * Internal: Branch admins from the same branch who are not involved parties
     * External: Investigators assigned to the company
     * 
     * @param Request $request
     * @param string|null $caseId Optional case ID to exclude involved parties
     */
    public function availableInvestigators(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied.'
                ], 403);
            }

            $caseId = $request->query('case_id');
            $type = $request->query('type'); // 'internal', 'external', or null for both

            // Get involved party user IDs if case_id is provided
            $involvedUserIds = [];
            if ($caseId) {
                $case = CaseModel::where('id', $caseId)
                    ->where('branch_id', $user->branch_id)
                    ->first();

                if ($case) {
                    $involvedUserIds = $case->involvedParties()->pluck('employee_id')->toArray();
                }
            }

            $response = [];

            // Get internal investigators (branch admins not involved)
            if (!$type || $type === 'internal') {
                $internalQuery = User::where('role', User::ROLE_BRANCH_ADMIN)
                    ->where('branch_id', $user->branch_id)
                    ->where('status', 'active')
                    ->where('is_verified', true)
                    ->where('id', '!=', $user->id); // Exclude current user

                // Exclude involved parties
                if (!empty($involvedUserIds)) {
                    $internalQuery->whereNotIn('id', $involvedUserIds);
                }

                // Exclude already assigned internal investigators for this case
                if ($caseId) {
                    $assignedInternalIds = CaseAssignment::where('case_id', $caseId)
                        ->where('investigator_type', CaseAssignment::TYPE_INTERNAL)
                        ->where('status', 'active')
                        ->pluck('investigator_id')
                        ->toArray();

                    if (!empty($assignedInternalIds)) {
                        $internalQuery->whereNotIn('id', $assignedInternalIds);
                    }
                }

                $response['internal'] = $internalQuery
                    ->select('id', 'name', 'email', 'phone', 'employee_id', 'role')
                    ->get()
                    ->map(function ($user) {
                        return [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'phone' => $user->phone,
                            'employee_id' => $user->employee_id,
                            'role' => $user->role,
                            'investigator_type' => 'internal',
                            'type_label' => 'Internal (Branch Admin)',
                        ];
                    });
            }

            // Get external investigators (investigators assigned to this company via investigator_company pivot)
            if (!$type || $type === 'external') {
                $companyId = $user->company_id;

                $externalQuery = User::where('role', User::ROLE_INVESTIGATOR)
                    ->where('status', 'active')
                    ->where('is_verified', true)
                    ->whereHas('investigator', function ($q) use ($companyId) {
                        $q->where('status', true)
                            ->whereHas('companies', function ($q2) use ($companyId) {
                                $q2->where('companies.id', $companyId);
                            });
                    });

                // Exclude involved parties
                if (!empty($involvedUserIds)) {
                    $externalQuery->whereNotIn('id', $involvedUserIds);
                }

                // Exclude already assigned external investigators for this case
                if ($caseId) {
                    $assignedExternalIds = CaseAssignment::where('case_id', $caseId)
                        ->where('investigator_type', CaseAssignment::TYPE_EXTERNAL)
                        ->where('status', 'active')
                        ->pluck('investigator_id')
                        ->toArray();

                    if (!empty($assignedExternalIds)) {
                        $externalQuery->whereNotIn('id', $assignedExternalIds);
                    }
                }

                $response['external'] = $externalQuery
                    ->select('id', 'name', 'email', 'phone', 'employee_id', 'role')
                    ->get()
                    ->map(function ($user) {
                        return [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'phone' => $user->phone,
                            'employee_id' => $user->employee_id,
                            'role' => $user->role,
                            'investigator_type' => 'external',
                            'type_label' => 'External Investigator',
                        ];
                    });
            }

            // Combined list for convenience
            $response['all'] = collect($response['internal'] ?? [])
                ->merge($response['external'] ?? [])
                ->values();

            return response()->json([
                'success' => true,
                'message' => 'Available investigators retrieved successfully',
                'data' => $response,
                'meta' => [
                    'internal_count' => count($response['internal'] ?? []),
                    'external_count' => count($response['external'] ?? []),
                    'total_count' => count($response['all']),
                    'case_id' => $caseId,
                    'excluded_involved_parties' => count($involvedUserIds),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve available investigators', [
                'error' => $e->getMessage(),
                'branch_id' => $user->branch_id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve available investigators',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function statistics(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied.'
                ], 403);
            }

            $branchId = $user->branch_id;

            $stats = [
                'total_cases' => CaseModel::where('branch_id', $branchId)->count(),
                'incident_cases' => CaseModel::where('branch_id', $branchId)->where('type', 'incident')->count(),
                'feedback_cases' => CaseModel::where('branch_id', $branchId)->where('type', 'feedback')->count(),
                'open_cases' => CaseModel::where('branch_id', $branchId)->where('status', 'open')->count(),
                'in_progress_cases' => CaseModel::where('branch_id', $branchId)->where('status', 'in_progress')->count(),
                'pending_cases' => CaseModel::where('branch_id', $branchId)->where('status', 'pending')->count(),
                'resolved_cases' => CaseModel::where('branch_id', $branchId)->where('status', 'resolved')->count(),
                'closed_cases' => CaseModel::where('branch_id', $branchId)->where('status', 'closed')->count(),
                'high_priority_cases' => CaseModel::where('branch_id', $branchId)->where('priority', 'high')->count(),
                'critical_priority_cases' => CaseModel::where('branch_id', $branchId)->where('priority', 'critical')->count(),
                'cases_this_month' => CaseModel::where('branch_id', $branchId)
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
                'cases_last_month' => CaseModel::where('branch_id', $branchId)
                    ->whereMonth('created_at', now()->subMonth()->month)
                    ->whereYear('created_at', now()->subMonth()->year)
                    ->count()
            ];

            return response()->json([
                'success' => true,
                'message' => 'Branch case statistics retrieved successfully',
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve branch case statistics', [
                'error' => $e->getMessage(),
                'branch_id' => $user->branch_id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
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

            if ($user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only branch admins can assign departments.'
                ], 403);
            }

            $case = CaseModel::where('id', $id)->where('branch_id', $user->branch_id)->first();

            if (!$case) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case not found or access denied'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'department_ids' => 'required|array|min:1',
                'department_ids.*' => 'required|string|exists:departments,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $assignedDepartments = [];
            foreach ($request->department_ids as $departmentId) {
                // Check if department belongs to company
                $department = Department::where('id', $departmentId)
                    ->where('company_id', $user->company_id)
                    ->first();

                if (!$department) {
                    continue;
                }

                // Check if already assigned
                $existing = CaseDepartment::where('case_id', $id)
                    ->where('department_id', $departmentId)
                    ->first();

                if (!$existing) {
                    $caseDepartment = CaseDepartment::create([
                        'case_id' => $id,
                        'department_id' => $departmentId,
                        'assigned_by' => $user->id,
                        'assigned_at' => now()
                    ]);

                    $caseDepartment->load('department:id,name');
                    $assignedDepartments[] = $caseDepartment;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Departments assigned successfully',
                'data' => $assignedDepartments
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign departments',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get case departments.
     */
    public function getCaseDepartments(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied.'
                ], 403);
            }

            $case = CaseModel::where('id', $id)->where('branch_id', $user->branch_id)->first();

            if (!$case) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case not found or access denied'
                ], 404);
            }

            $caseDepartments = CaseDepartment::with(['department:id,name,description', 'assignedBy:id,name'])
                ->where('case_id', $id)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Case departments retrieved successfully',
                'data' => $caseDepartments
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
     * Remove a department from a case.
     */
    public function unassignDepartment(Request $request, string $id, string $departmentId): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied.'
                ], 403);
            }

            $case = CaseModel::where('id', $id)->where('branch_id', $user->branch_id)->first();

            if (!$case) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case not found or access denied'
                ], 404);
            }

            $caseDepartment = CaseDepartment::where('case_id', $id)
                ->where('department_id', $departmentId)
                ->first();

            if (!$caseDepartment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Department assignment not found'
                ], 404);
            }

            $caseDepartment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Department unassigned successfully'
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
     * Assign categories to a case.
     */
    public function assignCategories(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only branch admins can assign categories.'
                ], 403);
            }

            $case = CaseModel::where('id', $id)->where('branch_id', $user->branch_id)->first();

            if (!$case) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case not found or access denied'
                ], 404);
            }

            // Determine category type from case type
            $categoryType = $case->type; // 'incident' or 'feedback'

            $validator = Validator::make($request->all(), [
                'category_ids' => 'required|array|min:1',
                'category_ids.*' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $assignedCategories = [];
            foreach ($request->category_ids as $categoryId) {
                // Verify category exists and belongs to company based on case type
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
                $existing = CaseCategory::where('case_id', $id)
                    ->where('category_id', $categoryId)
                    ->where('category_type', $categoryType)
                    ->first();

                if (!$existing) {
                    $caseCategory = CaseCategory::create([
                        'case_id' => $id,
                        'category_id' => $categoryId,
                        'category_type' => $categoryType,
                        'assigned_by' => $user->id,
                        'assigned_at' => now()
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
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign categories',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get case categories.
     */
    public function getCaseCategories(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied.'
                ], 403);
            }

            $case = CaseModel::where('id', $id)->where('branch_id', $user->branch_id)->first();

            if (!$case) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case not found or access denied'
                ], 404);
            }

            $caseCategories = CaseCategory::with(['assignedBy:id,name'])
                ->where('case_id', $id)
                ->get();

            // Load category details based on type
            $caseCategories->load(['incidentCategory:id,name,description', 'feedbackCategory:id,name,description']);

            return response()->json([
                'success' => true,
                'message' => 'Case categories retrieved successfully',
                'data' => $caseCategories
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
     * Remove a category from a case.
     */
    public function unassignCategory(Request $request, string $id, string $categoryId): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied.'
                ], 403);
            }

            $case = CaseModel::where('id', $id)->where('branch_id', $user->branch_id)->first();

            if (!$case) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case not found or access denied'
                ], 404);
            }

            $caseCategory = CaseCategory::where('case_id', $id)
                ->where('category_id', $categoryId)
                ->first();

            if (!$caseCategory) {
                return response()->json([
                    'success' => false,
                    'message' => 'Category assignment not found'
                ], 404);
            }

            $caseCategory->delete();

            return response()->json([
                'success' => true,
                'message' => 'Category unassigned successfully'
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
     * Assign investigators to a case.
     * Supports both internal (branch_admin) and external (investigator role) investigators.
     */
    public function assignInvestigators(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only branch admins can assign investigators.'
                ], 403);
            }

            $case = CaseModel::where('id', $id)->where('branch_id', $user->branch_id)->first();

            if (!$case) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case not found or access denied'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'investigators' => 'required|array|min:1',
                'investigators.*.investigator_id' => 'required|string|exists:users,id',
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
                    // Internal: branch_admin from same branch who is NOT involved in the case
                    $investigator = User::where('id', $investigatorData['investigator_id'])
                        ->where('branch_id', $user->branch_id)
                        ->where('role', User::ROLE_BRANCH_ADMIN)
                        ->where('status', 'active')
                        ->first();

                    if (!$investigator) {
                        $errors[] = "Internal investigator {$investigatorData['investigator_id']} not found or not a branch admin in your branch";
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
     * Get case investigators with type information.
     */
    public function getCaseInvestigators(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied.'
                ], 403);
            }

            $case = CaseModel::where('id', $id)->where('branch_id', $user->branch_id)->first();

            if (!$case) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case not found or access denied'
                ], 404);
            }

            $assignments = CaseAssignment::with(['investigator:id,name,email,phone,role', 'assignedBy:id,name'])
                ->where('case_id', $id)
                ->get();

            // Group by type for easier frontend consumption
            $internalAssignments = $assignments->where('investigator_type', 'internal')->values();
            $externalAssignments = $assignments->where('investigator_type', 'external')->values();
            $leadInvestigator = $assignments->where('is_lead_investigator', true)->first();

            return response()->json([
                'success' => true,
                'message' => 'Case investigators retrieved successfully',
                'data' => [
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

    /**
     * Remove an investigator assignment from a case.
     */
    public function unassignInvestigator(Request $request, string $id, string $assignmentId): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied.'
                ], 403);
            }

            $case = CaseModel::where('id', $id)
                ->where('branch_id', $user->branch_id)
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

            // Verify investigator belongs to company (for external) or branch (for internal)
            if ($assignment->investigator_type === 'external') {
                // External investigators are validated via investigator_company pivot
                // Just ensure we have the investigator loaded
                if (!$investigator) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Investigator not found'
                    ], 404);
                }
            } else {
                // Internal investigators must be from the same branch
                if ($investigator->branch_id !== $user->branch_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Access denied'
                    ], 403);
                }
            }

            DB::beginTransaction();

            // Store investigator info before deletion
            $investigatorName = $investigator->name;
            $investigatorEmail = $investigator->email;
            $investigatorType = $assignment->investigator_type;

            // Delete the assignment
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
     * Get case files.
     */
    public function getCaseFiles(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied.'
                ], 403);
            }

            $case = CaseModel::where('id', $id)->where('branch_id', $user->branch_id)->first();

            if (!$case) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case not found or access denied'
                ], 404);
            }

            $files = CaseFile::where('case_id', $id)
                ->select('id', 'original_name', 'file_type', 'file_size', 'description', 'is_confidential', 'created_at')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Case files retrieved successfully',
                'data' => $files
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
     * Get case timeline/tracking information.
     */
    public function timeline(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only branch admins can view case timeline.'
                ], 403);
            }

            $case = CaseModel::where('id', $id)
                ->where('branch_id', $user->branch_id)
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

            if ($user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only branch admins can view case duration.'
                ], 403);
            }

            $case = CaseModel::where('id', $id)
                ->where('branch_id', $user->branch_id)
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

            if ($user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only branch admins can view case escalations.'
                ], 403);
            }

            $case = CaseModel::where('id', $id)
                ->where('branch_id', $user->branch_id)
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

            if ($user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only branch admins can view case tracking.'
                ], 403);
            }

            $case = CaseModel::where('id', $id)
                ->where('branch_id', $user->branch_id)
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
}
