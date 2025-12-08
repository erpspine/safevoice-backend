<?php

namespace App\Http\Controllers\Api\Branch;

use App\Http\Controllers\Controller;
use App\Models\FeedbackCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BranchFeedbackCategoryController extends Controller
{
    /**
     * Display a listing of feedback categories for the authenticated branch.
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

            // Ensure user has branch access and proper role
            if ($user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only branch admins can manage feedback categories.'
                ], 403);
            }

            // Get feedback categories for the branch's company
            $query = FeedbackCategory::where('company_id', $user->company_id);

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

            // Get feedback categories with pagination
            $categories = $query->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve feedback categories',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Store a newly created feedback category for the authenticated branch's company.
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

        // Ensure user has branch access and proper role
        if ($user->role !== 'branch_admin' || !$user->branch_id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Only branch admins can manage feedback categories.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('feedback_categories')->where(function ($query) use ($user) {
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
            $categoryData = $request->only([
                'name',
                'description',
                'status'
            ]);
            $categoryData['company_id'] = $user->company_id; // Force company ID from authenticated user

            $category = FeedbackCategory::create($categoryData);

            return response()->json([
                'success' => true,
                'message' => 'Feedback category created successfully',
                'data' => $category
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create feedback category',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Display the specified feedback category (only if it belongs to the authenticated branch's company).
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

            // Ensure user has branch access and proper role
            if ($user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only branch admins can manage feedback categories.'
                ], 403);
            }

            $category = FeedbackCategory::where('id', $id)
                ->where('company_id', $user->company_id)
                ->with(['company:id,name'])
                ->first();

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Feedback category not found or access denied'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $category
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve feedback category',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update the specified feedback category (only if it belongs to the authenticated branch's company).
     */
    public function update(Request $request, string $id): JsonResponse
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

            // Ensure user has branch access and proper role
            if ($user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only branch admins can manage feedback categories.'
                ], 403);
            }

            $category = FeedbackCategory::where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Feedback category not found or access denied'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('feedback_categories')->where(function ($query) use ($user) {
                        return $query->where('company_id', $user->company_id);
                    })->ignore($id)
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
                $categoryData = $request->only([
                    'name',
                    'description',
                    'status'
                ]);
                // Don't allow changing company_id

                $category->update($categoryData);

                return response()->json([
                    'success' => true,
                    'message' => 'Feedback category updated successfully',
                    'data' => $category
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update feedback category',
                    'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update feedback category',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove the specified feedback category (only if it belongs to the authenticated branch's company).
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

            // Ensure user has branch access and proper role
            if ($user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only branch admins can manage feedback categories.'
                ], 403);
            }

            $category = FeedbackCategory::where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Feedback category not found or access denied'
                ], 404);
            }

            // Check if category is used in any active cases
            $activeCases = $category->assignedCases()->where('status', '!=', 'closed')->count();
            if ($activeCases > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete feedback category with active cases',
                    'details' => $activeCases
                ], 422);
            }

            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Feedback category deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete feedback category',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get feedback category statistics for the authenticated branch's company.
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

            // Ensure user has branch access and proper role
            if ($user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only branch admins can manage feedback categories.'
                ], 403);
            }

            $companyId = $user->company_id;

            $stats = [
                'total_categories' => FeedbackCategory::where('company_id', $companyId)->count(),
                'active_categories' => FeedbackCategory::where('company_id', $companyId)->where('status', true)->count(),
                'inactive_categories' => FeedbackCategory::where('company_id', $companyId)->where('status', false)->count(),
            ];

            // Get category breakdown
            $categoryBreakdown = FeedbackCategory::where('company_id', $companyId)
                ->select('id', 'name', 'status')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'statistics' => $stats,
                    'category_breakdown' => $categoryBreakdown,
                    'company_id' => $companyId,
                    'branch_id' => $user->branch_id
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
