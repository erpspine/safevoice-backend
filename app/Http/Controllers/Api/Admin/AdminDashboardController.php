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

            // Ensure user has admin role
            if ($user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only admins can view dashboard.'
                ], 403);
            }

            // Get overview statistics
            $overview = $this->getOverviewStats();

            // Get cases per category
            $casesPerCategory = $this->getCasesPerCategory();

            // Get cases by status
            $casesByStatus = $this->getCasesByStatus();

            // Get monthly new reports
            $monthlyNewReports = $this->getMonthlyNewReports();

            // Get investigator leaderboard
            $investigatorLeaderboard = $this->getInvestigatorLeaderboard();

            // Get feedback sentiment trends
            $feedbackSentimentTrends = $this->getFeedbackSentimentTrends();

            // Get revenue growth
            $revenueGrowth = $this->getRevenueGrowth();

            // Get branch activity heatmap
            $branchActivityHeatmap = $this->getBranchActivityHeatmap();

            // Get case resolution time
            $caseResolutionTime = $this->getCaseResolutionTime();

            // Get company statistics
            $companyStatistics = $this->getCompanyStatistics();

            // Get system health
            $systemHealth = $this->getSystemHealth();

            // Get recent activities
            $recentActivities = $this->getRecentActivities();

            // Date range
            $dateRange = [
                'from' => now()->startOfYear()->toDateString(),
                'to' => now()->toDateString(),
                'period' => '12_months'
            ];

            return response()->json([
                'success' => true,
                'message' => 'Admin dashboard data retrieved successfully',
                'data' => [
                    'overview' => $overview,
                    'cases_per_category' => $casesPerCategory,
                    'cases_by_status' => $casesByStatus,
                    'monthly_new_reports' => $monthlyNewReports,
                    'investigator_leaderboard' => $investigatorLeaderboard,
                    'feedback_sentiment_trends' => $feedbackSentimentTrends,
                    'revenue_growth' => $revenueGrowth,
                    'branch_activity_heatmap' => $branchActivityHeatmap,
                    'case_resolution_time' => $caseResolutionTime,
                    'company_statistics' => $companyStatistics,
                    'system_health' => $systemHealth,
                    'recent_activities' => $recentActivities,
                    'date_range' => $dateRange
                ]
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
        $resolvedCases = CaseModel::whereIn('status', ['resolved', 'closed'])->count();
        $activeCases = CaseModel::whereIn('status', ['open', 'in_progress', 'under_investigation'])->count();
        $pendingCases = CaseModel::where('status', 'pending_review')->count();

        // Calculate average resolution time in days
        $avgResolutionTime = CaseModel::whereNotNull('case_closed_at')
            ->select(DB::raw('AVG(EXTRACT(EPOCH FROM (case_closed_at - created_at))/86400) as avg_days'))
            ->value('avg_days');

        // Resolution rate percentage
        $resolutionRate = $totalCases > 0 ? round(($resolvedCases / $totalCases) * 100, 1) : 0;

        // Monthly growth percentage (compare current month with previous month)
        $currentMonthCases = CaseModel::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $previousMonthCases = CaseModel::whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();
        $monthlyGrowth = $previousMonthCases > 0
            ? round((($currentMonthCases - $previousMonthCases) / $previousMonthCases) * 100, 0)
            : 0;

        return [
            'total_cases' => $totalCases,
            'resolved_cases' => $resolvedCases,
            'active_cases' => $activeCases,
            'pending_cases' => $pendingCases,
            'avg_resolution_time_days' => $avgResolutionTime ? round($avgResolutionTime, 1) : 0,
            'satisfaction_score' => 4.6, // TODO: Calculate from actual feedback data
            'resolution_rate_percentage' => $resolutionRate,
            'monthly_growth_percentage' => $monthlyGrowth,
            'cases_within_sla' => true // TODO: Calculate based on actual SLA data
        ];
    }

    /**
     * Get cases per category
     */
    private function getCasesPerCategory(): array
    {
        // Get incident categories with case counts
        $incidentCategories = DB::table('case_categories')
            ->join('incident_categories', 'case_categories.category_id', '=', 'incident_categories.id')
            ->where('case_categories.category_type', 'incident')
            ->whereNull('case_categories.deleted_at')
            ->select('incident_categories.name', DB::raw('count(*) as count'))
            ->groupBy('incident_categories.name')
            ->orderBy('count', 'desc')
            ->get();

        $labels = $incidentCategories->pluck('name')->toArray();
        $data = $incidentCategories->pluck('count')->map(fn($val) => (int)$val)->toArray();

        return [
            'labels' => $labels,
            'data' => $data,
            'total_categories' => count($labels)
        ];
    }

    /**
     * Get cases by status
     */
    private function getCasesByStatus(): array
    {
        $statuses = ['open', 'in_progress', 'pending_review', 'resolved', 'closed'];
        $labels = ['Open', 'In Progress', 'Pending Review', 'Resolved', 'Closed'];
        $colors = ['#FFCD00', '#5356FB', '#FFA500', '#4CAF50', '#9E9E9E'];

        $data = [];
        foreach ($statuses as $status) {
            $data[] = CaseModel::where('status', $status)->count();
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'colors' => $colors
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
        $totalReports = [];

        for ($month = 1; $month <= 12; $month++) {
            // Count incident cases
            $incidentCount = DB::table('case_categories')
                ->join('cases', 'case_categories.case_id', '=', 'cases.id')
                ->where('case_categories.category_type', 'incident')
                ->whereMonth('cases.created_at', $month)
                ->whereYear('cases.created_at', now()->year)
                ->whereNull('case_categories.deleted_at')
                ->whereNull('cases.deleted_at')
                ->distinct('cases.id')
                ->count('cases.id');

            // Count feedback cases
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
            $totalReports[] = $incidentCount + $feedbackCount;
        }

        return [
            'labels' => $labels,
            'incidents' => $incidents,
            'feedback' => $feedback,
            'total_reports' => $totalReports
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
            $totalCases = CaseAssignment::where('investigator_id', $investigator->id)->count();
            $resolvedCases = CaseAssignment::where('investigator_id', $investigator->id)
                ->whereHas('case', function ($q) {
                    $q->whereIn('status', ['resolved', 'closed']);
                })->count();
            $activeCases = CaseAssignment::where('investigator_id', $investigator->id)
                ->whereHas('case', function ($q) {
                    $q->whereNotIn('status', ['resolved', 'closed']);
                })->count();

            // Calculate average resolution time
            $avgResolutionTime = CaseAssignment::where('investigator_id', $investigator->id)
                ->join('cases', 'case_assignments.case_id', '=', 'cases.id')
                ->whereNotNull('cases.case_closed_at')
                ->select(DB::raw('AVG(EXTRACT(EPOCH FROM (cases.case_closed_at - cases.created_at))/86400) as avg_days'))
                ->value('avg_days');

            // Calculate efficiency score
            $efficiencyScore = $totalCases > 0
                ? round(($resolvedCases / $totalCases) * 100, 1)
                : 0;

            // Get initials for avatar
            $nameParts = explode(' ', $investigator->name);
            $avatar = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));

            $leaderboard[] = [
                'id' => $investigator->id,
                'name' => $investigator->name,
                'email' => $investigator->email,
                'avatar' => $avatar,
                'cases_resolved' => $resolvedCases,
                'avg_resolution_time_days' => $avgResolutionTime ? round($avgResolutionTime, 1) : 0,
                'satisfaction_rate' => rand(90, 99), // TODO: Calculate from actual feedback
                'active_cases' => $activeCases,
                'department' => 'Investigation', // TODO: Get from actual department data
                'efficiency_score' => $efficiencyScore,
                'total_cases_assigned' => $totalCases
            ];
        }

        // Sort by cases resolved and add rank
        usort($leaderboard, fn($a, $b) => $b['cases_resolved'] - $a['cases_resolved']);
        foreach ($leaderboard as $index => &$investigator) {
            $investigator['rank'] = $index + 1;
        }

        return array_slice($leaderboard, 0, 10); // Return top 10
    }

    /**
     * Get feedback sentiment trends
     */
    private function getFeedbackSentimentTrends(): array
    {
        $labels = [];
        $positive = [];
        $neutral = [];
        $negative = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $labels[] = $month->format('M');

            // TODO: Replace with actual sentiment analysis from feedback
            $positive[] = rand(65, 80);
            $neutral[] = rand(15, 25);
            $negative[] = rand(5, 10);
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
        // TODO: Replace with actual subscription/revenue data
        $labels = ['Q1 2024', 'Q2 2024', 'Q3 2024', 'Q4 2024', 'Q1 2025', 'Q2 2025'];
        $subscriptionRevenue = [250000, 275000, 295000, 320000, 345000, 370000];
        $growthPercentage = [];

        for ($i = 1; $i < count($subscriptionRevenue); $i++) {
            $growth = round((($subscriptionRevenue[$i] - $subscriptionRevenue[$i - 1]) / $subscriptionRevenue[$i - 1]) * 100, 0);
            $growthPercentage[] = $growth;
        }
        array_unshift($growthPercentage, 10); // First quarter baseline

        return [
            'labels' => $labels,
            'subscription_revenue' => $subscriptionRevenue,
            'growth_percentage' => $growthPercentage
        ];
    }

    /**
     * Get branch activity heatmap
     */
    private function getBranchActivityHeatmap(): array
    {
        $branches = Branch::with('company')
            ->where('status', 'active')
            ->get();

        $heatmap = [];
        foreach ($branches as $branch) {
            $totalCases = CaseModel::where('branch_id', $branch->id)->count();
            $resolvedCases = CaseModel::where('branch_id', $branch->id)
                ->whereIn('status', ['resolved', 'closed'])
                ->count();
            $activeCases = CaseModel::where('branch_id', $branch->id)
                ->whereNotIn('status', ['resolved', 'closed'])
                ->count();

            // Calculate average resolution time
            $avgResolutionTime = CaseModel::where('branch_id', $branch->id)
                ->whereNotNull('case_closed_at')
                ->select(DB::raw('AVG(EXTRACT(EPOCH FROM (case_closed_at - created_at))/86400) as avg_days'))
                ->value('avg_days');

            // Calculate activity score (based on total cases and resolution rate)
            $resolutionRate = $totalCases > 0 ? ($resolvedCases / $totalCases) : 0;
            $activityScore = min(100, round(($totalCases * 0.5) + ($resolutionRate * 50), 0));

            $heatmap[] = [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'company_name' => $branch->company->name ?? 'N/A',
                'location' => $branch->address ?? $branch->name,
                'total_cases' => $totalCases,
                'resolved_cases' => $resolvedCases,
                'active_cases' => $activeCases,
                'avg_resolution_time' => $avgResolutionTime ? round($avgResolutionTime, 1) : 0,
                'activity_score' => $activityScore,
                'coordinates' => [0, 0] // TODO: Add actual coordinates from branch data
            ];
        }

        // Sort by activity score
        usort($heatmap, fn($a, $b) => $b['activity_score'] - $a['activity_score']);

        return $heatmap;
    }

    /**
     * Get case resolution time distribution
     */
    private function getCaseResolutionTime(): array
    {
        $labels = ['0-1 Days', '2-3 Days', '4-7 Days', '8-15 Days', '16-30 Days', '30+ Days'];
        $data = [0, 0, 0, 0, 0, 0];

        $cases = CaseModel::whereNotNull('case_closed_at')
            ->select(DB::raw('EXTRACT(EPOCH FROM (case_closed_at - created_at))/86400 as days'))
            ->get();

        foreach ($cases as $case) {
            $days = $case->days;
            if ($days <= 1) $data[0]++;
            elseif ($days <= 3) $data[1]++;
            elseif ($days <= 7) $data[2]++;
            elseif ($days <= 15) $data[3]++;
            elseif ($days <= 30) $data[4]++;
            else $data[5]++;
        }

        $totalResolved = array_sum($data);
        $withinSla = $totalResolved > 0
            ? round((($data[0] + $data[1] + $data[2]) / $totalResolved) * 100, 1)
            : 0;

        return [
            'labels' => $labels,
            'data' => $data,
            'sla_target' => '7 days',
            'within_sla_percentage' => $withinSla
        ];
    }

    /**
     * Get company statistics
     */
    private function getCompanyStatistics(): array
    {
        return [
            'total_companies' => Company::count(),
            'active_companies' => Company::where('status', 'active')->count(),
            'total_branches' => Branch::count(),
            'active_branches' => Branch::where('status', 'active')->count(),
            'total_users' => User::count(),
            'active_users' => User::where('status', 'active')->count()
        ];
    }

    /**
     * Get system health metrics
     */
    private function getSystemHealth(): array
    {
        // TODO: Implement actual system health monitoring
        return [
            'uptime_percentage' => 99.9,
            'avg_response_time_ms' => 125,
            'api_requests_per_day' => 15420,
            'storage_used_percentage' => 68,
            'database_queries_per_second' => 45
        ];
    }

    /**
     * Get recent activities
     */
    private function getRecentActivities(): array
    {
        $recentCases = CaseModel::with(['company', 'branch'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $activities = [];
        foreach ($recentCases as $case) {
            $type = in_array($case->status, ['resolved', 'closed']) ? 'case_resolved' : 'case_created';
            $description = $type === 'case_resolved'
                ? 'Case resolved successfully'
                : 'New case reported';

            $activity = [
                'id' => uniqid('activity_', true),
                'type' => $type,
                'description' => $description,
                'case_id' => $case->id,
                'case_token' => $case->case_token,
                'company_name' => $case->company->name ?? 'N/A',
                'branch_name' => $case->branch->name ?? 'N/A',
                'priority' => $case->priority
            ];

            if ($type === 'case_resolved') {
                $activity['resolved_at'] = $case->case_closed_at;
                if ($case->case_closed_at) {
                    $resolutionTime = Carbon::parse($case->created_at)->diffInDays(Carbon::parse($case->case_closed_at), false);
                    $activity['resolution_time_days'] = round($resolutionTime, 1);
                }
            } else {
                $activity['created_at'] = $case->created_at;
            }

            $activities[] = $activity;
        }

        return $activities;
    }
}
