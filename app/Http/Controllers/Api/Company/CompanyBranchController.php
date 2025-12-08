<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CompanyBranchController extends Controller
{
    /**
     * Display a listing of branches for the authenticated company.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Check if user is authenticated via token
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            // Ensure user has company access and proper role
            if ($user->role !== 'company_admin' || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only company admins can manage branches.'
                ], 403);
            }

            $query = Branch::where('company_id', $user->company_id);

            // Apply filters if provided
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ILIKE', "%{$search}%")
                        ->orWhere('location', 'ILIKE', "%{$search}%")
                        ->orWhere('branch_code', 'ILIKE', "%{$search}%");
                });
            }

            // Get branches with optional relationships
            $branches = $query->with(['manager' => function ($query) {
                $query->select('id', 'name', 'email');
            }])->orderBy('name')->get();

            return response()->json([
                'success' => true,
                'data' => $branches
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve branches',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Store a newly created branch for the authenticated company.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if user is authenticated via token
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required. Please provide a valid authorization token.'
            ], 401);
        }

        // Ensure user has company access and proper role
        if ($user->role !== 'company_admin' || !$user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Only company admins can manage branches.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('branches')->where(function ($query) use ($user) {
                    return $query->where('company_id', $user->company_id);
                })
            ],
            'location' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'contact_person' => 'nullable|string|max:255',
            'branch_code' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'status' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $branchData = $request->only([
                'name',
                'branch_code',
                'location',
                'address',
                'contact_person',
                'contact_phone',
                'contact_email',
                'status'
            ]);
            $branchData['company_id'] = $user->company_id; // Force company ID from authenticated user

            $branch = Branch::create($branchData);

            // Load the manager relationship
            $branch->load(['manager' => function ($query) {
                $query->select('id', 'name', 'email');
            }]);

            return response()->json([
                'success' => true,
                'message' => 'Branch created successfully',
                'data' =>  $branch
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create branch',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Display the specified branch (only if it belongs to the authenticated company).
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            // Check if user is authenticated via token
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            // Ensure user has company access and proper role
            if ($user->role !== 'company_admin' || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only company admins can manage branches.'
                ], 403);
            }

            $branch = Branch::where('id', $id)
                ->where('company_id', $user->company_id)
                ->with([
                    'manager' => function ($query) {
                        $query->select('id', 'name', 'email', 'phone');
                    },
                    'users' => function ($query) {
                        $query->select('id', 'name', 'email', 'role', 'status')
                            ->where('status', 'active');
                    }
                ])
                ->first();

            if (!$branch) {
                return response()->json([
                    'success' => false,
                    'message' => 'Branch not found or access denied'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' =>  $branch

            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve branch',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update the specified branch (only if it belongs to the authenticated company).
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        // Check if user is authenticated via token
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required. Please provide a valid authorization token.'
            ], 401);
        }

        // Ensure user has company access and proper role
        if ($user->role !== 'company_admin' || !$user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Only company admins can manage branches.'
            ], 403);
        }

        $branch = Branch::where('id', $id)
            ->where('company_id', $user->company_id)
            ->first();

        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found or access denied'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('branches')->where(function ($query) use ($user) {
                    return $query->where('company_id', $user->company_id);
                })->ignore($id)
            ],
            'location' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'contact_person' => 'nullable|string|max:255',
            'branch_code' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'status' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $branchData = $request->only([
                'name',
                'branch_code',
                'location',
                'address',
                'contact_person',
                'contact_phone',
                'contact_email',
                'status'
            ]);
            // Don't allow changing company_id

            $branch->update($branchData);

            // Load the manager relationship
            $branch->load(['manager' => function ($query) {
                $query->select('id', 'name', 'email');
            }]);

            return response()->json([
                'success' => true,
                'message' => 'Branch updated successfully',
                'data' => [
                    'branch' => $branch
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update branch',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove the specified branch (only if it belongs to the authenticated company).
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            // Check if user is authenticated via token
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            // Ensure user has company access and proper role
            if ($user->role !== 'company_admin' || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only company admins can manage branches.'
                ], 403);
            }

            $branch = Branch::where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (!$branch) {
                return response()->json([
                    'success' => false,
                    'message' => 'Branch not found or access denied'
                ], 404);
            }

            // Check if branch has users
            $userCount = $branch->users()->count();
            if ($userCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete branch. It has {$userCount} user(s) assigned to it."
                ], 409);
            }

            $branchName = $branch->name;
            $branch->delete();

            return response()->json([
                'success' => true,
                'message' => "Branch '{$branchName}' deleted successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete branch',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get branch statistics for the authenticated company.
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Check if user is authenticated via token
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            // Ensure user has company access and proper role
            if ($user->role !== 'company_admin' || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only company admins can manage branches.'
                ], 403);
            }

            $companyId = $user->company_id;

            $stats = [
                'total_branches' => Branch::where('company_id', $companyId)->count(),
                'active_branches' => Branch::where('company_id', $companyId)->where('status', true)->count(),
                'inactive_branches' => Branch::where('company_id', $companyId)->where('status', false)->count(),
                'branches_with_managers' => Branch::where('company_id', $companyId)->whereNotNull('manager_id')->count(),
                'branches_without_managers' => Branch::where('company_id', $companyId)->whereNull('manager_id')->count(),
            ];

            // Get branch breakdown
            $branchBreakdown = Branch::where('company_id', $companyId)
                ->withCount('users')
                ->select('id', 'name', 'branch_code', 'location', 'status', 'manager_id')
                ->with(['manager' => function ($query) {
                    $query->select('id', 'name');
                }])
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'statistics' => $stats,
                    'branch_breakdown' => $branchBreakdown,
                    'company_id' => $companyId
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get available branch managers for the authenticated company.
     */
    public function availableManagers(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Check if user is authenticated via token
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            // Ensure user has company access and proper role
            if ($user->role !== 'company_admin' || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only company admins can manage branches.'
                ], 403);
            }

            // Get users with branch_admin role in the same company who are not already managing a branch
            $availableManagers = \App\Models\User::where('company_id', $user->company_id)
                ->where('role', 'branch_admin')
                ->where('status', 'active')
                ->whereDoesntHave('managedBranch') // Not already managing a branch
                ->select('id', 'name', 'email')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'available_managers' => $availableManagers,
                    'count' => $availableManagers->count()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve available managers',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
