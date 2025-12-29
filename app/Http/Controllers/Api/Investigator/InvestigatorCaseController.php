<?php

namespace App\Http\Controllers\Api\Investigator;

use App\Http\Controllers\Controller;
use App\Models\CaseAssignment;
use App\Models\CaseModel;
use App\Models\CaseFile;
use App\Models\Thread;
use App\Models\CaseMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InvestigatorCaseController extends Controller
{
    /**
     * Get cases assigned to the investigator
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            $query = CaseAssignment::where('investigator_id', $user->id)
                ->with([
                    'case' => function ($q) {
                        $q->select('id', 'case_token', 'title', 'description', 'status', 'priority', 'created_at', 'company_id', 'branch_id');
                    },
                    'case.company:id,name,logo',
                    'case.branch:id,name',
                    'case.caseCategories.incidentCategory:id,name',
                    'case.caseCategories.feedbackCategory:id,name',
                    'case.assignments' => function ($q) {
                        $q->with('investigator:id,name,email');
                    }
                ]);

            // Apply filters
            if ($request->filled('status')) {
                $statuses = explode(',', $request->status);
                $query->whereHas('case', function ($q) use ($statuses) {
                    $q->whereIn('status', $statuses);
                });
            }

            if ($request->filled('priority')) {
                $priorities = explode(',', $request->priority);
                $query->whereHas('case', function ($q) use ($priorities) {
                    $q->whereIn('priority', $priorities);
                });
            }

            if ($request->filled('company_id')) {
                $query->whereHas('case', function ($q) use ($request) {
                    $q->where('company_id', $request->company_id);
                });
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->whereHas('case', function ($q) use ($search) {
                    $q->where('case_token', 'ILIKE', '%' . $search . '%')
                        ->orWhere('title', 'ILIKE', '%' . $search . '%')
                        ->orWhere('description', 'ILIKE', '%' . $search . '%');
                });
            }

            if ($request->filled('date_from')) {
                $query->whereHas('case', function ($q) use ($request) {
                    $q->whereDate('created_at', '>=', $request->date_from);
                });
            }

            if ($request->filled('date_to')) {
                $query->whereHas('case', function ($q) use ($request) {
                    $q->whereDate('created_at', '<=', $request->date_to);
                });
            }

            // Apply sorting
            $sortField = $request->get('sort_field', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');

            if (in_array($sortField, ['created_at', 'updated_at'])) {
                $query->orderBy($sortField, $sortDirection);
            } else {
                // For case fields, we need to join
                $query->join('cases', 'investigator_case_assignments.case_id', '=', 'cases.id')
                    ->orderBy('cases.' . $sortField, $sortDirection)
                    ->select('investigator_case_assignments.*');
            }

            // Pagination
            $perPage = min($request->get('per_page', 15), 100);
            $assignments = $query->paginate($perPage);

            $cases = $assignments->getCollection()->map(function ($assignment) {
                $case = $assignment->case;

                return [
                    'assignment_id' => $assignment->id,
                    'case' => [
                        'id' => $case->id,
                        'case_token' => $case->case_token,
                        'title' => $case->title ? $case->title : 'Untitled Case',
                        'description' => $case->description,
                        'status' => $case->status,
                        'priority' => $case->priority,
                        'deadline' => $case->deadline,
                        'is_overdue' => $case->deadline && $case->deadline < now(),
                        'days_until_deadline' => $case->deadline ? now()->diffInDays($case->deadline, false) : null,
                        'created_at' => $case->created_at,
                        'company' => [
                            'id' => $case->company->id,
                            'name' => $case->company->name,
                            'logo' => $case->company->logo,
                        ],
                        'branch' => $case->branch ? [
                            'id' => $case->branch->id,
                            'name' => $case->branch->name,
                        ] : null,
                        'categories' => $case->caseCategories->map(function ($caseCategory) {
                            if ($caseCategory->category_type === 'incident' && $caseCategory->incidentCategory) {
                                return [
                                    'id' => $caseCategory->incidentCategory->id,
                                    'name' => $caseCategory->incidentCategory->name,
                                    'color' => $caseCategory->incidentCategory->color,
                                    'type' => 'incident',
                                ];
                            } elseif ($caseCategory->category_type === 'feedback' && $caseCategory->feedbackCategory) {
                                return [
                                    'id' => $caseCategory->feedbackCategory->id,
                                    'name' => $caseCategory->feedbackCategory->name,
                                    'color' => $caseCategory->feedbackCategory->color,
                                    'type' => 'feedback',
                                ];
                            }
                            return null;
                        })->filter(),
                        'investigators' => $case->assignments ? $case->assignments->map(function ($assign) {
                            return [
                                'id' => $assign->investigator->id,
                                'name' => $assign->investigator->name,
                                'email' => $assign->investigator->email,
                                'assigned_at' => $assign->created_at,
                            ];
                        }) : [],
                    ],
                    'assigned_at' => $assignment->created_at,
                    'role' => $assignment->role,
                    'notes' => $assignment->notes,
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'cases' => $cases,
                    'pagination' => [
                        'current_page' => $assignments->currentPage(),
                        'last_page' => $assignments->lastPage(),
                        'per_page' => $assignments->perPage(),
                        'total' => $assignments->total(),
                        'from' => $assignments->firstItem(),
                        'to' => $assignments->lastItem(),
                    ]
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Investigator cases index error', [
                'user_id' => $request->user()->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve cases'
            ], 500);
        }
    }

    /**
     * Get cases with thread statistics for investigator
     */
    public function getCasesWithThreads(Request $request)
    {
        try {
            $user = $request->user();

            $query = CaseModel::with([
                'company:id,name,email',
                'branch:id,name,location',
                'assignments.investigator:id,name,email'
            ])->whereHas('assignments', function ($q) use ($user) {
                $q->where('investigator_id', $user->id);
            });

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

            if ($request->has('search') && $request->search !== '') {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'ILIKE', '%' . $search . '%')
                        ->orWhere('description', 'ILIKE', '%' . $search . '%')
                        ->orWhere('case_token', 'ILIKE', '%' . $search . '%');
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
                    'title' => $case->title,
                    'type' => $case->type,
                    'status' => $case->status,
                    'priority' => $case->priority,
                    'created_at' => $case->created_at,
                    'updated_at' => $case->updated_at,
                    'company' => $case->company,
                    'branch' => $case->branch,
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
            Log::error('Failed to retrieve investigator cases with thread statistics', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve cases with thread statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get a specific case details
     */
    public function show(Request $request, $caseId)
    {
        try {
            $user = $request->user();

            // Build case query with thread statistics
            $caseQuery = CaseModel::with([
                'company:id,name,email,contact,address',
                'branch:id,name,location',
                'assignments.investigator:id,name,email,phone_number',
                'assignments.assignedBy:id,name',
                'files'
            ])->whereHas('assignments', function ($q) use ($user) {
                $q->where('investigator_id', $user->id);
            });

            // Add thread statistics
            $caseQuery->addSelect([
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

            $case = $caseQuery->find($caseId);

            if (!$case) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Case not found or not assigned to you'
                ], 404);
            }

            // Get investigator's assignment
            $assignment = $case->assignments->where('investigator_id', $user->id)->first();

            // Format the response with thread statistics
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

            // Add assignment info
            $caseData['assignment'] = $assignment ? [
                'id' => $assignment->id,
                'role' => $assignment->role,
                'notes' => $assignment->notes,
                'assigned_at' => $assignment->created_at,
            ] : null;

            return response()->json([
                'success' => true,
                'message' => 'Case retrieved successfully',
                'data' => $caseData
            ], 200);
        } catch (\Exception $e) {
            Log::error('Investigator case show error', [
                'user_id' => $request->user()->id,
                'case_id' => $caseId,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve case details'
            ], 500);
        }
    }

    /**
     * Update case status or add notes
     */
    public function updateCase(Request $request, $caseId)
    {
        try {
            $user = $request->user();

            // Verify investigator has access to this case
            $assignment = CaseAssignment::where('investigator_id', $user->id)
                ->where('case_id', $caseId)
                ->first();

            if (!$assignment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Case not found or not assigned to you'
                ], 404);
            }

            $request->validate([
                'status' => 'sometimes|in:open,in_progress,under_investigation,pending_review,resolved,closed',
                'notes' => 'sometimes|string|max:1000',
                'priority' => 'sometimes|in:low,medium,high,urgent',
            ]);

            $case = $assignment->case;
            $changes = [];
            $oldStatus = $case->status;

            DB::beginTransaction();

            // Update case fields
            if ($request->filled('status') && $request->status !== $case->status) {
                $changes['status'] = ['old' => $case->status, 'new' => $request->status];
                $case->status = $request->status;
            }

            if ($request->filled('priority') && $request->priority !== $case->priority) {
                $changes['priority'] = ['old' => $case->priority, 'new' => $request->priority];
                $case->priority = $request->priority;
            }

            if (!empty($changes)) {
                $case->save();

                Log::info('Case updated by investigator', [
                    'case_id' => $case->id,
                    'user_id' => $user->id,
                    'changes' => $changes
                ]);
            }

            // Update assignment notes if provided
            if ($request->filled('notes')) {
                $assignment->notes = $request->notes;
                $assignment->save();
            }

            DB::commit();

            Log::info('Investigator updated case', [
                'user_id' => $user->id,
                'case_id' => $case->id,
                'changes' => $changes,
                'notes' => $request->notes
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Case updated successfully',
                'data' => [
                    'case' => [
                        'id' => $case->id,
                        'status' => $case->status,
                        'priority' => $case->priority,
                        'updated_at' => $case->updated_at,
                    ],
                    'assignment_notes' => $assignment->notes,
                ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Investigator case update error', [
                'user_id' => $request->user()->id,
                'case_id' => $caseId,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update case'
            ], 500);
        }
    }

    /**
     * Download case file
     */
    public function downloadFile(Request $request, $caseId, $fileId)
    {
        try {
            $user = $request->user();

            // Verify investigator has access to this case
            $assignment = CaseAssignment::where('investigator_id', $user->id)
                ->where('case_id', $caseId)
                ->first();

            if (!$assignment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Case not found or not assigned to you'
                ], 404);
            }

            $file = CaseFile::where('id', $fileId)
                ->where('case_id', $caseId)
                ->first();

            if (!$file) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'File not found'
                ], 404);
            }

            $filePath = storage_path('app/private/case-files/' . $file->filename);

            if (!file_exists($filePath)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'File not found on storage'
                ], 404);
            }

            Log::info('Investigator downloaded case file', [
                'user_id' => $user->id,
                'case_id' => $caseId,
                'file_id' => $file->id,
                'filename' => $file->filename
            ]);

            return response()->download($filePath, $file->original_filename);
        } catch (\Exception $e) {
            Log::error('Investigator file download error', [
                'user_id' => $request->user()->id,
                'case_id' => $caseId,
                'file_id' => $fileId,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to download file'
            ], 500);
        }
    }

    /**
     * Get case timeline/activity
     */
    private function getCaseTimeline($caseId)
    {
        $activities = [];

        // Get investigator assignments
        $assignments = CaseAssignment::where('case_id', $caseId)
            ->with('investigator:id,name')
            ->get();

        foreach ($assignments as $assignment) {
            $activities[] = [
                'type' => 'investigator_assigned',
                'title' => 'Investigator assigned',
                'description' => $assignment->investigator->name . ' was assigned to this case',
                'user' => 'System',
                'timestamp' => $assignment->created_at,
            ];
        }

        // Sort by timestamp
        usort($activities, function ($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        return collect($activities)->take(20);
    }
}
