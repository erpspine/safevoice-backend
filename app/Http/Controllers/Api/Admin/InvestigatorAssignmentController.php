<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Investigator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InvestigatorAssignmentController extends Controller
{
    /**
     * Get companies and their assigned investigators.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Company::with(['assignedInvestigators' => function ($query) {
                $query->select(['investigators.*'])
                    ->withPivot(['created_at'])
                    ->orderBy('investigators.created_at', 'desc');
            }]);

            // Filter by company if provided
            if ($request->filled('company_id')) {
                $query->where('id', $request->company_id);
            }

            $companies = $query->get()->map(function ($company) {
                return [
                    'id' => $company->id,
                    'name' => $company->name,
                    'investigators' => $company->assignedInvestigators->map(function ($investigator) {
                        return [
                            'id' => $investigator->id,
                            'name' => $investigator->display_name,
                            'email' => $investigator->contact_email,
                            'is_external' => $investigator->is_external,
                            'assigned_at' => $investigator->pivot->created_at,
                            'workload' => $investigator->current_workload,
                        ];
                    }),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $companies,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve investigator assignments',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get investigators assigned to a specific company.
     */
    public function companyInvestigators(Company $company): JsonResponse
    {
        try {
            $investigators = $company->assignedInvestigators()
                ->select([
                    'investigators.*',
                    'investigator_company.created_at as assigned_at'
                ])
                ->withCount('activeAssignments')
                ->orderBy('investigators.created_at', 'desc')
                ->get()
                ->map(function ($investigator) {
                    return [
                        'id' => $investigator->id,
                        'name' => $investigator->display_name,
                        'email' => $investigator->contact_email,
                        'is_external' => $investigator->is_external,
                        'specializations' => $investigator->specializations,
                        'assigned_at' => $investigator->assigned_at,
                        'active_cases' => $investigator->active_assignments_count,
                        'is_available' => $investigator->isAvailable(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'company' => [
                        'id' => $company->id,
                        'name' => $company->name,
                    ],
                    'investigators' => $investigators,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve company investigators',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get companies an investigator is assigned to.
     */
    public function investigatorCompanies(Investigator $investigator): JsonResponse
    {
        try {
            $companies = $investigator->companies()
                ->select(['companies.*', 'investigator_company.created_at as assigned_at'])
                ->withCount(['cases as active_cases' => function ($query) use ($investigator) {
                    $query->whereHas('assignments', function ($q) use ($investigator) {
                        $q->where('investigator_id', $investigator->id)
                            ->where('status', 'active');
                    });
                }])
                ->orderBy('companies.name')
                ->get()
                ->map(function ($company) {
                    return [
                        'id' => $company->id,
                        'name' => $company->name,
                        'assigned_at' => $company->assigned_at,
                        'active_cases' => $company->active_cases,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'investigator' => [
                        'id' => $investigator->id,
                        'name' => $investigator->display_name,
                        'is_external' => $investigator->is_external,
                    ],
                    'companies' => $companies,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve investigator companies',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign investigators to a company.
     */
    public function assign(Request $request, Company $company): JsonResponse
    {
        try {
            $request->validate([
                'investigator_ids' => 'required|array',
                'investigator_ids.*' => 'exists:investigators,id',
            ]);

            $investigators = Investigator::whereIn('id', $request->investigator_ids)->get();

            // Add any new assignments
            $company->assignedInvestigators()->syncWithoutDetaching($request->investigator_ids);

            return response()->json([
                'success' => true,
                'message' => 'Investigators assigned successfully',
                'data' => $investigators->map(function ($investigator) {
                    return [
                        'id' => $investigator->id,
                        'name' => $investigator->display_name,
                    ];
                }),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign investigators',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove investigators from a company.
     */
    public function unassign(Request $request, Company $company): JsonResponse
    {
        try {
            $request->validate([
                'investigator_ids' => 'required|array',
                'investigator_ids.*' => 'exists:investigators,id',
            ]);

            // Check for active cases first
            $activeAssignments = $company->cases()
                ->whereHas('assignments', function ($query) use ($request) {
                    $query->whereIn('investigator_id', $request->investigator_ids)
                        ->where('status', 'active');
                })
                ->count();

            if ($activeAssignments > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot unassign investigators with active cases',
                ], 422);
            }

            // Remove the assignments
            $company->assignedInvestigators()->detach($request->investigator_ids);

            return response()->json([
                'success' => true,
                'message' => 'Investigators unassigned successfully',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to unassign investigators',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available investigators for assignment to a company.
     */
    public function availableInvestigators(Company $company): JsonResponse
    {
        try {
            // Get IDs of already assigned investigators
            $assignedIds = $company->assignedInvestigators()->pluck('investigators.id');

            // Get available investigators not yet assigned to this company
            $investigators = Investigator::whereNotIn('id', $assignedIds)
                ->where('status', true)
                ->withCount('activeAssignments')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($investigator) {
                    return [
                        'id' => $investigator->id,
                        'name' => $investigator->display_name,
                        'email' => $investigator->contact_email,
                        'is_external' => $investigator->is_external,
                        'specializations' => $investigator->specializations,
                        'active_cases' => $investigator->active_assignments_count,
                        'is_available' => $investigator->isAvailable(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $investigators,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve available investigators',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get assignment statistics.
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = [
                'total_assignments' => \DB::table('investigator_company')->count(),
                'investigators_with_assignments' => Investigator::has('companies')->count(),
                'companies_with_investigators' => Company::has('assignedInvestigators')->count(),
                'average_assignments_per_investigator' => round(
                    \DB::table('investigator_company')
                        ->select('investigator_id')
                        ->groupBy('investigator_id')
                        ->get()
                        ->avg(function ($row) {
                            return 1;
                        }),
                    2
                ),
                'average_investigators_per_company' => round(
                    \DB::table('investigator_company')
                        ->select('company_id')
                        ->groupBy('company_id')
                        ->get()
                        ->avg(function ($row) {
                            return 1;
                        }),
                    2
                ),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve assignment statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
