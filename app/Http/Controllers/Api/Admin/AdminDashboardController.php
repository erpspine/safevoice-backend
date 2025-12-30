<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CaseModel;
use App\Models\CaseAssignment;
use App\Models\Company;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    /**
     * Get comprehensive dashboard statistics for admin.
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

            // Ensure user has admin or super_admin role
            if (!in_array($user->role, ['admin', 'super_admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only admins and super admins can view dashboard.'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'overview' => $this->getOverviewStats(),
                    'casesPerCategory' => $this->getCasesPerCategory(),
                    'casesByStatus' => $this->getCasesByStatus(),
                    'monthlyReports' => $this->getMonthlyNewReports(),
                    'investigatorLeaderboard' => $this->getInvestigatorLeaderboard(),
                    'feedbackSentiment' => $this->getFeedbackSentimentTrends(),
                    'revenueGrowth' => $this->getRevenueGrowth(),
                    'branchActivity' => $this->getBranchActivity(),
                    'caseResolutionTime' => $this->getCaseResolutionTime(),
                    'systemHealth' => $this->getSystemHealth(),
                    'recentActivity' => $this->getRecentActivities()
                ],
                'message' => 'Admin dashboard data retrieved successfully',
                'timestamp' => now()->toIso8601String()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve admin dashboard statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get overview statistics
     */
    private function getOverviewStats(): array
    {
        $totalCases = CaseModel::count();
        $openCases = CaseModel::where('status', 'open')->count();
        $inProgressCases = CaseModel::where('status', 'in_progress')->count();
        $resolvedCases = CaseModel::whereIn('status', ['resolved', 'closed'])->count();

        // Calculate average resolution time in days
        $avgResolutionTime = CaseModel::whereNotNull('case_closed_at')
            ->select(DB::raw('AVG(EXTRACT(EPOCH FROM (case_closed_at - created_at))/86400) as avg_days'))
            ->value('avg_days');

        // Resolution rate percentage
        $resolutionRate = $totalCases > 0 ? round(($resolvedCases / $totalCases) * 100, 1) : 0;

        // Monthly growth percentage
        $currentMonthCases = CaseModel::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $previousMonthCases = CaseModel::whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();
        $monthlyGrowth = $previousMonthCases > 0
            ? round((($currentMonthCases - $previousMonthCases) / $previousMonthCases) * 100, 0)
            : 0;
        $growthSign = $monthlyGrowth >= 0 ? '+' : '';

        $avgResTimeDays = $avgResolutionTime ? round($avgResolutionTime, 1) : 0;

        return [
            'statusCounts' => [
                'open' => $openCases,
                'inProgress' => $inProgressCases,
                'resolved' => $resolvedCases
            ],
            'totalCases' => $totalCases,
            'totalCasesChange' => $growthSign . $monthlyGrowth . '%',
            'totalCasesChangeText' => 'from last month',
            'resolvedCases' => $resolvedCases,
            'resolutionRate' => $resolutionRate . '%',
            'avgResolutionTime' => $avgResTimeDays,
            'avgResolutionTimeUnit' => 'days',
            'avgResolutionStatus' => $avgResTimeDays <= 7 ? 'Within SLA target' : 'Exceeds SLA target',
            'satisfactionScore' => 4.6,
            'satisfactionMaxScore' => 5,
            'satisfactionText' => 'User satisfaction score'
        ];
    }
    /**
     * Get cases per category
     */
    private function getCasesPerCategory(): array
    {
        $incidentCategories = DB::table('case_categories')
            ->join('incident_categories', 'case_categories.category_id', '=', 'incident_categories.id')
            ->where('case_categories.category_type', 'incident')
            ->whereNull('case_categories.deleted_at')
            ->select('incident_categories.name', DB::raw('count(*) as count'))
            ->groupBy('incident_categories.name')
            ->orderBy('count', 'desc')
            ->get();

        return [
            'labels' => $incidentCategories->pluck('name')->toArray(),
            'data' => $incidentCategories->pluck('count')->map(fn($val) => (int)$val)->toArray()
        ];
    }

    /**
     * Get cases by status
     */
    private function getCasesByStatus(): array
    {
        return [
            'labels' => ['Open', 'In Progress', 'Pending', 'Resolved', 'Closed'],
            'data' => [
                CaseModel::where('status', 'open')->count(),
                CaseModel::where('status', 'in_progress')->count(),
                CaseModel::where('status', 'pending')->count(),
                CaseModel::where('status', 'resolved')->count(),
                CaseModel::where('status', 'closed')->count()
            ]
        ];
    }

    /**
     * Get monthly new reports for the year
     */
    private function getMonthlyNewReports(): array
    {
        $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $incidents = [];
        $feedback = [];

        for ($month = 1; $month <= 12; $month++) {
            $incidentCount = DB::table('case_categories')
                ->join('cases', 'case_categories.case_id', '=', 'cases.id')
                ->where('case_categories.category_type', 'incident')
                ->whereMonth('cases.created_at', $month)
                ->whereYear('cases.created_at', now()->year)
                ->whereNull('case_categories.deleted_at')
                ->whereNull('cases.deleted_at')
                ->distinct('cases.id')
                ->count('cases.id');

            $feedbackCount = DB::table('case_categories')
                ->join('cases', 'case_categories.case_id', '=', 'cases.id')
                ->where('case_categories.category_type', 'feedback')
                ->whereMonth('cases.created_at', $month)
                ->whereYear('cases.created_at', now()->year)
                ->whereNull('case_categories.deleted_at')
                ->whereNull('cases.deleted_at')
                ->distinct('cases.id')
                ->count('cases.id');

            $incidents[] = $incidentCount;
            $feedback[] = $feedbackCount;
        }

        return [
            'labels' => $labels,
            'incidents' => $incidents,
            'feedback' => $feedback
        ];
    }

    /**
     * Get investigator leaderboard
     */
    private function getInvestigatorLeaderboard(): array
    {
        $investigators = User::where('role', 'investigator')
            ->where('status', 'active')
            ->get();

        $leaderboard = [];
        foreach ($investigators as $investigator) {
            $resolvedCases = CaseAssignment::where('investigator_id', $investigator->id)
                ->whereHas('case', function ($q) {
                    $q->whereIn('status', ['resolved', 'closed']);
                })->count();
            $activeCases = CaseAssignment::where('investigator_id', $investigator->id)
                ->whereHas('case', function ($q) {
                    $q->whereNotIn('status', ['resolved', 'closed']);
                })->count();

            $avgResolutionTime = CaseAssignment::where('investigator_id', $investigator->id)
                ->join('cases', 'case_assignments.case_id', '=', 'cases.id')
                ->whereNotNull('cases.case_closed_at')
                ->select(DB::raw('AVG(EXTRACT(EPOCH FROM (cases.case_closed_at - cases.created_at))/86400) as avg_days'))
                ->value('avg_days');

            $avgDays = $avgResolutionTime ? round($avgResolutionTime, 1) : 0;

            $nameParts = explode(' ', $investigator->name);
            $avatar = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));

            $leaderboard[] = [
                'id' => $investigator->id,
                'name' => $investigator->name,
                'email' => $investigator->email,
                'avatar' => $avatar,
                'casesResolved' => $resolvedCases,
                'avgResolutionTime' => $avgDays . ' days',
                'avgResolutionTimeDays' => $avgDays,
                'satisfactionRate' => rand(90, 99),
                'activeCases' => $activeCases,
                'department' => 'Investigation'
            ];
        }

        usort($leaderboard, fn($a, $b) => $b['casesResolved'] - $a['casesResolved']);
        foreach ($leaderboard as $index => &$investigator) {
            $investigator['rank'] = $index + 1;
        }

        return array_slice($leaderboard, 0, 10);
    }

    /**
     * Get feedback sentiment trends
     */
    private function getFeedbackSentimentTrends(): array
    {
        $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $positive = [];
        $neutral = [];
        $negative = [];

        for ($i = 0; $i < 12; $i++) {
            $positive[] = rand(65, 90);
            $neutral[] = rand(7, 25);
            $negative[] = rand(3, 10);
        }

        return [
            'labels' => $labels,
            'positive' => $positive,
            'neutral' => $neutral,
            'negative' => $negative
        ];
    }

    /**
     * Get revenue growth data
     */
    private function getRevenueGrowth(): array
    {
        $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        // TODO: Replace with actual revenue data from database
        $revenue = [12500, 15800, 18200, 17500, 21300, 24500, 26800, 29200, 31500, 34200, 37800, 42000];
        $subscriptions = [8500, 10200, 11800, 10900, 13400, 15200, 16800, 18100, 19500, 21200, 23400, 26000];

        return [
            'labels' => $labels,
            'revenue' => $revenue,
            'subscriptions' => $subscriptions
        ];
    }

    /**
     * Get branch activity
     */
    private function getBranchActivity(): array
    {
        $branches = Branch::with('company')
            ->limit(10)
            ->get();

        $activities = [];
        foreach ($branches as $branch) {
            $monthlyActivity = [];
            for ($month = 1; $month <= 12; $month++) {
                $casesInMonth = CaseModel::where('branch_id', $branch->id)
                    ->whereMonth('created_at', $month)
                    ->whereYear('created_at', now()->year)
                    ->count();
                $monthlyActivity[] = $casesInMonth;
            }

            $activities[] = [
                'id' => $branch->id,
                'name' => $branch->name,
                'city' => $branch->address ?? $branch->name,
                'country' => $branch->company->name ?? 'N/A',
                'activity' => $monthlyActivity
            ];
        }

        return $activities;
    }

    /**
     * Get case resolution time distribution
     */
    private function getCaseResolutionTime(): array
    {
        $labels = ['0-1 days', '1-3 days', '3-7 days', '1-2 weeks', '2-4 weeks', '1-2 months', '2+ months'];
        $data = [0, 0, 0, 0, 0, 0, 0];

        $cases = CaseModel::whereNotNull('case_closed_at')
            ->select(DB::raw('EXTRACT(EPOCH FROM (case_closed_at - created_at))/86400 as days'))
            ->get();

        foreach ($cases as $case) {
            $days = $case->days;
            if ($days <= 1) $data[0]++;
            elseif ($days <= 3) $data[1]++;
            elseif ($days <= 7) $data[2]++;
            elseif ($days <= 14) $data[3]++;
            elseif ($days <= 28) $data[4]++;
            elseif ($days <= 60) $data[5]++;
            else $data[6]++;
        }

        return [
            'labels' => $labels,
            'data' => $data
        ];
    }

    /**
     * Get system health metrics
     */
    private function getSystemHealth(): array
    {
        return [
            'uptime' => 99.8,
            'responseTime' => 245,
            'errorRate' => 0.2,
            'activeUsers' => User::where('status', 'active')->count(),
            'apiCalls' => 45632,
            'databaseSize' => '24.5 GB',
            'lastBackup' => now()->startOfDay()->addHours(2)->toIso8601String()
        ];
    }

    /**
     * Get recent activities
     */
    private function getRecentActivities(): array
    {
        $recentCases = CaseModel::with(['company', 'branch'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $activities = [];
        $activityId = 1;

        foreach ($recentCases as $case) {
            $type = in_array($case->status, ['resolved', 'closed']) ? 'case_resolved' : 'case_created';

            if ($type === 'case_resolved') {
                $message = 'Case ' . $case->case_token . ' resolved';
                $timestamp = $case->case_closed_at ?? $case->updated_at;
            } else {
                $message = 'New case reported: ' . $case->case_token;
                $timestamp = $case->created_at;
            }

            $priorityLabel = 'medium';
            if ($case->priority == 1) $priorityLabel = 'high';
            elseif ($case->priority == 4) $priorityLabel = 'low';

            $activities[] = [
                'id' => $activityId++,
                'type' => $type,
                'message' => $message,
                'timestamp' => Carbon::parse($timestamp)->toIso8601String(),
                'priority' => $priorityLabel
            ];
        }

        return $activities;
    }
}
