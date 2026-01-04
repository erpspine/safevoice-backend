<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Investigator;
use App\Mail\InvestigatorAssignedToCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class InvestigatorCompanyAssignmentController extends Controller
{
    /**
     * Get investigators and their assigned companies.
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
                    'message' => 'Access denied. Only admins can access investigator assignments.'
                ], 403);
            }

            $query = Investigator::with(['user', 'companies' => function ($query) {
                $query->select(['companies.*'])
                    ->withPivot(['created_at'])
                    ->orderBy('companies.name', 'asc');
            }]);

            // Filter by investigator if provided
            if ($request->filled('investigator_id')) {
                $query->where('id', $request->investigator_id);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->boolean('status'));
            }

            $investigators = $query->get()->map(function ($investigator) {
                return [
                    'id' => $investigator->id,
                    'name' => $investigator->display_name,
                    'email' => $investigator->contact_email,
                    'is_external' => $investigator->is_external,
                    'status' => $investigator->status,
                    'companies' => $investigator->companies->map(function ($company) {
                        return [
                            'id' => $company->id,
                            'name' => $company->name,
                            'assigned_at' => $company->pivot->created_at,
                        ];
                    }),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $investigators,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve investigator company assignments',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all investigators list (simple list for dropdowns).
     */
    public function investigators(Request $request): JsonResponse
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
                    'message' => 'Access denied. Only admins can access investigators.'
                ], 403);
            }

            $query = Investigator::with('user')->withCount('companies');

            // Filter by status (default to active only)
            if ($request->has('status')) {
                $query->where('status', $request->boolean('status'));
            } else {
                $query->where('status', true);
            }

            // Search by name or email
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->whereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'ilike', "%{$search}%")
                            ->orWhere('email', 'ilike', "%{$search}%");
                    })
                        ->orWhere('external_name', 'ilike', "%{$search}%")
                        ->orWhere('external_email', 'ilike', "%{$search}%");
                });
            }

            $investigators = $query->get()->map(function ($investigator) {
                return [
                    'id' => $investigator->id,
                    'name' => $investigator->display_name,
                    'email' => $investigator->contact_email,
                    'is_external' => $investigator->is_external,
                    'status' => $investigator->status,
                    'availability_status' => $investigator->availability_status,
                    'companies_count' => $investigator->companies_count,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $investigators,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve investigators',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get companies assigned to a specific investigator.
     */
    public function investigatorCompanies(Request $request, string $investigator): JsonResponse
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
                    'message' => 'Access denied. Only admins can access investigator assignments.'
                ], 403);
            }

            $investigatorModel = Investigator::findOrFail($investigator);

            $companies = $investigatorModel->companies()
                ->select([
                    'companies.*',
                    'investigator_company.created_at as assigned_at'
                ])
                ->withCount(['cases as total_cases'])
                ->withCount(['cases as active_cases' => function ($query) use ($investigatorModel) {
                    $query->whereHas('assignments', function ($q) use ($investigatorModel) {
                        $q->where('investigator_id', $investigatorModel->id)
                            ->where('status', 'active');
                    });
                }])
                ->orderBy('companies.name')
                ->get()
                ->map(function ($company) {
                    return [
                        'id' => $company->id,
                        'name' => $company->name,
                        'plan' => $company->plan,
                        'status' => $company->status,
                        'assigned_at' => $company->assigned_at,
                        'total_cases' => $company->total_cases,
                        'active_cases' => $company->active_cases,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'investigator' => [
                        'id' => $investigatorModel->id,
                        'name' => $investigatorModel->display_name,
                        'email' => $investigatorModel->contact_email,
                        'is_external' => $investigatorModel->is_external,
                    ],
                    'companies' => $companies,
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Investigator not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve investigator companies',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get investigators assigned to a specific company (from investigator perspective).
     */
    public function companyInvestigators(Request $request, Company $company): JsonResponse
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
                    'message' => 'Access denied. Only admins can access investigator assignments.'
                ], 403);
            }

            $investigators = $company->assignedInvestigators()
                ->select([
                    'investigators.*',
                    'investigator_company.created_at as assigned_at'
                ])
                ->withCount('activeAssignments')
                ->orderBy('investigators.display_name', 'asc')
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
     * Assign companies to an investigator.
     */
    public function assign(Request $request, string $investigator): JsonResponse
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
                    'message' => 'Access denied. Only admins can assign investigators.'
                ], 403);
            }

            $investigatorModel = Investigator::findOrFail($investigator);

            $request->validate([
                'company_ids' => 'required|array',
                'company_ids.*' => 'exists:companies,id',
            ]);

            // Verify investigator is active
            if (!$investigatorModel->status) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot assign companies to an inactive investigator',
                ], 422);
            }

            $companies = Company::whereIn('id', $request->company_ids)
                ->where('status', true)
                ->get();

            if ($companies->count() !== count($request->company_ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'One or more companies are inactive or not found',
                ], 422);
            }

            // Add any new assignments
            $investigatorModel->companies()->syncWithoutDetaching($request->company_ids);

            // Send email notification to the investigator about new assignments
            if ($investigatorModel->user && $investigatorModel->user->email) {
                try {
                    Mail::to($investigatorModel->user->email)
                        ->send(new InvestigatorAssignedToCompany($investigatorModel, $companies->all()));
                } catch (\Exception $mailException) {
                    // Log the email error but don't fail the assignment
                    \Log::warning('Failed to send investigator assignment email', [
                        'investigator_id' => $investigatorModel->id,
                        'error' => $mailException->getMessage()
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Companies assigned successfully',
                'data' => $companies->map(function ($company) {
                    return [
                        'id' => $company->id,
                        'name' => $company->name,
                    ];
                }),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Investigator not found',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign companies',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove companies from an investigator.
     */
    public function unassign(Request $request, string $investigator): JsonResponse
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
                    'message' => 'Access denied. Only admins can unassign investigators.'
                ], 403);
            }

            $investigatorModel = Investigator::findOrFail($investigator);

            $request->validate([
                'company_ids' => 'required|array',
                'company_ids.*' => 'exists:companies,id',
            ]);

            // Check for active cases first
            $activeAssignments = $investigatorModel->assignments()
                ->whereHas('case', function ($query) use ($request) {
                    $query->whereIn('company_id', $request->company_ids);
                })
                ->where('status', 'active')
                ->count();

            if ($activeAssignments > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot unassign companies with active cases',
                ], 422);
            }

            // Remove the assignments
            $investigatorModel->companies()->detach($request->company_ids);

            return response()->json([
                'success' => true,
                'message' => 'Companies unassigned successfully',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Investigator not found',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to unassign companies',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available companies for assignment to an investigator.
     */
    public function availableCompanies(Request $request, string $investigator): JsonResponse
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
                    'message' => 'Access denied. Only admins can view available companies.'
                ], 403);
            }

            // Find investigator by ID
            $investigatorModel = Investigator::findOrFail($investigator);

            // Get IDs of already assigned companies
            $assignedIds = $investigatorModel->companies()->pluck('companies.id');

            // Get available companies not yet assigned to this investigator
            $companies = Company::whereNotIn('id', $assignedIds)
                ->where('status', true)
                ->withCount('cases')
                ->orderBy('name', 'asc')
                ->get()
                ->map(function ($company) {
                    return [
                        'id' => $company->id,
                        'name' => $company->name,
                        'plan' => $company->plan,
                        'total_cases' => $company->cases_count,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $companies,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Investigator not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve available companies',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get assignment statistics.
     */
    public function stats(Request $request): JsonResponse
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
                    'message' => 'Access denied. Only admins can view assignment statistics.'
                ], 403);
            }

            $stats = [
                'total_assignments' => \DB::table('investigator_company')->count(),
                'investigators_with_companies' => Investigator::has('companies')->count(),
                'companies_with_investigators' => Company::has('assignedInvestigators')->count(),
                'average_companies_per_investigator' => round(
                    Investigator::has('companies')
                        ->withCount('companies')
                        ->get()
                        ->avg('companies_count') ?? 0,
                    2
                ),
                'top_investigators_by_companies' => Investigator::withCount('companies')
                    ->orderBy('companies_count', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(function ($investigator) {
                        return [
                            'id' => $investigator->id,
                            'name' => $investigator->display_name,
                            'companies_count' => $investigator->companies_count,
                        ];
                    }),
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
