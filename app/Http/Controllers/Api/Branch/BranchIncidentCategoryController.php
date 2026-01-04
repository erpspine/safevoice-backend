<?php

namespace App\Http\Controllers\Api\Branch;

use App\Http\Controllers\Controller;
use App\Models\IncidentCategory;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BranchIncidentCategoryController extends Controller
{
    /**
     * Display a listing of incident categories for the authenticated branch.
     * Supports hierarchical view with parent/child relationships.
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
                    'message' => 'Access denied. Only branch admins can manage incident categories.'
                ], 403);
            }

            // Get view type: 'flat' for all categories, 'hierarchical' for parent with children (default)
            $viewType = $request->query('view', 'hierarchical');

            // Get incident categories for the branch's company
            $query = IncidentCategory::where('company_id', $user->company_id);

            // Apply filters if provided
            if ($request->has('status')) {
                $query->where('status', (bool) $request->status);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ILIKE', "%{$search}%")
                        ->orWhere('description', 'ILIKE', "%{$search}%")
                        ->orWhere('category_key', 'ILIKE', "%{$search}%");
                });
            }

            // Filter by parent_id (get only children of specific parent)
            if ($request->filled('parent_id')) {
                $query->where('parent_id', $request->parent_id);
            }

            // Filter to get only root/parent categories
            if ($request->boolean('parents_only')) {
                $query->whereNull('parent_id');
            }

            if ($viewType === 'hierarchical' && !$request->filled('search') && !$request->filled('parent_id')) {
                // Get only parent categories with their children
                $categories = IncidentCategory::where('company_id', $user->company_id)
                    ->whereNull('parent_id')
                    ->when($request->has('status'), function ($q) use ($request) {
                        $q->where('status', (bool) $request->status);
                    })
                    ->with(['subcategories' => function ($q) use ($request) {
                        $q->orderBy('sort_order')->orderBy('name');
                        if ($request->has('status')) {
                            $q->where('status', (bool) $request->status);
                        }
                    }])
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get();

                // Calculate statistics
                $totalParent = $categories->count();
                $totalSubcategories = $categories->sum(fn($cat) => $cat->subcategories->count());
                $totalActive = IncidentCategory::where('company_id', $user->company_id)->where('status', true)->count();
                $totalInactive = IncidentCategory::where('company_id', $user->company_id)->where('status', false)->count();

                return response()->json([
                    'success' => true,
                    'data' => $categories,
                    'statistics' => [
                        'total_parent_categories' => $totalParent,
                        'total_subcategories' => $totalSubcategories,
                        'total_categories' => $totalParent + $totalSubcategories,
                        'active' => $totalActive,
                        'inactive' => $totalInactive
                    ]
                ]);
            }

            // Flat list (all categories)
            $categories = $query
                ->with('parent:id,name')
                ->withCount('subcategories')
                ->orderBy('sort_order')
                ->orderBy('name')
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
     * Store a newly created incident category for the authenticated branch's company.
     * Supports creating parent categories or subcategories.
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
                'message' => 'Access denied. Only branch admins can manage incident categories.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('incident_categories')->where(function ($query) use ($user, $request) {
                    $query->where('company_id', $user->company_id);
                    // Allow same name under different parents
                    if ($request->filled('parent_id')) {
                        $query->where('parent_id', $request->parent_id);
                    } else {
                        $query->whereNull('parent_id');
                    }
                    return $query;
                })
            ],
            'description' => 'nullable|string|max:1000',
            'status' => 'sometimes|boolean',
            'parent_id' => [
                'nullable',
                'string',
                Rule::exists('incident_categories', 'id')->where(function ($query) use ($user) {
                    return $query->where('company_id', $user->company_id)->whereNull('parent_id');
                })
            ],
            'category_key' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0'
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
                'parent_id',
                'category_key',
                'sort_order'
            ]);
            $categoryData['company_id'] = $user->company_id;
            $categoryData['status'] = $request->boolean('status', true);

            // Auto-generate category_key if not provided
            if (empty($categoryData['category_key'])) {
                $categoryData['category_key'] = strtolower(str_replace(' ', '_', $categoryData['name']));
            }

            // Auto-assign sort_order if not provided
            if (!isset($categoryData['sort_order'])) {
                $maxOrder = IncidentCategory::where('company_id', $user->company_id)
                    ->where('parent_id', $categoryData['parent_id'] ?? null)
                    ->max('sort_order') ?? 0;
                $categoryData['sort_order'] = $maxOrder + 1;
            }

            $category = IncidentCategory::create($categoryData);

            // Load parent relationship if it's a subcategory
            if ($category->parent_id) {
                $category->load('parent:id,name');
            }

            return response()->json([
                'success' => true,
                'message' => 'Incident category created successfully',
                'data' => $category
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
     * Display the specified incident category (only if it belongs to the authenticated branch's company).
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
                    'message' => 'Access denied. Only branch admins can manage incident categories.'
                ], 403);
            }

            $category = IncidentCategory::where('id', $id)
                ->where('company_id', $user->company_id)
                ->with(['company:id,name', 'parent:id,name', 'subcategories'])
                ->withCount('subcategories')
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
     * Update the specified incident category (only if it belongs to the authenticated branch's company).
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
                    'message' => 'Access denied. Only branch admins can manage incident categories.'
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
                    'sometimes',
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('incident_categories')->where(function ($query) use ($user, $category) {
                        $query->where('company_id', $user->company_id);
                        // Same name allowed under different parents
                        $query->where('parent_id', $category->parent_id);
                        return $query;
                    })->ignore($id)
                ],
                'description' => 'nullable|string|max:1000',
                'status' => 'sometimes|boolean',
                'parent_id' => [
                    'nullable',
                    'string',
                    Rule::exists('incident_categories', 'id')->where(function ($query) use ($user) {
                        return $query->where('company_id', $user->company_id)->whereNull('parent_id');
                    }),
                    // Prevent setting self as parent
                    function ($attribute, $value, $fail) use ($id) {
                        if ($value === $id) {
                            $fail('A category cannot be its own parent.');
                        }
                    }
                ],
                'category_key' => 'nullable|string|max:255',
                'sort_order' => 'nullable|integer|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            // If changing to a parent category, check if it has subcategories
            if ($request->filled('parent_id') && $category->subcategories()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot move a category with subcategories under another parent. Please move or delete subcategories first.'
                ], 422);
            }

            try {
                $categoryData = $request->only([
                    'name',
                    'description',
                    'status',
                    'parent_id',
                    'category_key',
                    'sort_order'
                ]);

                $category->update($categoryData);

                // Reload with relationships
                $category->load(['parent:id,name', 'subcategories']);

                return response()->json([
                    'success' => true,
                    'message' => 'Incident category updated successfully',
                    'data' => $category
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update incident category',
                    'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update incident category',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove the specified incident category (only if it belongs to the authenticated branch's company).
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
                    'message' => 'Access denied. Only branch admins can manage incident categories.'
                ], 403);
            }

            $category = IncidentCategory::where('id', $id)
                ->where('company_id', $user->company_id)
                ->withCount('subcategories')
                ->first();

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Incident category not found or access denied'
                ], 404);
            }

            // Check if category has subcategories
            $forceDelete = $request->boolean('force_delete', false);
            $reassignParent = $request->input('reassign_to_parent_id');

            if ($category->subcategories_count > 0 && !$forceDelete && !$reassignParent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete category with subcategories. Use force_delete=true to delete with all subcategories, or provide reassign_to_parent_id to move subcategories.',
                    'subcategories_count' => $category->subcategories_count
                ], 422);
            }

            // Check if category is used in any active cases
            $activeCases = $category->assignedCases()->where('status', '!=', 'closed')->count();
            if ($activeCases > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete incident category with active cases',
                    'active_cases_count' => $activeCases
                ], 422);
            }

            // If force delete, check subcategories for active cases too
            if ($forceDelete && $category->subcategories_count > 0) {
                $subcategoryIds = IncidentCategory::where('parent_id', $category->id)->pluck('id');
                $subcategoryActiveCases = \App\Models\CaseModel::whereIn('incident_category_id', $subcategoryIds)
                    ->where('status', '!=', 'closed')
                    ->count();

                if ($subcategoryActiveCases > 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot delete category because subcategories have active cases',
                        'subcategory_active_cases_count' => $subcategoryActiveCases
                    ], 422);
                }
            }

            // Handle subcategory reassignment
            if ($reassignParent && $category->subcategories_count > 0) {
                // Validate reassign parent exists and is not self or descendant
                if ($reassignParent === $category->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot reassign subcategories to the category being deleted'
                    ], 422);
                }

                $newParent = IncidentCategory::where('id', $reassignParent)
                    ->where('company_id', $user->company_id)
                    ->first();

                if (!$newParent) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Reassignment parent category not found'
                    ], 404);
                }

                // Move subcategories to new parent
                IncidentCategory::where('parent_id', $category->id)
                    ->update(['parent_id' => $reassignParent]);
            } elseif ($forceDelete && $category->subcategories_count > 0) {
                // Delete all subcategories
                IncidentCategory::where('parent_id', $category->id)->delete();
            }

            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Incident category deleted successfully',
                'details' => [
                    'subcategories_deleted' => $forceDelete ? $category->subcategories_count : 0,
                    'subcategories_reassigned' => $reassignParent ? $category->subcategories_count : 0
                ]
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
     * Get incident category statistics for the authenticated branch's company.
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
                    'message' => 'Access denied. Only branch admins can manage incident categories.'
                ], 403);
            }

            $companyId = $user->company_id;

            $stats = [
                'total_categories' => IncidentCategory::where('company_id', $companyId)->count(),
                'active_categories' => IncidentCategory::where('company_id', $companyId)->where('status', true)->count(),
                'inactive_categories' => IncidentCategory::where('company_id', $companyId)->where('status', false)->count(),
                'parent_categories' => IncidentCategory::where('company_id', $companyId)->whereNull('parent_id')->count(),
                'subcategories' => IncidentCategory::where('company_id', $companyId)->whereNotNull('parent_id')->count(),
            ];

            // Get category breakdown with hierarchy info
            $categoryBreakdown = IncidentCategory::where('company_id', $companyId)
                ->select('id', 'name', 'status', 'parent_id', 'category_key', 'sort_order')
                ->withCount('subcategories')
                ->orderBy('sort_order')
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
