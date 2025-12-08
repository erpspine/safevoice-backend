<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CompanyDepartmentController extends Controller
{
    /**
     * Display a listing of departments for the authenticated company.
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
                    'message' => 'Access denied. Only company admins can manage departments.'
                ], 403);
            }

            $query = Department::where('company_id', $user->company_id);

            // Apply filters if provided
            if ($request->has('status')) {
                $query->where('status', (bool) $request->status);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ILIKE', "%{$search}%")
                        ->orWhere('description', 'ILIKE', "%{$search}%");
                });
            }

            // Get departments
            $departments = $query->orderBy('name')->get();

            return response()->json([
                'success' => true,
                'data' => $departments
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve departments',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Store a newly created department for the authenticated company.
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
                'message' => 'Access denied. Only company admins can manage departments.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments')->where(function ($query) use ($user) {
                    return $query->where('company_id', $user->company_id);
                })
            ],
            'description' => 'nullable|string|max:1000',

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
            $departmentData = $request->only(['name', 'description', 'head_id', 'status']);
            $departmentData['company_id'] = $user->company_id; // Force company ID from authenticated user

            $department = Department::create($departmentData);

            return response()->json([
                'success' => true,
                'message' => 'Department created successfully',
                'data' => $department
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create department',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Display the specified department (only if it belongs to the authenticated company).
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
                    'message' => 'Access denied. Only company admins can manage departments.'
                ], 403);
            }

            $department = Department::where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (!$department) {
                return response()->json([
                    'success' => false,
                    'message' => 'Department not found or access denied'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $department

            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve department',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update the specified department (only if it belongs to the authenticated company).
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
                'message' => 'Access denied. Only company admins can manage departments.'
            ], 403);
        }

        $department = Department::where('id', $id)
            ->where('company_id', $user->company_id)
            ->first();

        if (!$department) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found or access denied'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments')->where(function ($query) use ($user) {
                    return $query->where('company_id', $user->company_id);
                })->ignore($id)
            ],
            'description' => 'nullable|string|max:1000',
            'head_id' => [
                'nullable',
                'ulid',
                Rule::exists('users', 'id')->where(function ($query) use ($user) {
                    return $query->where('company_id', $user->company_id)
                        ->where('role', 'investigator')
                        ->where('status', 'active');
                })
            ],
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
            $departmentData = $request->only(['name', 'description', 'head_id', 'status']);
            // Don't allow changing company_id

            $department->update($departmentData);

            return response()->json([
                'success' => true,
                'message' => 'Department updated successfully',
                'data' => $department
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update department',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove the specified department (only if it belongs to the authenticated company).
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
                    'message' => 'Access denied. Only company admins can manage departments.'
                ], 403);
            }

            $department = Department::where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (!$department) {
                return response()->json([
                    'success' => false,
                    'message' => 'Department not found or access denied'
                ], 404);
            }

            $departmentName = $department->name;
            $department->delete();

            return response()->json([
                'success' => true,
                'message' => "Department '{$departmentName}' deleted successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete department',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get available department heads for the authenticated company.
     */
    public function availableHeads(Request $request): JsonResponse
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
                    'message' => 'Access denied. Only company admins can manage departments.'
                ], 403);
            }

            // Get users with investigator role in the same company
            $availableHeads = \App\Models\User::where('company_id', $user->company_id)
                ->where('role', 'investigator')
                ->where('status', 'active')
                ->whereNull('department_id') // Not already assigned to a department
                ->select('id', 'name', 'email')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'available_heads' => $availableHeads,
                    'count' => $availableHeads->count()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve available heads',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get department statistics for the authenticated company.
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
                    'message' => 'Access denied. Only company admins can manage departments.'
                ], 403);
            }

            $companyId = $user->company_id;

            $stats = [
                'total_departments' => Department::where('company_id', $companyId)->count(),
                'active_departments' => Department::where('company_id', $companyId)->where('status', true)->count(),
                'inactive_departments' => Department::where('company_id', $companyId)->where('status', false)->count(),
                'departments_with_heads' => Department::where('company_id', $companyId)->whereNotNull('head_id')->count(),
                'departments_without_heads' => Department::where('company_id', $companyId)->whereNull('head_id')->count(),
            ];

            // Get department breakdown
            $departmentBreakdown = Department::where('company_id', $companyId)
                ->select('id', 'name', 'status', 'head_id')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'statistics' => $stats,
                    'department_breakdown' => $departmentBreakdown,
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
}
