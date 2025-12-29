<?php

namespace App\Http\Controllers\Api\Investigator;

use App\Http\Controllers\Controller;
use App\Models\CaseModel;
use App\Models\Thread;
use App\Models\CaseAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class InvestigatorDashboardController extends Controller
{
    /**
     * Get dashboard statistics for the authenticated investigator.
     */
    public function dashboard(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Ensure the request is authenticated
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            // Ensure user has investigator role
            if ($user->role !== 'investigator') {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only investigators can view dashboard.'
                ], 403);
            }

            $investigatorId = $user->id;

            // Quick overview numbers
            $overview = [
                'total_cases' => CaseAssignment::where('investigator_id', $investigatorId)->count(),
                'active_cases' => CaseAssignment::where('investigator_id', $investigatorId)
                    ->whereHas('case', function ($q) {
                        $q->whereNotIn('status', ['closed', 'resolved']);
                    })->count(),
                'resolved_cases' => CaseAssignment::where('investigator_id', $investigatorId)
                    ->whereHas('case', function ($q) {
                        $q->whereIn('status', ['resolved', 'closed']);
                    })->count(),
                'urgent_cases' => CaseAssignment::where('investigator_id', $investigatorId)
                    ->whereHas('case', function ($q) {
                        $q->where('priority', 1)
                            ->whereNotIn('status', ['closed', 'resolved']);
                    })->count(),
            ];

            // Get case statistics
            $caseStats = $this->getCaseStatistics($investigatorId);

            // Get recent cases
            $recentCases = $this->getRecentCases($investigatorId);

            // Get priority cases
            $priorityCases = $this->getPriorityCases($investigatorId);

            // Get thread activity
            $threadActivity = $this->getThreadActivity($investigatorId);

            // Get workload distribution
            $workloadDistribution = $this->getWorkloadDistribution($investigatorId);

            // Get monthly case trends
            $monthlyCaseTrends = $this->getMonthlyCaseTrends($investigatorId);

            return response()->json([
                'success' => true,
                'data' => [
                    'overview' => $overview,
                    'case_statistics' => $caseStats,
                    'recent_cases' => $recentCases,
                    'priority_cases' => $priorityCases,
                    'thread_activity' => $threadActivity,
                    'workload_distribution' => $workloadDistribution,
                    'monthly_trends' => $monthlyCaseTrends,
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
     * Get case statistics for the investigator
     */
    private function getCaseStatistics($investigatorId)
    {
        $baseQuery = CaseAssignment::where('investigator_id', $investigatorId)
            ->whereHas('case');

        return [
            'total_assigned' => $baseQuery->count(),
            'active_cases' => $baseQuery->whereHas('case', function ($q) {
                $q->whereIn('status', ['open', 'in_progress', 'under_investigation']);
            })->count(),
            'closed_cases' => $baseQuery->whereHas('case', function ($q) {
                $q->where('status', 'closed');
            })->count(),
            'pending_review' => $baseQuery->whereHas('case', function ($q) {
                $q->where('status', 'pending_review');
            })->count(),
            'urgent_cases' => $baseQuery->whereHas('case', function ($q) {
                $q->where('priority', 1)
                    ->whereIn('status', ['open', 'in_progress', 'under_investigation']);
            })->count(),
        ];
    }

    /**
     * Get recent cases assigned to the investigator
     */
    private function getRecentCases($investigatorId)
    {
        return CaseAssignment::where('investigator_id', $investigatorId)
            ->with([
                'case:id,case_token,title,description,status,priority,created_at',
                'case.company:id,name,logo',
                'case.branch:id,name'
            ])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($assignment) {
                $case = $assignment->case;
                return [
                    'assignment_id' => $assignment->id,
                    'case_id' => $case->id,
                    'case_token' => $case->case_token,
                    'title' => $case->title ? $case->title : 'Untitled Case',
                    'description_preview' => substr($case->description, 0, 100) . (strlen($case->description) > 100 ? '...' : ''),
                    'status' => $case->status,
                    'priority' => $case->priority,
                    'company' => $case->company ? [
                        'id' => $case->company->id,
                        'name' => $case->company->name,
                        'logo' => $case->company->logo,
                    ] : null,
                    'branch' => $case->branch ? [
                        'id' => $case->branch->id,
                        'name' => $case->branch->name,
                    ] : null,
                    'assigned_at' => $assignment->created_at,
                    'case_created_at' => $case->created_at,
                ];
            });
    }

    /**
     * Get priority cases that need attention
     */
    private function getPriorityCases($investigatorId)
    {
        return CaseAssignment::where('investigator_id', $investigatorId)
            ->whereHas('case', function ($q) {
                $q->whereIn('priority', [1, 2])
                    ->whereIn('status', ['open', 'in_progress', 'under_investigation']);
            })
            ->with([
                'case:id,case_token,title,status,priority,created_at',
                'case.company:id,name',
                'case.branch:id,name'
            ])
            ->join('cases', 'case_assignments.case_id', '=', 'cases.id')
            ->select('case_assignments.*')
            ->orderBy('cases.priority', 'asc')
            ->orderBy('case_assignments.created_at', 'asc')
            ->limit(5)
            ->get()
            ->map(function ($assignment) {
                $case = $assignment->case;
                return [
                    'case_id' => $case->id,
                    'case_token' => $case->case_token,
                    'title' => $case->title ? $case->title : 'Untitled Case',
                    'status' => $case->status,
                    'priority' => $case->priority,
                    'company_name' => $case->company->name ?? null,
                    'branch_name' => $case->branch->name ?? null,
                    'assigned_at' => $assignment->created_at,
                ];
            });
    }

    /**
     * Get thread activity statistics
     */
    private function getThreadActivity($investigatorId)
    {
        // Get threads for cases assigned to investigator
        $caseIds = CaseAssignment::where('investigator_id', $investigatorId)
            ->where('status', 'active')
            ->pluck('case_id');

        $totalThreads = Thread::whereIn('case_id', $caseIds)->count();

        // Count unread messages using message_reads table
        $unreadCount = DB::table('case_messages')
            ->join('threads', 'case_messages.thread_id', '=', 'threads.id')
            ->whereIn('threads.case_id', $caseIds)
            ->whereNotExists(function ($query) use ($investigatorId) {
                $query->select(DB::raw(1))
                    ->from('message_reads')
                    ->whereColumn('message_reads.message_id', 'case_messages.id')
                    ->where('message_reads.user_id', $investigatorId);
            })
            ->count();

        // Get recent threads with participant filter
        $recentThreads = Thread::whereIn('case_id', $caseIds)
            ->whereHas('participants', function ($q) use ($investigatorId) {
                $q->where('user_id', $investigatorId);
            })
            ->with(['case:id,case_token,title'])
            ->withCount('messages')
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($thread) {
                return [
                    'thread_id' => $thread->id,
                    'subject' => $thread->subject,
                    'case_token' => $thread->case->case_token,
                    'case_title' => $thread->case->title,
                    'last_message_at' => $thread->updated_at,
                    'messages_count' => $thread->messages_count ?? 0,
                ];
            });

        $activeThreadsToday = Thread::whereIn('case_id', $caseIds)
            ->where('updated_at', '>=', now()->startOfDay())
            ->count();

        return [
            'total_threads' => $totalThreads,
            'unread_messages' => $unreadCount,
            'active_threads_today' => $activeThreadsToday,
            'recent_threads' => $recentThreads,
        ];
    }

    /**
     * Get workload distribution by company
     */
    private function getWorkloadDistribution($investigatorId)
    {
        return CaseAssignment::where('investigator_id', $investigatorId)
            ->whereHas('case', function ($q) {
                $q->whereIn('status', ['open', 'in_progress', 'under_investigation']);
            })
            ->join('cases', 'case_assignments.case_id', '=', 'cases.id')
            ->join('companies', 'cases.company_id', '=', 'companies.id')
            ->select('companies.name as company_name', DB::raw('count(*) as case_count'))
            ->groupBy('companies.id', 'companies.name')
            ->orderBy('case_count', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'company_name' => $item->company_name,
                    'case_count' => (int) $item->case_count,
                ];
            });
    }

    /**
     * Get monthly case assignment trends
     */
    private function getMonthlyCaseTrends($investigatorId)
    {
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months[] = [
                'month' => $date->format('M Y'),
                'year_month' => $date->format('Y-m'),
                'assigned_cases' => CaseAssignment::where('investigator_id', $investigatorId)
                    ->whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->count(),
                'closed_cases' => CaseAssignment::where('investigator_id', $investigatorId)
                    ->whereHas('case', function ($q) {
                        $q->where('status', 'closed');
                    })
                    ->whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->count(),
            ];
        }

        return $months;
    }

    /**
     * Get quick stats for widgets
     */
    public function quickStats(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Ensure the request is authenticated
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            // Ensure user has investigator role
            if ($user->role !== 'investigator') {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only investigators can view quick stats.'
                ], 403);
            }

            $investigatorId = $user->id;

            // Get case IDs for investigator
            $caseIds = CaseAssignment::where('investigator_id', $investigatorId)
                ->where('status', 'active')
                ->pluck('case_id');

            // Count unread messages using message_reads table
            $unreadMessages = DB::table('case_messages')
                ->join('threads', 'case_messages.thread_id', '=', 'threads.id')
                ->whereIn('threads.case_id', $caseIds)
                ->whereNotExists(function ($query) use ($investigatorId) {
                    $query->select(DB::raw(1))
                        ->from('message_reads')
                        ->whereColumn('message_reads.message_id', 'case_messages.id')
                        ->where('message_reads.user_id', $investigatorId);
                })
                ->count();

            $stats = [
                'total_cases' => CaseAssignment::where('investigator_id', $investigatorId)->count(),
                'active_cases' => CaseAssignment::where('investigator_id', $investigatorId)
                    ->whereHas('case', function ($q) {
                        $q->whereIn('status', ['open', 'in_progress', 'under_investigation']);
                    })->count(),
                'urgent_cases' => CaseAssignment::where('investigator_id', $investigatorId)
                    ->whereHas('case', function ($q) {
                        $q->where('priority', 1)
                            ->whereIn('status', ['open', 'in_progress', 'under_investigation']);
                    })->count(),
                'unread_messages' => $unreadMessages,
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve quick stats',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
