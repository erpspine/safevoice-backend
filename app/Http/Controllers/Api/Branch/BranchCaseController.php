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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

            $query = CaseModel::with([
                'company:id,name,email',
                'branch:id,name,location',
                'departments:id,name',
                'caseCategories.incidentCategory:id,name',
                'caseCategories.feedbackCategory:id,name',
                'caseCategories.assignedBy:id,name',
                'assignments.investigator:id,name,email'
            ])->where('branch_id', $user->branch_id);

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

            return response()->json([
                'success' => true,
                'message' => 'Cases retrieved successfully',
                'data' => $cases
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

            $investigators = User::where('role', 'investigator')
                ->where('company_id', $user->company_id)
                ->where('status', 'active')
                ->select('id', 'name', 'email', 'phone', 'employee_id')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Available investigators retrieved successfully',
                'data' => $investigators
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
                    'assigned_by' => $user->id,
                    'assigned_at' => now(),
                    'assignment_type' => $investigatorData['assignment_type'] ?? 'primary',
                    'priority_level' => $investigatorData['priority_level'] ?? 2,
                    'assignment_note' => $investigatorData['assignment_note'] ?? null,
                    'estimated_hours' => $investigatorData['estimated_hours'] ?? null,
                    'deadline' => isset($investigatorData['deadline']) ? $investigatorData['deadline'] : null,
                    'status' => 'active'
                ]);

                $assignment->load(['investigator:id,name,email,phone', 'assignedBy:id,name']);
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
     * Get case investigators.
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

            $assignments = CaseAssignment::with(['investigator:id,name,email,phone', 'assignedBy:id,name'])
                ->where('case_id', $id)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Case investigators retrieved successfully',
                'data' => $assignments
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

            $case = CaseModel::where('id', $id)->where('branch_id', $user->branch_id)->first();

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

            // Delete the assignment
            $assignment->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Investigator unassigned successfully'
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
}
