<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    /**
     * Display a listing of departments with filters.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Department::with(['company:id,name']);

            // Apply sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');

            if (in_array($sortBy, ['name', 'status', 'created_at'])) {
                $query->orderBy($sortBy, $sortDirection);
            }

            // Get all departments without pagination
            $departments = $query->get();

            return response()->json([
                'success' => true,
                'data' => $departments
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve departments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created department.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'company_id' => 'required|string|exists:companies,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
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

            $department = Department::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Department created successfully',
                'data' => [
                    'department' => $department->load(['company:id,name'])
                ]
            ], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found or head user invalid'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create department',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified department with its relationships.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $department = Department::with([
                'company:id,name,logo'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $department,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve department',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified department.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $department = Department::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'company_id' => 'sometimes|required|string|exists:companies,id',
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
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

            $department->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Department updated successfully',
                'data' => [
                    'department' => $department->fresh()->load(['company:id,name'])
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found or invalid company/head'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update department',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified department (soft delete).
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $department = Department::findOrFail($id);

            $department->delete();

            return response()->json([
                'success' => true,
                'message' => 'Department deleted successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete department',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get departments by company.
     */
    public function byCompany(string $companyId): JsonResponse
    {
        try {
            $company = Company::findOrFail($companyId);

            $departments = Department::where('company_id', $companyId)
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'company' => $company->only(['id', 'name']),
                    'departments' => $departments
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve departments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get department statistics dashboard.
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total_departments' => Department::count(),
                'active_departments' => Department::where('status', true)->count(),
                'inactive_departments' => Department::where('status', false)->count(),
                'departments_by_company' => Department::with('company:id,name')
                    ->get()
                    ->groupBy('company.name')
                    ->map->count(),
                'recent_departments' => Department::with(['company:id,name'])
                    ->latest()
                    ->take(5)
                    ->get(['id', 'name', 'company_id', 'status', 'created_at']),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
