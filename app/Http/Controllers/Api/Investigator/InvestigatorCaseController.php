<?php

namespace App\Http\Controllers\Api\Investigator;

use App\Http\Controllers\Controller;
use App\Models\CaseModel;
use App\Models\InvestigatorCaseAssignment;
use App\Models\CaseFile;
use App\Models\CaseStatusLog;
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

            $query = InvestigatorCaseAssignment::where('investigator_id', $user->id)
                ->with([
                    'case' => function ($q) {
                        $q->select('id', 'case_token', 'title', 'description', 'status', 'priority', 'deadline', 'created_at', 'company_id', 'branch_id', 'incident_category_id');
                    },
                    'case.company:id,name,logo',
                    'case.branch:id,name',
                    'case.incidentCategory:id,name,color',
                    'case.investigatorAssignments' => function ($q) {
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
                        'incident_category' => $case->incidentCategory ? [
                            'id' => $case->incidentCategory->id,
                            'name' => $case->incidentCategory->name,
                            'color' => $case->incidentCategory->color,
                        ] : null,
                        'investigators' => $case->investigatorAssignments->map(function ($assign) {
                            return [
                                'id' => $assign->investigator->id,
                                'name' => $assign->investigator->name,
                                'email' => $assign->investigator->email,
                                'assigned_at' => $assign->created_at,
                            ];
                        }),
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
     * Get a specific case details
     */
    public function show(Request $request, $caseId)
    {
        try {
            $user = $request->user();

            $assignment = InvestigatorCaseAssignment::where('investigator_id', $user->id)
                ->where('case_id', $caseId)
                ->with([
                    'case' => function ($q) {
                        $q->with([
                            'company:id,name,logo,email,phone_number',
                            'branch:id,name,address,email,phone_number',
                            'incidentCategory:id,name,color,description',
                            'feedbackCategories:id,name,color',
                            'departments:id,name,description',
                            'investigatorAssignments.investigator:id,name,email,phone_number',
                            'files',
                            'statusLogs' => function ($q) {
                                $q->with('user:id,name,role')->orderBy('created_at', 'desc');
                            }
                        ]);
                    }
                ])
                ->first();

            if (!$assignment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Case not found or not assigned to you'
                ], 404);
            }

            $case = $assignment->case;

            // Get case timeline/activity
            $timeline = $this->getCaseTimeline($case->id);

            // Get case statistics
            $stats = [
                'total_files' => $case->files->count(),
                'total_messages' => $case->threads()->withCount('messages')->get()->sum('messages_count'),
                'investigators_count' => $case->investigatorAssignments->count(),
                'days_since_created' => $case->created_at->diffInDays(now()),
                'days_until_deadline' => $case->deadline ? now()->diffInDays($case->deadline, false) : null,
            ];

            return response()->json([
                'status' => 'success',
                'data' => [
                    'assignment' => [
                        'id' => $assignment->id,
                        'role' => $assignment->role,
                        'notes' => $assignment->notes,
                        'assigned_at' => $assignment->created_at,
                    ],
                    'case' => [
                        'id' => $case->id,
                        'case_token' => $case->case_token,
                        'title' => $case->title,
                        'description' => $case->description,
                        'status' => $case->status,
                        'priority' => $case->priority,
                        'deadline' => $case->deadline,
                        'is_overdue' => $case->deadline && $case->deadline < now(),
                        'is_anonymous' => $case->is_anonymous,
                        'reporter_name' => $case->reporter_name,
                        'reporter_email' => $case->reporter_email,
                        'reporter_phone' => $case->reporter_phone,
                        'incident_date' => $case->incident_date,
                        'incident_location' => $case->incident_location,
                        'evidence_description' => $case->evidence_description,
                        'witness_information' => $case->witness_information,
                        'additional_notes' => $case->additional_notes,
                        'created_at' => $case->created_at,
                        'updated_at' => $case->updated_at,
                        'company' => [
                            'id' => $case->company->id,
                            'name' => $case->company->name,
                            'logo' => $case->company->logo,
                            'email' => $case->company->email,
                            'phone_number' => $case->company->phone_number,
                        ],
                        'branch' => $case->branch ? [
                            'id' => $case->branch->id,
                            'name' => $case->branch->name,
                            'address' => $case->branch->address,
                            'email' => $case->branch->email,
                            'phone_number' => $case->branch->phone_number,
                        ] : null,
                        'incident_category' => $case->incidentCategory ? [
                            'id' => $case->incidentCategory->id,
                            'name' => $case->incidentCategory->name,
                            'color' => $case->incidentCategory->color,
                            'description' => $case->incidentCategory->description,
                        ] : null,
                        'feedback_categories' => $case->feedbackCategories->map(function ($category) {
                            return [
                                'id' => $category->id,
                                'name' => $category->name,
                                'color' => $category->color,
                            ];
                        }),
                        'departments' => $case->departments->map(function ($department) {
                            return [
                                'id' => $department->id,
                                'name' => $department->name,
                                'description' => $department->description,
                            ];
                        }),
                        'investigators' => $case->investigatorAssignments->map(function ($assign) {
                            return [
                                'id' => $assign->investigator->id,
                                'name' => $assign->investigator->name,
                                'email' => $assign->investigator->email,
                                'phone_number' => $assign->investigator->phone_number,
                                'role' => $assign->role,
                                'assigned_at' => $assign->created_at,
                            ];
                        }),
                        'files' => $case->files->map(function ($file) {
                            return [
                                'id' => $file->id,
                                'filename' => $file->filename,
                                'original_filename' => $file->original_filename,
                                'file_type' => $file->file_type,
                                'file_size' => $file->file_size,
                                'category' => $file->category,
                                'uploaded_by' => $file->uploaded_by,
                                'created_at' => $file->created_at,
                            ];
                        }),
                        'status_history' => $case->statusLogs->map(function ($log) {
                            return [
                                'id' => $log->id,
                                'old_status' => $log->old_status,
                                'new_status' => $log->new_status,
                                'notes' => $log->notes,
                                'changed_by' => [
                                    'id' => $log->user->id,
                                    'name' => $log->user->name,
                                    'role' => $log->user->role,
                                ],
                                'created_at' => $log->created_at,
                            ];
                        }),
                    ],
                    'timeline' => $timeline,
                    'statistics' => $stats,
                ]
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
            $assignment = InvestigatorCaseAssignment::where('investigator_id', $user->id)
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

                // Log status change
                if (isset($changes['status'])) {
                    CaseStatusLog::create([
                        'case_id' => $case->id,
                        'user_id' => $user->id,
                        'old_status' => $changes['status']['old'],
                        'new_status' => $changes['status']['new'],
                        'notes' => $request->notes ?? 'Status updated by investigator'
                    ]);
                }
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
            $assignment = InvestigatorCaseAssignment::where('investigator_id', $user->id)
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

        // Get status logs
        $statusLogs = CaseStatusLog::where('case_id', $caseId)
            ->with('user:id,name,role')
            ->get();

        foreach ($statusLogs as $log) {
            $activities[] = [
                'type' => 'status_change',
                'title' => 'Status changed from ' . ucfirst($log->old_status) . ' to ' . ucfirst($log->new_status),
                'description' => $log->notes,
                'user' => $log->user->name . ' (' . ucfirst($log->user->role) . ')',
                'timestamp' => $log->created_at,
            ];
        }

        // Get investigator assignments
        $assignments = InvestigatorCaseAssignment::where('case_id', $caseId)
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
