<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    /**
     * Display a listing of branches with pagination and filters.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Branch::with(['company:id,name']);



            // Apply sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');

            if (in_array($sortBy, ['name', 'location', 'status', 'created_at'])) {
                $query->orderBy($sortBy, $sortDirection);
            }

            // Get all branches without pagination
            $branches = $query->get();

            return response()->json([
                'success' => true,
                'data' => $branches,


            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve branches',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created branch.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'company_id' => 'required|string|exists:companies,id',
            'name' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'status' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Verify company exists and is active
            $company = Company::where('id', $request->company_id)
                ->where('status', true)
                ->firstOrFail();

            $branch = Branch::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Branch created successfully',
                'data' => $branch->load(['company:id,name'])
            ], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create branch',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified branch with its relationships.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $branch = Branch::with([
                'company:id,name,email,plan',
                'users' => function ($query) {
                    $query->select('id', 'name', 'email', 'role', 'status', 'branch_id')
                        ->orderBy('name');
                }
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $branch,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve branch',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified branch.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $branch = Branch::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'company_id' => 'sometimes|required|string|exists:companies,id',
                'name' => 'sometimes|required|string|max:255',
                'location' => 'sometimes|required|string|max:255',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'address' => 'nullable|string',
                'status' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verify company exists and is active (if company_id is being updated)
            if ($request->has('company_id')) {
                Company::where('id', $request->company_id)
                    ->where('status', true)
                    ->firstOrFail();
            }

            $branch->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Branch updated successfully',
                'data' => [
                    'branch' => $branch->fresh()->load(['company:id,name'])
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found or invalid company'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update branch',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified branch (soft delete).
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $branch = Branch::findOrFail($id);

            // Check if branch has active users
            $activeUsers = $branch->users()->where('status', 'active')->count();

            if ($activeUsers > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete branch with active users',
                    'details' => [
                        'active_users' => $activeUsers
                    ]
                ], 422);
            }

            $branch->delete();

            return response()->json([
                'success' => true,
                'message' => 'Branch deleted successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete branch',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get branches by company.
     */
    public function byCompany(string $companyId): JsonResponse
    {
        try {
            $company = Company::findOrFail($companyId);

            $branches = Branch::where('company_id', $companyId)
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $branches,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve branches',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Public API to get branches for a specific company (no authentication required).
     * Returns only active branches with minimal information for frontend use.
     */
    public function publicByCompany(string $companyId): JsonResponse
    {

        try {
            // Verify company exists and is active
            $company = Company::where('id', $companyId)
                ->where('status', true)
                ->firstOrFail();

            // Get active branches for the company
            $branches = Branch::where('company_id', $companyId)
                ->where('status', true) // Only active branches
                ->select([
                    'id',
                    'name',
                    'location',
                    'contact_phone',
                    'contact_email'
                ])
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Branches retrieved successfully.',
                'data' => $branches,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found or inactive.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve branches.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
