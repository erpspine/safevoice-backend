<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaseModel;
use App\Models\CaseAssignment;
use App\Models\User;
use App\Models\Branch;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InvestigatorAllocationController extends Controller
{
    /**
     * Get investigator allocation analytics
     */
    public function getAllocationAnalytics(Request $request)
    {
        try {
            $user = $request->user();
            $companyId = $user->company_id;
            $branchId = $user->branch_id;

            // Base query for case investigators
            $query = CaseAssignment::with([
                'investigator:id,name,email,company_id,branch_id',
                'investigator.company:id,name',
                'investigator.branch:id,name',
                'case:id,status,created_at,case_closed_at,case_token,title,company_id,branch_id,type'
            ]);

            // Apply filters based on user role and request parameters
            if ($user->role === 'branch_admin') {
                // Branch admins see only investigators from their specific branch
                $query->whereHas('case', function ($q) use ($companyId, $branchId) {
                    $q->where('company_id', $companyId)
                        ->where('branch_id', $branchId);
                });
            } elseif ($user->role === 'company_admin') {
                // Company admins see investigators from all branches under their company
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
            if ($request->has('investigator_id') && $request->investigator_id) {
                $query->where('investigator_id', $request->investigator_id);
            }

            if ($request->has('status') && $request->status) {
                $query->whereHas('case', function ($q) use ($request) {
                    $q->where('status', $request->status);
                });
            }

            if ($request->has('start_date') && $request->start_date) {
                $query->where('assigned_at', '>=', Carbon::parse($request->start_date)->startOfDay());
            }

            if ($request->has('end_date') && $request->end_date) {
                $query->where('assigned_at', '<=', Carbon::parse($request->end_date)->endOfDay());
            }

            $caseInvestigators = $query->get();

            // Group by investigator and calculate statistics
            $investigatorStats = $caseInvestigators->groupBy('investigator_id')->map(function ($assignments, $investigatorId) {
                $investigator = $assignments->first()->investigator;

                if (!$investigator) {
                    return null;
                }

                // Get unique cases for this investigator
                $cases = $assignments->pluck('case')->unique('id');
                $assignedCases = $cases->count();
                $closedCases = $cases->where('status', 'closed')->count();
                $pendingCases = $assignedCases - $closedCases;

                // Calculate average closure time for closed cases
                $closedWithTime = $cases->filter(function ($case) {
                    return $case->status === 'closed' &&
                        $case->created_at &&
                        $case->case_closed_at;
                });

                $avgClosureDays = 0;
                if ($closedWithTime->count() > 0) {
                    $totalHours = $closedWithTime->sum(function ($case) {
                        $created = Carbon::parse($case->created_at);
                        $closed = Carbon::parse($case->case_closed_at);
                        return $created->diffInHours($closed);
                    });
                    $avgClosureHours = $totalHours / $closedWithTime->count();
                    $avgClosureDays = round($avgClosureHours / 24, 1);
                }

                $avgClosureText = $avgClosureDays > 0
                    ? ($avgClosureDays >= 1
                        ? round($avgClosureDays, 1) . ' days'
                        : round($avgClosureDays * 24, 1) . ' hours')
                    : 'N/A';

                // Calculate SLA compliance (assuming 7 days SLA)
                $slaThresholdDays = 7;
                $slaCompliantCases = 0;

                if ($closedWithTime->count() > 0) {
                    $slaCompliantCases = $closedWithTime->filter(function ($case) use ($slaThresholdDays) {
                        $created = Carbon::parse($case->created_at);
                        $closed = Carbon::parse($case->case_closed_at);
                        $daysTaken = $created->diffInDays($closed);
                        return $daysTaken <= $slaThresholdDays;
                    })->count();
                }

                $slaComplianceRate = $closedCases > 0
                    ? round(($slaCompliantCases / $closedCases) * 100, 1)
                    : 0;

                return [
                    'investigator' => [
                        'id' => $investigator->id,
                        'name' => $investigator->name,
                        'email' => $investigator->email,
                        'company' => $investigator->company?->name,
                        'branch' => $investigator->branch?->name
                    ],
                    'assigned_cases' => $assignedCases,
                    'closed_cases' => $closedCases,
                    'pending' => $pendingCases,
                    'avg_closure_time' => $avgClosureText,
                    'avg_closure_days' => $avgClosureDays,
                    'sla_compliance_percent' => $slaComplianceRate,
                    'sla_compliant_cases' => $slaCompliantCases,
                    'cases' => $cases->map(function ($case) use ($assignments) {
                        $assignment = $assignments->where('case_id', $case->id)->first();
                        return [
                            'id' => $case->id,
                            'case_token' => $case->case_token,
                            'title' => $case->title,
                            'status' => $case->status,
                            'type' => $case->type,
                            'assigned_at' => $assignment ? Carbon::parse($assignment->assigned_at)->format('Y-m-d H:i:s') : null,
                            'created_at' => Carbon::parse($case->created_at)->format('Y-m-d'),
                            'closed_at' => $case->case_closed_at ? Carbon::parse($case->case_closed_at)->format('Y-m-d') : null,
                            'company' => $case->company?->name,
                            'branch' => $case->branch?->name
                        ];
                    })->values()
                ];
            })->filter()->values();

            // Sort by assigned cases (descending)
            $sortedStats = $investigatorStats->sortByDesc('assigned_cases')->values();

            // Calculate summary statistics
            $totalAssignedCases = $sortedStats->sum('assigned_cases');
            $totalClosedCases = $sortedStats->sum('closed_cases');
            $totalPendingCases = $sortedStats->sum('pending');
            $totalSlaCompliant = $sortedStats->sum('sla_compliant_cases');

            // Get filter options for response
            $filterOptions = $this->getFilterOptions($user, $companyId, $branchId);

            return response()->json([
                'status' => 'success',
                'message' => 'Investigator allocation retrieved successfully',
                'data' => [
                    'investigators' => $sortedStats,
                    'summary' => [
                        'total_investigators' => $investigatorStats->count(),
                        'total_assigned_cases' => $totalAssignedCases,
                        'total_closed_cases' => $totalClosedCases,
                        'total_pending_cases' => $totalPendingCases,
                        'overall_closure_rate' => $totalAssignedCases > 0 ? round(($totalClosedCases / $totalAssignedCases) * 100, 1) : 0,
                        'overall_sla_compliance' => $totalClosedCases > 0 ? round(($totalSlaCompliant / $totalClosedCases) * 100, 1) : 0
                    ],
                    'filter_options' => $filterOptions
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get investigator allocation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get investigator allocation trends over time
     */
    public function getAllocationTrends(Request $request)
    {
        try {
            $user = $request->user();
            $companyId = $user->company_id;
            $branchId = $user->branch_id;
            $period = $request->input('period', 'monthly'); // weekly or monthly
            $investigatorId = $request->input('investigator_id');

            // Base query
            $query = CaseAssignment::with([
                'investigator:id,name',
                'case:id,status,created_at,case_closed_at'
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

            // Apply investigator filter
            if ($investigatorId) {
                $query->where('investigator_id', $investigatorId);
            }

            // Date range filter
            if ($request->has('start_date')) {
                $query->where('assigned_at', '>=', Carbon::parse($request->start_date)->startOfDay());
            }

            if ($request->has('end_date')) {
                $query->where('assigned_at', '<=', Carbon::parse($request->end_date)->endOfDay());
            }

            $caseInvestigators = $query->get();

            // Group by time period
            $trends = $caseInvestigators->groupBy(function ($assignment) use ($period) {
                $date = Carbon::parse($assignment->assigned_at);
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

                // Group by investigator within period
                $investigatorGroups = $assignments->groupBy('investigator_id');

                $investigators = $investigatorGroups->map(function ($invAssignments, $invId) {
                    $investigator = $invAssignments->first()->investigator;

                    $cases = $invAssignments->pluck('case')->unique('id');
                    $assignedCases = $cases->count();
                    $closedCases = $cases->where('status', 'closed')->count();
                    $pendingCases = $assignedCases - $closedCases;

                    return [
                        'investigator_name' => $investigator ? $investigator->name : 'Unknown',
                        'assigned' => $assignedCases,
                        'closed' => $closedCases,
                        'pending' => $pendingCases,
                        'closure_rate' => $assignedCases > 0 ? round(($closedCases / $assignedCases) * 100, 1) : 0
                    ];
                })->values();

                return [
                    'period_key' => $periodKey,
                    'period_label' => $periodLabel,
                    'investigators' => $investigators,
                    'total_assigned' => $investigators->sum('assigned'),
                    'total_closed' => $investigators->sum('closed'),
                    'total_pending' => $investigators->sum('pending')
                ];
            })->sortBy('period_key')->values();

            // Get investigator details if filtering by specific investigator
            $investigatorDetails = null;
            if ($investigatorId) {
                $investigatorDetails = User::find($investigatorId, ['id', 'name', 'email']);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Investigator allocation trends retrieved successfully',
                'data' => [
                    'period' => $period,
                    'trends' => $trends,
                    'investigator' => $investigatorDetails
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get allocation trends',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export investigator allocation data as CSV
     */
    public function exportAllocationData(Request $request)
    {
        try {
            // Get the analytics data
            $analyticsResponse = $this->getAllocationAnalytics($request);
            $data = json_decode($analyticsResponse->getContent(), true);

            if ($data['status'] !== 'success') {
                return $analyticsResponse;
            }

            $investigators = $data['data']['investigators'];

            // Prepare CSV data
            $csvData = [];
            $csvData[] = ['Investigator', 'Email', 'Assigned Cases', 'Closed Cases', 'Pending', 'Avg Closure Time', 'SLA Compliance %'];

            foreach ($investigators as $inv) {
                $csvData[] = [
                    $inv['investigator']['name'],
                    $inv['investigator']['email'],
                    $inv['assigned_cases'],
                    $inv['closed_cases'],
                    $inv['pending'],
                    $inv['avg_closure_time'],
                    $inv['sla_compliance_percent']
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
                'Content-Disposition' => 'attachment; filename="investigator_allocation_' . date('Y-m-d_H-i-s') . '.csv"'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to export allocation data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available filter options for investigator allocation
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
            'investigators' => []
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

        // Get available investigators based on user access
        $investigatorQuery = User::select('id', 'name', 'email', 'company_id', 'branch_id')
            ->where('role', 'investigator')
            ->orderBy('name');

        if ($user->role === 'branch_admin') {
            $investigatorQuery->where('company_id', $companyId)
                ->where('branch_id', $branchId);
        } elseif ($user->role === 'company_admin') {
            $investigatorQuery->where('company_id', $companyId);
        }

        $options['investigators'] = $investigatorQuery->get();

        return $options;
    }
}
