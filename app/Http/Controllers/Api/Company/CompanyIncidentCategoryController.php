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

            // Filter by parent_id if provided
            if ($request->has('parent_id')) {
                $parentId = $request->parent_id;
                if ($parentId === 'null' || $parentId === '') {
                    $query->whereNull('parent_id');
                } else {
                    $query->where('parent_id', $parentId);
                }
            }

            // Determine output format: hierarchical or flat
            $format = $request->input('format', 'hierarchical');
            $includeSubcategories = $request->boolean('include_subcategories', true);

            if ($format === 'hierarchical' && !$request->has('parent_id')) {
                // Get only parent categories (no parent_id) with their subcategories
                $query->whereNull('parent_id');
                $query->with(['subcategories' => function ($q) use ($request) {
                    $q->orderBy('sort_order')->orderBy('name');
                    if ($request->has('status')) {
                        $q->where('status', (bool) $request->status);
                    }
                }]);
            } elseif ($format === 'flat' && !$includeSubcategories) {
                // Get only parent categories in flat format
                $query->whereNull('parent_id');
            }

            // Get incident categories
            $categories = $query->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $categories,
                'meta' => [
                    'format' => $format,
                    'include_subcategories' => $includeSubcategories,
                    'total' => $categories->count()
                ]
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
            'parent_id' => [
                'nullable',
                'ulid',
                Rule::exists('incident_categories', 'id')->where(function ($query) use ($user) {
                    return $query->where('company_id', $user->company_id);
                })
            ],
            'category_key' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('incident_categories')->where(function ($query) use ($user) {
                    return $query->where('company_id', $user->company_id);
                })
            ],
            'sort_order' => 'nullable|integer|min:0|max:999',
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
                'parent_id',
                'category_key',
                'sort_order',
                'status'
            ]);
            $categoryData['company_id'] = $user->company_id; // Force company ID from authenticated user

            $category = IncidentCategory::create($categoryData);

            // Load relationships for response
            $category->load('parent');
            $category->loadCount('subcategories');

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
                ->with(['parent', 'subcategories' => function ($q) {
                    $q->orderBy('sort_order')->orderBy('name');
                }])
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
            'parent_id' => [
                'nullable',
                'ulid',
                Rule::exists('incident_categories', 'id')->where(function ($query) use ($user) {
                    return $query->where('company_id', $user->company_id);
                }),
                function ($attribute, $value, $fail) use ($category) {
                    // Prevent setting self as parent
                    if ($value === $category->id) {
                        $fail('A category cannot be its own parent.');
                    }
                    // Prevent circular reference (cannot set a descendant as parent)
                    if ($value) {
                        $descendantIds = IncidentCategory::where('parent_id', $category->id)->pluck('id')->toArray();
                        if (in_array($value, $descendantIds)) {
                            $fail('Cannot set a subcategory as the parent (circular reference).');
                        }
                    }
                }
            ],
            'category_key' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('incident_categories')->where(function ($query) use ($user) {
                    return $query->where('company_id', $user->company_id);
                })->ignore($id)
            ],
            'sort_order' => 'nullable|integer|min:0|max:999',
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
                'parent_id',
                'category_key',
                'sort_order',
                'status'
            ]);
            // Don't allow changing company_id

            $category->update($categoryData);

            // Load relationships for response
            $category->load(['parent', 'subcategories' => function ($q) {
                $q->orderBy('sort_order')->orderBy('name');
            }]);
            $category->loadCount('subcategories');

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

            $categoryName = $category->name;
            $subcategoriesCount = $category->subcategories_count;
            $category->delete();

            return response()->json([
                'success' => true,
                'message' => "Incident category '{$categoryName}' deleted successfully",
                'details' => [
                    'subcategories_deleted' => $forceDelete ? $subcategoriesCount : 0,
                    'subcategories_reassigned' => $reassignParent ? $subcategoriesCount : 0
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
                'parent_categories' => IncidentCategory::where('company_id', $companyId)->whereNull('parent_id')->count(),
                'subcategories' => IncidentCategory::where('company_id', $companyId)->whereNotNull('parent_id')->count(),
                'categories_with_auto_assignment' => IncidentCategory::where('company_id', $companyId)
                    ->whereNotNull('auto_assign_to_department')->count(),
            ];

            // Get category breakdown with hierarchy info
            $categoryBreakdown = IncidentCategory::where('company_id', $companyId)
                ->select('id', 'name', 'status', 'color', 'auto_assign_to_department', 'parent_id', 'category_key', 'sort_order')
                ->withCount('subcategories')
                ->orderBy('sort_order')
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
