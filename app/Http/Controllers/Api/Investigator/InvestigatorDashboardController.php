<?php

namespace App\Http\Controllers\Api\Investigator;

use App\Http\Controllers\Controller;
use App\Models\CaseModel;
use App\Models\CaseThread;
use App\Models\InvestigatorCaseAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class InvestigatorDashboardController extends Controller
{
    /**
     * Get investigator dashboard data
     */
    public function dashboard(Request $request)
    {
        try {
            $user = $request->user();

            // Get case statistics
            $caseStats = $this->getCaseStatistics($user->id);

            // Get recent cases
            $recentCases = $this->getRecentCases($user->id);

            // Get priority cases
            $priorityCases = $this->getPriorityCases($user->id);

            // Get thread activity
            $threadActivity = $this->getThreadActivity($user->id);

            // Get workload distribution
            $workloadDistribution = $this->getWorkloadDistribution($user->id);

            // Get monthly case trends
            $monthlyCaseTrends = $this->getMonthlyCaseTrends($user->id);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'case_statistics' => $caseStats,
                    'recent_cases' => $recentCases,
                    'priority_cases' => $priorityCases,
                    'thread_activity' => $threadActivity,
                    'workload_distribution' => $workloadDistribution,
                    'monthly_trends' => $monthlyCaseTrends,
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Investigator dashboard error', [
                'user_id' => $request->user()->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load dashboard data'
            ], 500);
        }
    }

    /**
     * Get case statistics for the investigator
     */
    private function getCaseStatistics($investigatorId)
    {
        $baseQuery = InvestigatorCaseAssignment::where('investigator_id', $investigatorId)
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
                $q->where('priority', 'urgent')
                    ->whereIn('status', ['open', 'in_progress', 'under_investigation']);
            })->count(),
            'overdue_cases' => $baseQuery->whereHas('case', function ($q) {
                $q->where('deadline', '<', now())
                    ->whereNotIn('status', ['closed', 'resolved']);
            })->count(),
        ];
    }

    /**
     * Get recent cases assigned to the investigator
     */
    private function getRecentCases($investigatorId)
    {
        return InvestigatorCaseAssignment::where('investigator_id', $investigatorId)
            ->with([
                'case:id,case_token,title,description,status,priority,created_at,deadline',
                'case.company:id,name,logo',
                'case.branch:id,name',
                'case.incidentCategory:id,name,color'
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
                    'deadline' => $case->deadline,
                    'is_overdue' => $case->deadline && $case->deadline < now(),
                    'days_until_deadline' => $case->deadline ? now()->diffInDays($case->deadline, false) : null,
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
        return InvestigatorCaseAssignment::where('investigator_id', $investigatorId)
            ->whereHas('case', function ($q) {
                $q->whereIn('priority', ['urgent', 'high'])
                    ->whereIn('status', ['open', 'in_progress', 'under_investigation']);
            })
            ->with([
                'case:id,case_token,title,status,priority,deadline,created_at',
                'case.company:id,name',
                'case.branch:id,name'
            ])
            ->orderByRaw("
                CASE 
                    WHEN cases.priority = 'urgent' THEN 1
                    WHEN cases.priority = 'high' THEN 2
                    ELSE 3
                END
            ")
            ->orderBy('created_at', 'asc')
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
                    'deadline' => $case->deadline,
                    'is_overdue' => $case->deadline && $case->deadline < now(),
                    'days_until_deadline' => $case->deadline ? now()->diffInDays($case->deadline, false) : null,
                    'company_name' => $case->company->name,
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
        $threadsWithMessages = CaseThread::whereHas('participants', function ($q) use ($investigatorId) {
            $q->where('user_id', $investigatorId);
        })
            ->whereHas('case.investigatorAssignments', function ($q) use ($investigatorId) {
                $q->where('investigator_id', $investigatorId);
            })
            ->with(['case', 'messages' => function ($q) {
                $q->orderBy('created_at', 'desc')->limit(1);
            }]);

        $unreadCount = $threadsWithMessages->whereHas('messages', function ($q) use ($investigatorId) {
            $q->whereDoesntHave('readReceipts', function ($receipt) use ($investigatorId) {
                $receipt->where('user_id', $investigatorId);
            });
        })->count();

        $recentThreads = $threadsWithMessages->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($thread) {
                $lastMessage = $thread->messages->first();
                return [
                    'thread_id' => $thread->id,
                    'subject' => $thread->subject,
                    'case_token' => $thread->case->case_token,
                    'last_message_at' => $thread->updated_at,
                    'last_message_preview' => $lastMessage ? substr($lastMessage->message, 0, 50) . '...' : null,
                    'messages_count' => $thread->messages_count ?? 0,
                ];
            });

        return [
            'total_threads' => $threadsWithMessages->count(),
            'unread_messages' => $unreadCount,
            'active_threads_today' => $threadsWithMessages->where('updated_at', '>=', now()->startOfDay())->count(),
            'recent_threads' => $recentThreads,
        ];
    }

    /**
     * Get workload distribution by company
     */
    private function getWorkloadDistribution($investigatorId)
    {
        return InvestigatorCaseAssignment::where('investigator_id', $investigatorId)
            ->whereHas('case', function ($q) {
                $q->whereIn('status', ['open', 'in_progress', 'under_investigation']);
            })
            ->join('cases', 'investigator_case_assignments.case_id', '=', 'cases.id')
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
                'assigned_cases' => InvestigatorCaseAssignment::where('investigator_id', $investigatorId)
                    ->whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->count(),
                'closed_cases' => InvestigatorCaseAssignment::where('investigator_id', $investigatorId)
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
    public function quickStats(Request $request)
    {
        try {
            $user = $request->user();

            $stats = [
                'total_cases' => InvestigatorCaseAssignment::where('investigator_id', $user->id)->count(),
                'active_cases' => InvestigatorCaseAssignment::where('investigator_id', $user->id)
                    ->whereHas('case', function ($q) {
                        $q->whereIn('status', ['open', 'in_progress', 'under_investigation']);
                    })->count(),
                'urgent_cases' => InvestigatorCaseAssignment::where('investigator_id', $user->id)
                    ->whereHas('case', function ($q) {
                        $q->where('priority', 'urgent')
                            ->whereIn('status', ['open', 'in_progress', 'under_investigation']);
                    })->count(),
                'unread_messages' => CaseThread::whereHas('participants', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                    ->whereHas('messages', function ($q) use ($user) {
                        $q->whereDoesntHave('readReceipts', function ($receipt) use ($user) {
                            $receipt->where('user_id', $user->id);
                        });
                    })->count(),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $stats
            ], 200);
        } catch (\Exception $e) {
            Log::error('Investigator quick stats error', [
                'user_id' => $request->user()->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load quick stats'
            ], 500);
        }
    }
}
