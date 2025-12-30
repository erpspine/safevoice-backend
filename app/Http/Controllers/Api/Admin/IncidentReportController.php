<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CaseModel;
use App\Models\CaseCategory;
use App\Models\Company;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class IncidentReportController extends Controller
{
    /**
     * Get comprehensive incident reports for admin.
     */
    public function index(Request $request): JsonResponse
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
                    'message' => 'Access denied. Only admins and super admins can view incident reports.'
                ], 403);
            }

            // Get filters from request
            $companyId = $request->get('company_id');
            $branchId = $request->get('branch_id');
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => $this->getIncidentSummary($companyId, $branchId, $startDate, $endDate),
                    'incidentsPerCategory' => $this->getIncidentsPerCategory($companyId, $branchId, $startDate, $endDate),
                    'incidentsPerStatus' => $this->getIncidentsPerStatus($companyId, $branchId, $startDate, $endDate),
                    'incidentsPerPriority' => $this->getIncidentsPerPriority($companyId, $branchId, $startDate, $endDate),
                    'monthlyIncidents' => $this->getMonthlyIncidents($companyId, $branchId, $startDate, $endDate),
                    'topCompanies' => $this->getTopCompanies($startDate, $endDate),
                    'topCategories' => $this->getTopCategories($companyId, $branchId, $startDate, $endDate),
                    'incidentList' => $this->getIncidentList($request, $companyId, $branchId, $startDate, $endDate)
                ],
                'message' => 'Incident reports retrieved successfully',
                'timestamp' => now()->toIso8601String()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve incident reports',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get incident summary statistics
     */
    private function getIncidentSummary($companyId = null, $branchId = null, $startDate = null, $endDate = null): array
    {
        $query = CaseModel::where('type', 'incident');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $totalIncidents = (clone $query)->count();
        $openIncidents = (clone $query)->where('status', 'open')->count();
        $inProgressIncidents = (clone $query)->where('status', 'in_progress')->count();
        $resolvedIncidents = (clone $query)->where('status', 'resolved')->count();
        $closedIncidents = (clone $query)->where('status', 'closed')->count();

        // Calculate average resolution time
        $avgResolutionTime = (clone $query)->whereNotNull('case_closed_at')
            ->select(DB::raw('AVG(EXTRACT(EPOCH FROM (case_closed_at - created_at))/86400) as avg_days'))
            ->value('avg_days');

        $avgDays = $avgResolutionTime ? round($avgResolutionTime, 1) : 0;

        // Calculate resolution rate
        $resolvedTotal = $resolvedIncidents + $closedIncidents;
        $resolutionRate = $totalIncidents > 0 ? round(($resolvedTotal / $totalIncidents) * 100, 1) : 0;

        return [
            'totalIncidents' => $totalIncidents,
            'openIncidents' => $openIncidents,
            'inProgressIncidents' => $inProgressIncidents,
            'resolvedIncidents' => $resolvedIncidents,
            'closedIncidents' => $closedIncidents,
            'avgResolutionTime' => $avgDays . ' days',
            'avgResolutionTimeDays' => $avgDays,
            'resolutionRate' => $resolutionRate . '%'
        ];
    }

    /**
     * Get incidents per category
     */
    private function getIncidentsPerCategory($companyId = null, $branchId = null, $startDate = null, $endDate = null): array
    {
        $query = DB::table('case_categories')
            ->join('cases', 'case_categories.case_id', '=', 'cases.id')
            ->join('incident_categories', 'case_categories.category_id', '=', 'incident_categories.id')
            ->where('case_categories.category_type', 'incident')
            ->where('cases.type', 'incident')
            ->whereNull('case_categories.deleted_at');

        if ($companyId) {
            $query->where('cases.company_id', $companyId);
        }

        if ($branchId) {
            $query->where('cases.branch_id', $branchId);
        }

        if ($startDate) {
            $query->whereDate('cases.created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('cases.created_at', '<=', $endDate);
        }

        $categories = $query->select('incident_categories.name', DB::raw('count(*) as count'))
            ->groupBy('incident_categories.name')
            ->orderBy('count', 'desc')
            ->get();

        return [
            'labels' => $categories->pluck('name')->toArray(),
            'data' => $categories->pluck('count')->map(fn($val) => (int)$val)->toArray()
        ];
    }

    /**
     * Get incidents per status
     */
    private function getIncidentsPerStatus($companyId = null, $branchId = null, $startDate = null, $endDate = null): array
    {
        $query = CaseModel::where('type', 'incident');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        return [
            'labels' => ['Open', 'In Progress', 'Pending', 'Resolved', 'Closed'],
            'data' => [
                (clone $query)->where('status', 'open')->count(),
                (clone $query)->where('status', 'in_progress')->count(),
                (clone $query)->where('status', 'pending')->count(),
                (clone $query)->where('status', 'resolved')->count(),
                (clone $query)->where('status', 'closed')->count()
            ]
        ];
    }

    /**
     * Get incidents per priority
     */
    private function getIncidentsPerPriority($companyId = null, $branchId = null, $startDate = null, $endDate = null): array
    {
        $query = CaseModel::where('type', 'incident');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        return [
            'labels' => ['Low', 'Medium', 'High', 'Critical'],
            'data' => [
                (clone $query)->where('priority', 4)->count(),
                (clone $query)->where('priority', 2)->count(),
                (clone $query)->where('priority', 3)->count(),
                (clone $query)->where('priority', 1)->count()
            ]
        ];
    }

    /**
     * Get monthly incidents trend
     */
    private function getMonthlyIncidents($companyId = null, $branchId = null, $startDate = null, $endDate = null): array
    {
        $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $data = [];

        for ($month = 1; $month <= 12; $month++) {
            $query = CaseModel::where('type', 'incident')
                ->whereMonth('created_at', $month)
                ->whereYear('created_at', now()->year);

            if ($companyId) {
                $query->where('company_id', $companyId);
            }

            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            if ($startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            }

            if ($endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            }

            $data[] = $query->count();
        }

        return [
            'labels' => $labels,
            'data' => $data
        ];
    }

    /**
     * Get top companies by incidents
     */
    private function getTopCompanies($startDate = null, $endDate = null): array
    {
        $query = DB::table('cases')
            ->join('companies', 'cases.company_id', '=', 'companies.id')
            ->where('cases.type', 'incident')
            ->whereNull('cases.deleted_at');

        if ($startDate) {
            $query->whereDate('cases.created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('cases.created_at', '<=', $endDate);
        }

        $companies = $query->select('companies.id', 'companies.name', DB::raw('count(*) as incident_count'))
            ->groupBy('companies.id', 'companies.name')
            ->orderBy('incident_count', 'desc')
            ->limit(10)
            ->get();

        return $companies->map(function ($company) {
            return [
                'id' => $company->id,
                'name' => $company->name,
                'incidentCount' => $company->incident_count
            ];
        })->toArray();
    }

    /**
     * Get top incident categories
     */
    private function getTopCategories($companyId = null, $branchId = null, $startDate = null, $endDate = null): array
    {
        $query = DB::table('case_categories')
            ->join('cases', 'case_categories.case_id', '=', 'cases.id')
            ->join('incident_categories', 'case_categories.category_id', '=', 'incident_categories.id')
            ->where('case_categories.category_type', 'incident')
            ->where('cases.type', 'incident')
            ->whereNull('case_categories.deleted_at');

        if ($companyId) {
            $query->where('cases.company_id', $companyId);
        }

        if ($branchId) {
            $query->where('cases.branch_id', $branchId);
        }

        if ($startDate) {
            $query->whereDate('cases.created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('cases.created_at', '<=', $endDate);
        }

        $categories = $query->select('incident_categories.id', 'incident_categories.name', DB::raw('count(*) as count'))
            ->groupBy('incident_categories.id', 'incident_categories.name')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        return $categories->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'count' => $category->count
            ];
        })->toArray();
    }

    /**
     * Get paginated incident list
     */
    private function getIncidentList(Request $request, $companyId = null, $branchId = null, $startDate = null, $endDate = null): array
    {
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');

        $query = CaseModel::where('type', 'incident')
            ->with(['company:id,name', 'branch:id,name', 'assignee:id,name,email']);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('case_token', 'ILIKE', "%{$search}%")
                    ->orWhere('title', 'ILIKE', "%{$search}%")
                    ->orWhere('description', 'ILIKE', "%{$search}%");
            });
        }

        $total = $query->count();
        $incidents = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'total' => $total,
            'perPage' => $perPage,
            'currentPage' => $page,
            'lastPage' => $incidents->lastPage(),
            'data' => $incidents->map(function ($incident) {
                $priorityLabel = match ($incident->priority) {
                    1 => 'critical',
                    2 => 'high',
                    3 => 'medium',
                    4 => 'low',
                    default => 'medium'
                };

                return [
                    'id' => $incident->id,
                    'caseToken' => $incident->case_token,
                    'title' => $incident->title,
                    'description' => $incident->description,
                    'status' => $incident->status,
                    'priority' => $priorityLabel,
                    'company' => $incident->company ? [
                        'id' => $incident->company->id,
                        'name' => $incident->company->name
                    ] : null,
                    'branch' => $incident->branch ? [
                        'id' => $incident->branch->id,
                        'name' => $incident->branch->name
                    ] : null,
                    'assignee' => $incident->assignee ? [
                        'id' => $incident->assignee->id,
                        'name' => $incident->assignee->name,
                        'email' => $incident->assignee->email
                    ] : null,
                    'createdAt' => $incident->created_at->toIso8601String(),
                    'updatedAt' => $incident->updated_at->toIso8601String()
                ];
            })->toArray()
        ];
    }
}
