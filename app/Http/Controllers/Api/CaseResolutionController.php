<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaseModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CaseResolutionController extends Controller
{
    /**
     * Get case resolution time analytics
     */
    public function getResolutionAnalytics(Request $request)
    {
        try {
            $user = $request->user();
            $companyId = $user->company_id;
            $branchId = $user->branch_id;

            // Base query for closed cases
            $query = CaseModel::where('status', 'closed')
                ->whereNotNull('case_closed_at');

            // Apply company/branch filtering based on user role
            if ($user->role === 'branch_admin') {
                // Branch admins see only cases from their specific branch
                $query->where('company_id', $companyId)
                    ->where('branch_id', $branchId);
            } elseif ($user->role === 'company_admin') {
                // Company admins see cases from all branches under their company
                $query->where('company_id', $companyId);
            } elseif ($user->role === 'investigator') {
                // For investigators, show cases assigned to them
                $query->whereHas('assignments', function ($q) use ($user) {
                    $q->where('investigator_id', $user->id);
                });
            } elseif ($user->role === 'super_admin' || $user->role === 'system_admin' || $user->role === 'admin') {
                // System/Super/Admin can see all cases, no additional filter needed
            } else {
                // For other roles, filter by company if available
                if ($companyId) {
                    $query->where('company_id', $companyId);
                }
            }

            // Apply date filters if provided
            if ($request->has('start_date')) {
                $query->whereDate('case_closed_at', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->whereDate('case_closed_at', '<=', $request->end_date);
            }

            // Get the cases with necessary relationships
            $cases = $query->with([
                'company:id,name',
                'branch:id,name',
                'closedBy:id,name,email'
            ])->get();

            // Calculate resolution times and format response
            $resolutionData = $cases->map(function ($case) {
                $createdAt = Carbon::parse($case->created_at);
                $closedAt = Carbon::parse($case->case_closed_at);

                // Calculate duration in different units
                $durationInSeconds = $closedAt->diffInSeconds($createdAt);
                $durationInMinutes = $closedAt->diffInMinutes($createdAt);
                $durationInHours = $closedAt->diffInHours($createdAt);
                $durationInDays = $closedAt->diffInDays($createdAt);

                // Format duration text
                $durationText = '';
                if ($durationInDays > 0) {
                    $durationText = $durationInDays . ' day' . ($durationInDays > 1 ? 's' : '');
                    if ($durationInHours % 24 > 0) {
                        $durationText .= ' ' . ($durationInHours % 24) . ' hour' . (($durationInHours % 24) > 1 ? 's' : '');
                    }
                } elseif ($durationInHours > 0) {
                    $durationText = $durationInHours . ' hour' . ($durationInHours > 1 ? 's' : '');
                    if ($durationInMinutes % 60 > 0) {
                        $durationText .= ' ' . ($durationInMinutes % 60) . ' minute' . (($durationInMinutes % 60) > 1 ? 's' : '');
                    }
                } else {
                    $durationText = $durationInMinutes . ' minute' . ($durationInMinutes > 1 ? 's' : '');
                }

                return [
                    'case_id' => $case->case_token,
                    'case_title' => $case->title,
                    'submitted_on' => $createdAt->format('d/m/Y'),
                    'closed_on' => $closedAt->format('d/m/Y'),
                    'duration' => $durationText,
                    'duration_in_days' => $durationInDays,
                    'duration_in_hours' => $durationInHours,
                    'duration_in_minutes' => $durationInMinutes,
                    'close_classification' => $case->case_close_classification,
                    'close_remarks' => $case->resolution_note,
                    'closed_by' => [
                        'id' => $case->closedBy?->id,
                        'name' => $case->closedBy?->name,
                        'email' => $case->closedBy?->email
                    ],
                    'priority' => $case->priority,
                    'type' => $case->type,
                    'status' => $case->status,
                    'company' => $case->company?->name,
                    'branch' => $case->branch?->name
                ];
            });

            // Calculate summary statistics
            $totalCases = $resolutionData->count();
            $avgResolutionDays = $totalCases > 0 ? $resolutionData->avg('duration_in_days') : 0;
            $avgResolutionHours = $totalCases > 0 ? $resolutionData->avg('duration_in_hours') : 0;

            // Group by classification
            $byClassification = $resolutionData->groupBy('close_classification');

            // Group by time ranges
            $byTimeRange = [
                'same_day' => $resolutionData->where('duration_in_days', 0)->count(),
                '1_3_days' => $resolutionData->where('duration_in_days', '>=', 1)->where('duration_in_days', '<=', 3)->count(),
                '4_7_days' => $resolutionData->where('duration_in_days', '>=', 4)->where('duration_in_days', '<=', 7)->count(),
                '8_30_days' => $resolutionData->where('duration_in_days', '>=', 8)->where('duration_in_days', '<=', 30)->count(),
                'over_30_days' => $resolutionData->where('duration_in_days', '>', 30)->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'cases' => $resolutionData->values(),
                    'summary' => [
                        'total_cases' => $totalCases,
                        'average_resolution_days' => round($avgResolutionDays, 2),
                        'average_resolution_hours' => round($avgResolutionHours, 2),
                        'by_classification' => $byClassification->map(function ($cases, $classification) {
                            return [
                                'classification' => $classification,
                                'count' => $cases->count(),
                                'avg_days' => round($cases->avg('duration_in_days'), 2)
                            ];
                        })->values(),
                        'by_time_range' => $byTimeRange
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get resolution analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get resolution time trends (monthly/weekly)
     */
    public function getResolutionTrends(Request $request)
    {
        try {
            $user = $request->user();
            $companyId = $user->company_id;
            $branchId = $user->branch_id;
            $period = $request->get('period', 'monthly'); // monthly or weekly

            // Base query for closed cases
            $query = CaseModel::where('status', 'closed')
                ->whereNotNull('case_closed_at');

            // Apply company/branch filtering based on user role
            if ($user->role === 'branch_admin') {
                // Branch admins see only cases from their specific branch
                $query->where('company_id', $companyId)
                    ->where('branch_id', $branchId);
            } elseif ($user->role === 'company_admin') {
                // Company admins see cases from all branches under their company
                $query->where('company_id', $companyId);
            } elseif ($user->role === 'investigator') {
                $query->whereHas('assignments', function ($q) use ($user) {
                    $q->where('investigator_id', $user->id);
                });
            } elseif ($user->role === 'super_admin' || $user->role === 'system_admin' || $user->role === 'admin') {
                // System/Super/Admin can see all cases, no additional filter needed
            } else {
                // For other roles, filter by company if available
                if ($companyId) {
                    $query->where('company_id', $companyId);
                }
            }

            // Get date range (default to last 12 months or 12 weeks)
            $endDate = Carbon::now();
            $startDate = $period === 'monthly'
                ? $endDate->copy()->subMonths(12)
                : $endDate->copy()->subWeeks(12);

            $query->whereDate('case_closed_at', '>=', $startDate)
                ->whereDate('case_closed_at', '<=', $endDate);

            // Group by period and calculate average resolution time
            if ($period === 'monthly') {
                $trends = $query->selectRaw('
                        EXTRACT(YEAR FROM case_closed_at) as year,
                        EXTRACT(MONTH FROM case_closed_at) as month,
                        COUNT(*) as cases_count,
                        AVG(EXTRACT(EPOCH FROM (case_closed_at - created_at)) / 86400) as avg_days
                    ')
                    ->groupBy('year', 'month')
                    ->orderBy('year')
                    ->orderBy('month')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'period' => Carbon::create($item->year, $item->month, 1)->format('M Y'),
                            'cases_count' => $item->cases_count,
                            'avg_resolution_days' => round($item->avg_days, 2)
                        ];
                    });
            } else {
                $trends = $query->selectRaw('
                        EXTRACT(YEAR FROM case_closed_at) as year,
                        EXTRACT(WEEK FROM case_closed_at) as week,
                        COUNT(*) as cases_count,
                        AVG(EXTRACT(EPOCH FROM (case_closed_at - created_at)) / 86400) as avg_days
                    ')
                    ->groupBy('year', 'week')
                    ->orderBy('year')
                    ->orderBy('week')
                    ->get()
                    ->map(function ($item) {
                        $date = Carbon::now()->setISODate($item->year, $item->week);
                        return [
                            'period' => 'Week ' . $item->week . ', ' . $item->year,
                            'week_start' => $date->startOfWeek()->format('M d'),
                            'week_end' => $date->endOfWeek()->format('M d, Y'),
                            'cases_count' => $item->cases_count,
                            'avg_resolution_days' => round($item->avg_days, 2)
                        ];
                    });
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => $period,
                    'trends' => $trends->values()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get resolution trends',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export resolution data to CSV
     */
    public function exportResolutionData(Request $request)
    {
        try {
            $user = $request->user();
            $companyId = $user->company_id;
            $branchId = $user->branch_id;

            // Base query for closed cases
            $query = CaseModel::where('status', 'closed')
                ->whereNotNull('case_closed_at');

            // Apply company/branch filtering based on user role
            if ($user->role === 'branch_admin') {
                // Branch admins see only cases from their specific branch
                $query->where('company_id', $companyId)
                    ->where('branch_id', $branchId);
            } elseif ($user->role === 'company_admin') {
                // Company admins see cases from all branches under their company
                $query->where('company_id', $companyId);
            } elseif ($user->role === 'investigator') {
                $query->whereHas('assignments', function ($q) use ($user) {
                    $q->where('investigator_id', $user->id);
                });
            } elseif ($user->role === 'super_admin' || $user->role === 'system_admin' || $user->role === 'admin') {
                // System/Super/Admin can see all cases, no additional filter needed
            } else {
                // For other roles, filter by company if available
                if ($companyId) {
                    $query->where('company_id', $companyId);
                }
            }

            // Apply date filters if provided
            if ($request->has('start_date')) {
                $query->whereDate('case_closed_at', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->whereDate('case_closed_at', '<=', $request->end_date);
            }

            // Get the cases
            $cases = $query->with(['company:id,name', 'branch:id,name', 'closedBy:id,name,email'])->get();

            // Prepare CSV data
            $csvData = [];
            $csvData[] = ['Case ID', 'Submitted On', 'Closed On', 'Duration', 'Close Classification', 'Close Remarks', 'Closed By', 'Priority', 'Type', 'Company', 'Branch'];

            foreach ($cases as $case) {
                $createdAt = Carbon::parse($case->created_at);
                $closedAt = Carbon::parse($case->case_closed_at);
                $durationInDays = $closedAt->diffInDays($createdAt);

                $durationText = '';
                if ($durationInDays > 0) {
                    $durationText = $durationInDays . ' day' . ($durationInDays > 1 ? 's' : '');
                } else {
                    $durationInMinutes = $closedAt->diffInMinutes($createdAt);
                    $durationInHours = $closedAt->diffInHours($createdAt);
                    if ($durationInHours > 0) {
                        $durationText = $durationInHours . ' hour' . ($durationInHours > 1 ? 's' : '');
                    } else {
                        $durationText = $durationInMinutes . ' minute' . ($durationInMinutes > 1 ? 's' : '');
                    }
                }

                $csvData[] = [
                    $case->case_token,
                    $createdAt->format('d/m/Y'),
                    $closedAt->format('d/m/Y'),
                    $durationText,
                    $case->case_close_classification ?? '',
                    $case->resolution_note ?? '',
                    $case->closedBy?->name ?? '',
                    $case->priority ?? '',
                    $case->type ?? '',
                    $case->company?->name ?? '',
                    $case->branch?->name ?? ''
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
                'Content-Disposition' => 'attachment; filename="case_resolution_time_' . date('Y-m-d_H-i-s') . '.csv"'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export resolution data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
