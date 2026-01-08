<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\IncidentCategory;
use App\Models\Company;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class IncidentCategoryController extends Controller
{
    /**
     * Display a listing of incident categories with filters.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = IncidentCategory::with([
                'company:id,name',
                'parent:id,name',
                'subcategories' => function ($query) {
                    $query->select(['id', 'parent_id', 'company_id', 'name', 'description', 'status', 'sort_order'])
                        ->orderBy('sort_order')
                        ->orderBy('name');
                }
            ]);

            // Apply sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');

            if (in_array($sortBy, ['name', 'status', 'created_at'])) {
                $query->orderBy($sortBy, $sortDirection);
            }

            // Get all categories without pagination
            $categories = $query->get();

            return response()->json([
                'success' => true,
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve incident categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created incident category.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'company_id' => 'string|required|exists:companies,id',
            'name' => 'required|string|max:255',
            'name_sw' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_sw' => 'nullable|string',
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

            $category = IncidentCategory::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Incident category created successfully',
                'data' => [
                    'incident_category' => $category->load(['company:id,name'])
                ]
            ], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found or department invalid'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create incident category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified incident category with its relationships.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $category = IncidentCategory::with([
                'company:id,name,logo',
                'routingRules' => function ($query) {
                    $query->select('id', 'category_id', 'name', 'description', 'priority', 'type', 'recipients_json', 'is_active')
                        ->where('is_active', true)
                        ->orderBy('priority', 'desc');
                }
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $category,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Incident category not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve incident category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified incident category.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $category = IncidentCategory::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'company_id' => 'sometimes|string|required|exists:companies,id',
                'name' => 'sometimes|required|string|max:255',
                'name_sw' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'description_sw' => 'nullable|string',
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

            $category->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Incident category updated successfully',
                'data' => [
                    'incident_category' => $category->fresh()->load(['company:id,name'])
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Incident category not found or invalid company/department'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update incident category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified incident category (soft delete).
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $category = IncidentCategory::findOrFail($id);

            // Check if category has active routing rules
            $activeRules = $category->routingRules()->where('is_active', true)->count();

            if ($activeRules > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete incident category with active routing rules',
                    'details' => [
                        'active_routing_rules' => $activeRules
                    ]
                ], 422);
            }

            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Incident category deleted successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Incident category not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete incident category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get incident categories by company.
     */
    public function byCompany(string $companyId): JsonResponse
    {
        try {
            $company = Company::findOrFail($companyId);

            // Get root categories (no parent) with their subcategories
            $categories = IncidentCategory::where('company_id', $companyId)
                ->whereNull('parent_id')
                ->with(['subcategories' => function ($query) {
                    $query->orderBy('sort_order')->orderBy('name');
                }])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'company' => $company->only(['id', 'name', 'sector']),
                    'incident_categories' => $categories
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
                'message' => 'Failed to retrieve incident categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get incident category statistics dashboard.
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total_categories' => IncidentCategory::count(),
                'active_categories' => IncidentCategory::where('status', true)->count(),
                'inactive_categories' => IncidentCategory::where('status', false)->count(),
                'categories_by_company' => IncidentCategory::with('company:id,name')
                    ->get()
                    ->groupBy('company.name')
                    ->map->count(),
                'recent_categories' => IncidentCategory::with(['company:id,name'])
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

    /**
     * Public API to get incident categories for a specific company (no authentication required).
     * Returns only active incident categories with minimal information for frontend use.
     */
    public function publicByCompany(string $companyId): JsonResponse
    {
        try {
            // Verify company exists and is active
            $company = Company::where('id', $companyId)
                ->where('status', true)
                ->firstOrFail();

            // Get active root incident categories for the company with their active subcategories
            $categories = IncidentCategory::where('company_id', $companyId)
                ->whereNull('parent_id') // Only root categories
                ->where('status', true) // Only active categories
                ->with(['subcategories' => function ($query) {
                    $query->where('status', true)
                        ->select(['id', 'parent_id', 'name', 'description'])
                        ->orderBy('sort_order')
                        ->orderBy('name');
                }])
                ->select([
                    'id',
                    'name',
                    'description',
                ])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Incident categories retrieved successfully.',
                'data' => $categories
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found or inactive.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve incident categories.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Public API to get all active incident categories (no authentication required).
     * Returns only active incident categories for frontend dropdowns and forms.
     */
    public function publicIndex(): JsonResponse
    {
        try {
            // Get all active incident categories with company info
            $categories = IncidentCategory::where('status', true)
                ->with(['company:id,name'])
                ->select([
                    'id',
                    'company_id',
                    'name',
                    'description',
                ])
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Incident categories retrieved successfully.',
                'data' => [
                    'categories' => $categories,
                    'total' => $categories->count()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve incident categories.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Public API to get parent (root) incident categories for a specific company.
     * Used for the first dropdown in user portal case submission.
     */
    public function publicParentCategories(Request $request, string $companyId): JsonResponse
    {
        try {
            // Validate language parameter
            $validator = Validator::make($request->all(), [
                'language' => 'nullable|string|in:en,sw'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $language = $request->input('language', 'en');

            // Verify company exists and is active
            $company = Company::where('id', $companyId)
                ->where('status', true)
                ->firstOrFail();

            // Get only parent categories (no parent_id)
            $categories = IncidentCategory::where('company_id', $companyId)
                ->whereNull('parent_id')
                ->where('status', true)
                ->select(['id', 'name', 'name_sw', 'description', 'description_sw'])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(function ($category) use ($language) {
                    return [
                        'id' => $category->id,
                        'name' => $language === 'sw' ? ($category->name_sw ?? $category->name) : $category->name,
                        'description' => $language === 'sw' ? ($category->description_sw ?? $category->description) : $category->description,
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Parent categories retrieved successfully.',
                'data' => [
                    'language' => $language,
                    'company_id' => $companyId,
                    'company_name' => $company->name,
                    'categories' => $categories,
                    'total' => $categories->count()
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found or inactive.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve parent categories.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Public API to get subcategories for a specific parent category.
     * Used for the second dropdown in user portal case submission.
     */
    public function publicSubcategories(Request $request, string $companyId, string $parentId): JsonResponse
    {
        try {
            // Validate language parameter
            $validator = Validator::make($request->all(), [
                'language' => 'nullable|string|in:en,sw'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $language = $request->input('language', 'en');

            // Verify company exists and is active
            $company = Company::where('id', $companyId)
                ->where('status', true)
                ->firstOrFail();

            // Verify parent category exists and belongs to the company
            $parentCategory = IncidentCategory::where('id', $parentId)
                ->where('company_id', $companyId)
                ->where('status', true)
                ->whereNull('parent_id')
                ->select(['id', 'name', 'name_sw'])
                ->firstOrFail();

            // Get subcategories for this parent
            $subcategories = IncidentCategory::where('company_id', $companyId)
                ->where('parent_id', $parentId)
                ->where('status', true)
                ->select(['id', 'name', 'name_sw', 'description', 'description_sw'])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(function ($category) use ($language) {
                    return [
                        'id' => $category->id,
                        'name' => $language === 'sw' ? ($category->name_sw ?? $category->name) : $category->name,
                        'description' => $language === 'sw' ? ($category->description_sw ?? $category->description) : $category->description,
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Subcategories retrieved successfully.',
                'data' => [
                    'language' => $language,
                    'company_id' => $companyId,
                    'parent_category' => [
                        'id' => $parentCategory->id,
                        'name' => $language === 'sw' ? ($parentCategory->name_sw ?? $parentCategory->name) : $parentCategory->name,
                    ],
                    'subcategories' => $subcategories,
                    'total' => $subcategories->count()
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Company, or parent category not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve subcategories.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
