<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaseModel;
use App\Models\CaseCategory;
use App\Models\IncidentCategory;
use App\Models\FeedbackCategory;
use App\Models\Branch;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CategoryCaseDistributionController extends Controller
{
    /**
     * Get category case distribution analytics
     */
    public function getCategoryAnalytics(Request $request)
    {
        try {
            $user = $request->user();
            $companyId = $user->company_id;
            $branchId = $user->branch_id;

            // Base query for case categories
            $query = CaseCategory::with([
                'case:id,status,created_at,case_closed_at,case_token,title,company_id,branch_id,type',
                'case.company:id,name',
                'case.branch:id,name'
            ]);

            // Apply filters based on user role and request parameters
            if ($user->role === 'branch_admin') {
                // Branch admins see only cases from their specific branch
                $query->whereHas('case', function ($q) use ($companyId, $branchId) {
                    $q->where('company_id', $companyId)
                        ->where('branch_id', $branchId);
                });
            } elseif ($user->role === 'company_admin') {
                // Company admins see cases from all branches under their company
                $query->whereHas('case', function ($q) use ($companyId, $request) {
                    $q->where('company_id', $companyId);

                    if ($request->has('branch_id') && $request->branch_id) {
                        $q->where('branch_id', $request->branch_id);
                    }
                });
            } else {
                // Super admins can filter by company and branch
                if ($request->has('company_id') && $request->company_id) {
                    $query->whereHas('case', function ($q) use ($request) {
                        $q->where('company_id', $request->company_id);

                        if ($request->has('branch_id') && $request->branch_id) {
                            $q->where('branch_id', $request->branch_id);
                        }
                    });
                }
            }

            // Apply additional filters
            if ($request->has('category_type') && in_array($request->category_type, ['incident', 'feedback'])) {
                $query->where('category_type', $request->category_type);
            }

            if ($request->has('category_id') && $request->category_id) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->has('status') && $request->status) {
                $query->whereHas('case', function ($q) use ($request) {
                    $q->where('status', $request->status);
                });
            }

            if ($request->has('start_date') && $request->start_date) {
                $query->whereHas('case', function ($q) use ($request) {
                    $q->where('created_at', '>=', Carbon::parse($request->start_date)->startOfDay());
                });
            }

            if ($request->has('end_date') && $request->end_date) {
                $query->whereHas('case', function ($q) use ($request) {
                    $q->where('created_at', '<=', Carbon::parse($request->end_date)->endOfDay());
                });
            }

            $caseCategories = $query->get();

            // Group by category and calculate statistics
            $categoryStats = $caseCategories->groupBy(function ($assignment) {
                return $assignment->category_type . '_' . $assignment->category_id;
            })->map(function ($assignments, $key) {
                $firstAssignment = $assignments->first();
                $categoryType = $firstAssignment->category_type;
                $categoryId = $firstAssignment->category_id;

                // Get category details
                if ($categoryType === 'incident') {
                    $category = IncidentCategory::find($categoryId, ['id', 'name', 'description']);
                } else {
                    $category = FeedbackCategory::find($categoryId, ['id', 'name', 'description']);
                }

                if (!$category) {
                    return null;
                }

                // Get unique cases for this category
                $cases = $assignments->pluck('case')->unique('id');
                $totalCases = $cases->count();
                $closedCases = $cases->where('status', 'closed')->count();
                $pendingCases = $totalCases - $closedCases;

                // Calculate average resolution time for closed cases
                $closedWithTime = $cases->filter(function ($case) {
                    return $case->status === 'closed' &&
                        $case->created_at &&
                        $case->case_closed_at;
                });

                $avgResolutionDays = 0;
                if ($closedWithTime->count() > 0) {
                    $totalHours = $closedWithTime->sum(function ($case) {
                        $created = Carbon::parse($case->created_at);
                        $closed = Carbon::parse($case->case_closed_at);
                        return $created->diffInHours($closed);
                    });
                    $avgResolutionHours = $totalHours / $closedWithTime->count();
                    $avgResolutionDays = round($avgResolutionHours / 24, 1);
                }

                $avgResolutionText = $avgResolutionDays > 0
                    ? ($avgResolutionDays >= 1
                        ? round($avgResolutionDays, 1) . ' days'
                        : round($avgResolutionDays * 24, 1) . ' hours')
                    : 'N/A';

                return [
                    'category' => [
                        'id' => $category->id,
                        'name' => $category->name,
                        'description' => $category->description ?? null,
                        'type' => $categoryType
                    ],
                    'total_cases' => $totalCases,
                    'closed' => $closedCases,
                    'pending' => $pendingCases,
                    'avg_resolution_time' => $avgResolutionText,
                    'avg_resolution_days' => $avgResolutionDays,
                    'cases' => $cases->map(function ($case) {
                        return [
                            'id' => $case->id,
                            'case_token' => $case->case_token,
                            'title' => $case->title,
                            'status' => $case->status,
                            'type' => $case->type,
                            'created_at' => Carbon::parse($case->created_at)->format('Y-m-d'),
                            'closed_at' => $case->case_closed_at ? Carbon::parse($case->case_closed_at)->format('Y-m-d') : null,
                            'company' => $case->company?->name,
                            'branch' => $case->branch?->name
                        ];
                    })->values()
                ];
            })->filter()->values();

            // Sort by total cases (descending)
            $sortedStats = $categoryStats->sortByDesc('total_cases')->values();

            // Calculate summary statistics
            $totalCases = $sortedStats->sum('total_cases');
            $totalClosed = $sortedStats->sum('closed');
            $totalPending = $sortedStats->sum('pending');

            // Get filter options for response
            $filterOptions = $this->getFilterOptions($user, $companyId, $branchId);

            return response()->json([
                'status' => 'success',
                'message' => 'Category case distribution retrieved successfully',
                'data' => [
                    'categories' => $sortedStats,
                    'summary' => [
                        'total_categories' => $categoryStats->count(),
                        'total_cases' => $totalCases,
                        'total_closed' => $totalClosed,
                        'total_pending' => $totalPending,
                        'overall_closure_rate' => $totalCases > 0 ? round(($totalClosed / $totalCases) * 100, 1) : 0
                    ],
                    'filter_options' => $filterOptions
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get category case distribution',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get category trends over time
     */
    public function getCategoryTrends(Request $request)
    {
        try {
            $user = $request->user();
            $companyId = $user->company_id;
            $branchId = $user->branch_id;
            $period = $request->input('period', 'monthly'); // weekly or monthly
            $categoryId = $request->input('category_id');
            $categoryType = $request->input('category_type');

            // Base query
            $query = CaseCategory::with([
                'case:id,status,created_at,case_closed_at,company_id,branch_id',
            ]);

            // Apply role-based filters
            if ($user->role === 'branch_admin') {
                $query->whereHas('case', function ($q) use ($companyId, $branchId) {
                    $q->where('company_id', $companyId)
                        ->where('branch_id', $branchId);
                });
            } elseif ($user->role === 'company_admin') {
                $query->whereHas('case', function ($q) use ($companyId, $request) {
                    $q->where('company_id', $companyId);

                    if ($request->has('branch_id') && $request->branch_id) {
                        $q->where('branch_id', $request->branch_id);
                    }
                });
            } else {
                if ($request->has('company_id') && $request->company_id) {
                    $query->whereHas('case', function ($q) use ($request) {
                        $q->where('company_id', $request->company_id);

                        if ($request->has('branch_id') && $request->branch_id) {
                            $q->where('branch_id', $request->branch_id);
                        }
                    });
                }
            }

            // Apply category filters
            if ($categoryId) {
                $query->where('category_id', $categoryId);
            }

            if ($categoryType && in_array($categoryType, ['incident', 'feedback'])) {
                $query->where('category_type', $categoryType);
            }

            // Date range filter
            if ($request->has('start_date')) {
                $query->whereHas('case', function ($q) use ($request) {
                    $q->where('created_at', '>=', Carbon::parse($request->start_date)->startOfDay());
                });
            }

            if ($request->has('end_date')) {
                $query->whereHas('case', function ($q) use ($request) {
                    $q->where('created_at', '<=', Carbon::parse($request->end_date)->endOfDay());
                });
            }

            $caseCategories = $query->get();

            // Group by time period
            $trends = $caseCategories->groupBy(function ($assignment) use ($period) {
                $date = Carbon::parse($assignment->case->created_at);
                return $period === 'monthly'
                    ? $date->format('Y-m')
                    : $date->format('Y-W');
            })->map(function ($assignments, $periodKey) use ($period) {
                // Format period label
                if ($period === 'monthly') {
                    $date = Carbon::parse($periodKey . '-01');
                    $periodLabel = $date->format('M Y');
                } else {
                    list($year, $week) = explode('-', $periodKey);
                    $date = Carbon::now()->setISODate($year, $week);
                    $periodLabel = 'Week ' . $week . ', ' . $year;
                }

                // Group by category within period
                $categoryGroups = $assignments->groupBy(function ($assignment) {
                    return $assignment->category_type . '_' . $assignment->category_id;
                });

                $categories = $categoryGroups->map(function ($catAssignments, $key) {
                    $firstAssignment = $catAssignments->first();
                    $categoryType = $firstAssignment->category_type;
                    $categoryId = $firstAssignment->category_id;

                    // Get category name
                    if ($categoryType === 'incident') {
                        $category = IncidentCategory::find($categoryId, ['id', 'name']);
                    } else {
                        $category = FeedbackCategory::find($categoryId, ['id', 'name']);
                    }

                    $cases = $catAssignments->pluck('case')->unique('id');
                    $totalCases = $cases->count();
                    $closedCases = $cases->where('status', 'closed')->count();
                    $pendingCases = $totalCases - $closedCases;

                    return [
                        'category_name' => $category ? $category->name : 'Unknown',
                        'category_type' => $categoryType,
                        'cases' => $totalCases,
                        'closed' => $closedCases,
                        'pending' => $pendingCases,
                        'closure_rate' => $totalCases > 0 ? round(($closedCases / $totalCases) * 100, 1) : 0
                    ];
                })->values();

                return [
                    'period_key' => $periodKey,
                    'period_label' => $periodLabel,
                    'categories' => $categories,
                    'total_cases' => $categories->sum('cases'),
                    'total_closed' => $categories->sum('closed'),
                    'total_pending' => $categories->sum('pending')
                ];
            })->sortBy('period_key')->values();

            // Get category details if filtering by specific category
            $categoryDetails = null;
            if ($categoryId && $categoryType) {
                if ($categoryType === 'incident') {
                    $categoryDetails = IncidentCategory::find($categoryId, ['id', 'name', 'description']);
                } else {
                    $categoryDetails = FeedbackCategory::find($categoryId, ['id', 'name', 'description']);
                }
                if ($categoryDetails) {
                    $categoryDetails->type = $categoryType;
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Category case distribution trends retrieved successfully',
                'data' => [
                    'period' => $period,
                    'trends' => $trends,
                    'category' => $categoryDetails
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get category trends',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export category distribution data as CSV
     */
    public function exportCategoryData(Request $request)
    {
        try {
            // Get the analytics data
            $analyticsResponse = $this->getCategoryAnalytics($request);
            $data = json_decode($analyticsResponse->getContent(), true);

            if ($data['status'] !== 'success') {
                return $analyticsResponse;
            }

            $categories = $data['data']['categories'];

            // Prepare CSV data
            $csvData = [];
            $csvData[] = ['Category', 'Type', 'Total Cases', 'Closed', 'Pending', 'Avg Resolution Time'];

            foreach ($categories as $cat) {
                $csvData[] = [
                    $cat['category']['name'],
                    ucfirst($cat['category']['type']),
                    $cat['total_cases'],
                    $cat['closed'],
                    $cat['pending'],
                    $cat['avg_resolution_time']
                ];
            }

            // Generate CSV content
            $output = fopen('php://temp', 'r+');
            foreach ($csvData as $row) {
                fputcsv($output, $row);
            }
            rewind($output);
            $csvContent = stream_get_contents($output);
            fclose($output);

            // Return CSV response
            return response($csvContent, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="category_case_distribution_' . date('Y-m-d_H-i-s') . '.csv"'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to export category distribution data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available filter options for category distribution
     */
    public function getFilters(Request $request)
    {
        try {
            $user = $request->user();
            $companyId = $user->company_id;
            $branchId = $user->branch_id;

            $filterOptions = $this->getFilterOptions($user, $companyId, $branchId);

            return response()->json([
                'status' => 'success',
                'message' => 'Filter options retrieved successfully',
                'data' => $filterOptions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve filter options',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get filter options based on user role
     */
    public function getFilterOptions($user, $companyId, $branchId)
    {
        $options = [
            'companies' => [],
            'branches' => [],
            'incident_categories' => [],
            'feedback_categories' => []
        ];

        if ($user->role === 'super_admin' || $user->role === 'system_admin' || $user->role === 'admin') {
            // Super admins can filter by company
            $options['companies'] = Company::select('id', 'name')->orderBy('name')->get();
        }

        if (
            $user->role === 'company_admin' ||
            ($user->role === 'super_admin' || $user->role === 'system_admin' || $user->role === 'admin')
        ) {
            // Company admins and super admins can filter by branches
            $branchQuery = Branch::select('id', 'name', 'company_id')->orderBy('name');

            if ($user->role === 'company_admin') {
                $branchQuery->where('company_id', $companyId);
            }

            $options['branches'] = $branchQuery->get();
        }

        // Get available categories based on user access
        $incidentQuery = IncidentCategory::select('id', 'name', 'company_id')->orderBy('name');
        $feedbackQuery = FeedbackCategory::select('id', 'name', 'company_id')->orderBy('name');

        if ($user->role === 'branch_admin' || $user->role === 'company_admin') {
            $incidentQuery->where('company_id', $companyId);
            $feedbackQuery->where('company_id', $companyId);
        }

        $options['incident_categories'] = $incidentQuery->get();
        $options['feedback_categories'] = $feedbackQuery->get();

        return $options;
    }
}
