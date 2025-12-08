<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\IncidentCategory;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CompanyIncidentCategoryController extends Controller
{
    /**
     * Display a listing of incident categories for the authenticated company.
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
                    'message' => 'Access denied. Only company admins can manage incident categories.'
                ], 403);
            }

            $query = IncidentCategory::where('company_id', $user->company_id);

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

            // Get incident categories
            $categories = $query->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve incident categories',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Store a newly created incident category for the authenticated company.
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
                'message' => 'Access denied. Only company admins can manage incident categories.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('incident_categories')->where(function ($query) use ($user) {
                    return $query->where('company_id', $user->company_id);
                })
            ],
            'description' => 'nullable|string|max:1000',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'auto_assign_to_department' => [
                'nullable',
                'ulid',
                Rule::exists('departments', 'id')->where(function ($query) use ($user) {
                    return $query->where('company_id', $user->company_id)
                        ->where('status', true);
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
            $categoryData = $request->only([
                'name',
                'description',
                'color',
                'auto_assign_to_department',
                'status'
            ]);
            $categoryData['company_id'] = $user->company_id; // Force company ID from authenticated user

            $category = IncidentCategory::create($categoryData);

            return response()->json([
                'success' => true,
                'message' => 'Incident category created successfully',
                'data' => [
                    'category' => $category
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create incident category',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Display the specified incident category (only if it belongs to the authenticated company).
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
                    'message' => 'Access denied. Only company admins can manage incident categories.'
                ], 403);
            }

            $category = IncidentCategory::where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Incident category not found or access denied'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $category
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve incident category',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update the specified incident category (only if it belongs to the authenticated company).
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
                'message' => 'Access denied. Only company admins can manage incident categories.'
            ], 403);
        }

        $category = IncidentCategory::where('id', $id)
            ->where('company_id', $user->company_id)
            ->first();

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Incident category not found or access denied'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('incident_categories')->where(function ($query) use ($user) {
                    return $query->where('company_id', $user->company_id);
                })->ignore($id)
            ],
            'description' => 'nullable|string|max:1000',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'auto_assign_to_department' => [
                'nullable',
                'ulid',
                Rule::exists('departments', 'id')->where(function ($query) use ($user) {
                    return $query->where('company_id', $user->company_id)
                        ->where('status', true);
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
            $categoryData = $request->only([
                'name',
                'description',
                'color',
                'auto_assign_to_department',
                'status'
            ]);
            // Don't allow changing company_id

            $category->update($categoryData);

            return response()->json([
                'success' => true,
                'message' => 'Incident category updated successfully',
                'data' => [
                    'category' => $category
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update incident category',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove the specified incident category (only if it belongs to the authenticated company).
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
                    'message' => 'Access denied. Only company admins can manage incident categories.'
                ], 403);
            }

            $category = IncidentCategory::where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Incident category not found or access denied'
                ], 404);
            }

            $categoryName = $category->name;
            $category->delete();

            return response()->json([
                'success' => true,
                'message' => "Incident category '{$categoryName}' deleted successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete incident category',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get available departments for auto-assignment in the authenticated company.
     */
    public function availableDepartments(Request $request): JsonResponse
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
                    'message' => 'Access denied. Only company admins can manage incident categories.'
                ], 403);
            }

            // Get active departments in the same company
            $departments = Department::where('company_id', $user->company_id)
                ->where('status', true)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'departments' => $departments,
                    'count' => $departments->count()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve available departments',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get incident category statistics for the authenticated company.
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
                    'message' => 'Access denied. Only company admins can manage incident categories.'
                ], 403);
            }

            $companyId = $user->company_id;

            $stats = [
                'total_categories' => IncidentCategory::where('company_id', $companyId)->count(),
                'active_categories' => IncidentCategory::where('company_id', $companyId)->where('status', true)->count(),
                'inactive_categories' => IncidentCategory::where('company_id', $companyId)->where('status', false)->count(),
                'categories_with_auto_assignment' => IncidentCategory::where('company_id', $companyId)
                    ->whereNotNull('auto_assign_to_department')->count(),
            ];

            // Get category breakdown
            $categoryBreakdown = IncidentCategory::where('company_id', $companyId)
                ->select('id', 'name', 'status', 'color', 'auto_assign_to_department')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'statistics' => $stats,
                    'category_breakdown' => $categoryBreakdown,
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
