<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaseModel;
use App\Models\CaseDepartment;
use App\Models\Department;
use App\Models\Branch;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DepartmentalCaseDistributionController extends Controller
{
    /**
     * Get departmental case distribution analytics
     */
    public function getDistributionAnalytics(Request $request)
    {
        try {
            $user = $request->user();
            $companyId = $user->company_id;
            $branchId = $user->branch_id;

            // Base query for case departments
            $query = CaseDepartment::with([
                'department:id,name,description',
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
                // But can filter by branch if provided
                $query->whereHas('case', function ($q) use ($companyId, $request) {
                    $q->where('company_id', $companyId);

                    if ($request->has('branch_id') && $request->branch_id) {
                        $q->where('branch_id', $request->branch_id);
                    }
                });
            } elseif ($user->role === 'investigator') {
                $query->whereHas('case.assignments', function ($q) use ($user) {
                    $q->where('investigator_id', $user->id);
                });
            } elseif ($user->role === 'super_admin' || $user->role === 'system_admin' || $user->role === 'admin') {
                // System/Super/Admin can filter by company if provided
                if ($request->has('company_id') && $request->company_id) {
                    $query->whereHas('case', function ($q) use ($request) {
                        $q->where('company_id', $request->company_id);

                        if ($request->has('branch_id') && $request->branch_id) {
                            $q->where('branch_id', $request->branch_id);
                        }
                    });
                }
            } else {
                // For other roles, filter by company if available
                if ($companyId) {
                    $query->whereHas('case', function ($q) use ($companyId) {
                        $q->where('company_id', $companyId);
                    });
                }
            }

            // Apply date filters if provided
            if ($request->has('start_date')) {
                $query->whereHas('case', function ($q) use ($request) {
                    $q->whereDate('created_at', '>=', $request->start_date);
                });
            }

            if ($request->has('end_date')) {
                $query->whereHas('case', function ($q) use ($request) {
                    $q->whereDate('created_at', '<=', $request->end_date);
                });
            }

            // Get the case department assignments
            $caseDepartments = $query->get();

            // Group by department and calculate statistics
            $departmentStats = $caseDepartments->groupBy('department_id')->map(function ($assignments, $departmentId) {
                $department = $assignments->first()->department;
                $cases = $assignments->pluck('case')->unique('id');

                $totalCases = $cases->count();
                $closedCases = $cases->where('status', 'closed')->count();
                $pendingCases = $totalCases - $closedCases;

                // Calculate average resolution time for closed cases
                $closedCasesWithTime = $cases->where('status', 'closed')
                    ->filter(function ($case) {
                        return $case->case_closed_at !== null;
                    });

                $avgResolutionDays = 0;
                $avgResolutionText = '0 days';

                if ($closedCasesWithTime->count() > 0) {
                    $totalResolutionDays = $closedCasesWithTime->sum(function ($case) {
                        $created = Carbon::parse($case->created_at);
                        $closed = Carbon::parse($case->case_closed_at);
                        return $closed->diffInDays($created);
                    });

                    $avgResolutionDays = round($totalResolutionDays / $closedCasesWithTime->count(), 1);
                    $avgResolutionText = $avgResolutionDays . ' day' . ($avgResolutionDays != 1 ? 's' : '');
                }

                return [
                    'department' => [
                        'id' => $department->id,
                        'name' => $department->name,
                        'description' => $department->description
                    ],
                    'number_of_cases' => $totalCases,
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
            });

            // Sort by number of cases (descending)
            $sortedStats = $departmentStats->sortByDesc('number_of_cases')->values();

            // Calculate summary statistics
            $totalCases = $caseDepartments->pluck('case')->unique('id')->count();
            $totalClosed = $caseDepartments->pluck('case')->unique('id')->where('status', 'closed')->count();
            $totalPending = $totalCases - $totalClosed;

            // Get filter options for response
            $filterOptions = $this->getFilterOptions($user, $companyId, $branchId);

            return response()->json([
                'status' => 'success',
                'message' => 'Departmental case distribution retrieved successfully',
                'data' => [
                    'departments' => $sortedStats,
                    'summary' => [
                        'total_departments' => $departmentStats->count(),
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
                'message' => 'Failed to get departmental case distribution',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get departmental trends over time
     */
    public function getDistributionTrends(Request $request)
    {
        try {
            $user = $request->user();
            $companyId = $user->company_id;
            $branchId = $user->branch_id;
            $period = $request->get('period', 'monthly'); // monthly or weekly
            $departmentId = $request->get('department_id');

            // Base query
            $query = CaseDepartment::with([
                'department:id,name',
                'case:id,status,created_at,case_closed_at,company_id,branch_id'
            ]);

            // Apply role-based filtering
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
            } elseif ($user->role === 'super_admin' || $user->role === 'system_admin' || $user->role === 'admin') {
                if ($request->has('company_id') && $request->company_id) {
                    $query->whereHas('case', function ($q) use ($request) {
                        $q->where('company_id', $request->company_id);
                    });
                }
            } else {
                if ($companyId) {
                    $query->whereHas('case', function ($q) use ($companyId) {
                        $q->where('company_id', $companyId);
                    });
                }
            }

            // Filter by specific department if provided
            if ($departmentId) {
                $query->where('department_id', $departmentId);
            }

            // Date range for trends
            $endDate = Carbon::now();
            $startDate = $period === 'monthly'
                ? $endDate->copy()->subMonths(12)
                : $endDate->copy()->subWeeks(12);

            $query->whereHas('case', function ($q) use ($startDate, $endDate) {
                $q->whereDate('created_at', '>=', $startDate)
                    ->whereDate('created_at', '<=', $endDate);
            });

            $caseDepartments = $query->get();

            // Group by time period
            $trends = $caseDepartments->groupBy(function ($assignment) use ($period) {
                $date = Carbon::parse($assignment->case->created_at);
                return $period === 'monthly'
                    ? $date->format('Y-m')
                    : $date->format('Y-W');
            })->map(function ($assignments, $periodKey) use ($period) {
                $cases = $assignments->pluck('case')->unique('id');
                $totalCases = $cases->count();
                $closedCases = $cases->where('status', 'closed')->count();

                // Format period name
                if ($period === 'monthly') {
                    $periodName = Carbon::createFromFormat('Y-m', $periodKey)->format('M Y');
                } else {
                    [$year, $week] = explode('-', $periodKey);
                    $periodName = "Week $week, $year";
                }

                return [
                    'period' => $periodName,
                    'period_key' => $periodKey,
                    'total_cases' => $totalCases,
                    'closed_cases' => $closedCases,
                    'pending_cases' => $totalCases - $closedCases,
                    'closure_rate' => $totalCases > 0 ? round(($closedCases / $totalCases) * 100, 1) : 0
                ];
            })->sortBy('period_key')->values();

            return response()->json([
                'status' => 'success',
                'message' => 'Departmental case distribution trends retrieved successfully',
                'data' => [
                    'period' => $period,
                    'trends' => $trends,
                    'department' => $departmentId ? Department::find($departmentId, ['id', 'name']) : null
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get departmental trends',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export departmental distribution data to CSV
     */
    public function exportDistributionData(Request $request)
    {
        try {
            // Get the analytics data
            $analyticsResponse = $this->getDistributionAnalytics($request);
            $data = json_decode($analyticsResponse->getContent(), true);

            if ($data['status'] !== 'success') {
                return $analyticsResponse;
            }

            $departments = $data['data']['departments'];

            // Prepare CSV data
            $csvData = [];
            $csvData[] = ['Department', 'Number of Cases', 'Closed', 'Pending', 'Avg Resolution Time'];

            foreach ($departments as $dept) {
                $csvData[] = [
                    $dept['department']['name'],
                    $dept['number_of_cases'],
                    $dept['closed'],
                    $dept['pending'],
                    $dept['avg_resolution_time']
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
                'Content-Disposition' => 'attachment; filename="departmental_case_distribution_' . date('Y-m-d_H-i-s') . '.csv"'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to export departmental distribution data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available filter options for departmental distribution
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
            'departments' => []
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

        // Get available departments based on user access
        $departmentQuery = Department::select('id', 'name', 'company_id')->orderBy('name');

        if ($user->role === 'branch_admin') {
            $departmentQuery->where('company_id', $companyId);
        } elseif ($user->role === 'company_admin') {
            $departmentQuery->where('company_id', $companyId);
        }

        $options['departments'] = $departmentQuery->get();

        return $options;
    }
}
