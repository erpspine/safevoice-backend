<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeedbackCategory;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class FeedbackCategoryController extends Controller
{
    /**
     * Display a listing of feedback categories with filters and search.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = FeedbackCategory::with(['company:id,name']);

            // Apply filters
            if ($request->has('company_id') && $request->company_id !== '') {
                $query->where('company_id', $request->company_id);
            }

            if ($request->has('status') && $request->status !== '') {
                $query->where('status', $request->boolean('status'));
            }

            // Search functionality
            if ($request->has('search') && $request->search !== '') {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ILIKE', "%{$search}%")
                        ->orWhere('description', 'ILIKE', "%{$search}%");
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');

            if (in_array($sortBy, ['name', 'status', 'created_at'])) {
                $query->orderBy($sortBy, $sortDirection);
            }

            $feedbackCategories = $query->get();

            return response()->json([
                'success' => true,
                'message' => 'Feedback categories retrieved successfully.',
                'data' => $feedbackCategories,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve feedback categories', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve feedback categories.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Store a newly created feedback category.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Validation rules
            $validator = Validator::make($request->all(), [
                'company_id' => 'required|exists:companies,id',
                'name' => [
                    'required',
                    'string',
                    'max:100',
                    Rule::unique('feedback_categories')->where(function ($query) use ($request) {
                        return $query->where('company_id', $request->company_id)
                            ->whereNull('deleted_at');
                    }),
                ],
                'name_sw' => 'nullable|string|max:100',
                'description' => 'nullable|string|max:500',
                'description_sw' => 'nullable|string|max:500',
                'status' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Create feedback category
            $feedbackCategory = FeedbackCategory::create([
                'company_id' => $request->company_id,
                'name' => $request->name,
                'name_sw' => $request->name_sw,
                'description' => $request->description,
                'description_sw' => $request->description_sw,
                'status' => $request->boolean('status', true),
            ]);

            // Load relationships
            $feedbackCategory->load(['company:id,name']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Feedback category created successfully.',
                'data' => [
                    'feedback_category' => $feedbackCategory,
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create feedback category', [
                'request_data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create feedback category.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Display the specified feedback category.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $feedbackCategory = FeedbackCategory::with(['company:id,name'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Feedback category retrieved successfully.',
                'data' => $feedbackCategory
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Feedback category not found.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve feedback category', [
                'feedback_category_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve feedback category.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update the specified feedback category.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $feedbackCategory = FeedbackCategory::findOrFail($id);

            // Validation rules
            $validator = Validator::make($request->all(), [
                'company_id' => 'sometimes|required|exists:companies,id',
                'name' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:100',
                    Rule::unique('feedback_categories')->where(function ($query) use ($request, $id, $feedbackCategory) {
                        $query = $query->where('company_id', $request->company_id ?? $feedbackCategory->company_id)
                            ->whereNull('deleted_at');
                        if ($id) {
                            $query->where('id', '!=', $id);
                        }
                        return $query;
                    }),
                ],
                'name_sw' => 'sometimes|nullable|string|max:100',
                'description' => 'sometimes|nullable|string|max:500',
                'description_sw' => 'sometimes|nullable|string|max:500',
                'status' => 'sometimes|required|boolean',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update feedback category
            $updateData = [];
            foreach (['company_id', 'name', 'name_sw', 'description', 'description_sw', 'status'] as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $request->$field;
                }
            }

            $feedbackCategory->update($updateData);

            // Load relationships
            $feedbackCategory->load(['company:id,name']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Feedback category updated successfully.',
                'data' => [
                    'feedback_category' => $feedbackCategory,
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Feedback category not found.',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update feedback category', [
                'feedback_category_id' => $id,
                'request_data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update feedback category.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove the specified feedback category from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $feedbackCategory = FeedbackCategory::findOrFail($id);

            // Check if feedback category has any associated feedback/cases
            // This would prevent deletion if there are dependent records
            // $hasFeedback = $feedbackCategory->feedback()->exists();

            // if ($hasFeedback) {
            //     DB::rollBack();
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Cannot delete feedback category. It has associated feedback records.',
            //     ], 400);
            // }

            $feedbackCategory->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Feedback category deleted successfully.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Feedback category not found.',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete feedback category', [
                'feedback_category_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete feedback category.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get feedback categories by company
     */
    public function byCompany(string $companyId): JsonResponse
    {
        try {
            $company = Company::findOrFail($companyId);

            $feedbackCategories = FeedbackCategory::where('company_id', $companyId)
                ->where('status', true)
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Feedback categories retrieved successfully.',
                'data' => [
                    'company' => [
                        'id' => $company->id,
                        'name' => $company->name,
                    ],
                    'feedback_categories' => $feedbackCategories,
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve feedback categories by company', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve feedback categories.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get statistics for feedback categories
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total_categories' => FeedbackCategory::count(),
                'active_categories' => FeedbackCategory::where('status', true)->count(),
                'inactive_categories' => FeedbackCategory::where('status', false)->count(),
                'categories_by_company' => FeedbackCategory::select('company_id')
                    ->with('company:id,name')
                    ->selectRaw('company_id, count(*) as category_count')
                    ->groupBy('company_id')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'company_id' => $item->company_id,
                            'company_name' => $item->company->name ?? 'Unknown',
                            'category_count' => $item->category_count,
                        ];
                    }),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Feedback category statistics retrieved successfully.',
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve feedback category statistics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Public API to get feedback categories for a specific company (no authentication required).
     * Returns only active feedback categories with hierarchical structure (parent with nested subcategories).
     */
    public function publicByCompany(string $companyId): JsonResponse
    {
        try {
            // Verify company exists and is active
            $company = Company::where('id', $companyId)
                ->where('status', true)
                ->firstOrFail();

            // Get active root feedback categories for the company with their active subcategories
            $categories = FeedbackCategory::where('company_id', $companyId)
                ->whereNull('parent_id') // Only root categories
                ->where('status', true) // Only active categories
                ->with(['children' => function ($query) {
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
                'message' => 'Feedback categories retrieved successfully.',
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
                'message' => 'Failed to retrieve feedback categories.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Public API to get all active feedback categories (no authentication required).
     * Returns only active feedback categories for frontend dropdowns and forms.
     */
    public function publicIndex(): JsonResponse
    {
        try {
            // Get all active feedback categories with company info
            $categories = FeedbackCategory::where('status', true)
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
                'message' => 'Feedback categories retrieved successfully.',
                'data' => [
                    'categories' => $categories,
                    'total' => $categories->count()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve feedback categories.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Public API to get parent (root) feedback categories for a specific company.
     * Used for the first dropdown in user portal feedback submission.
     */
    public function publicParentCategories(Request $request, string $companyId): JsonResponse
    {
        try {
            // Get language parameter (default to English)
            $language = $request->input('language', 'en');

            // Validate language
            if (!in_array($language, ['en', 'sw'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid language. Use "en" or "sw".',
                ], 400);
            }

            // Verify company exists and is active
            $company = Company::where('id', $companyId)
                ->where('status', true)
                ->firstOrFail();

            // Get only parent categories (no parent_id)
            $categories = FeedbackCategory::where('company_id', $companyId)
                ->whereNull('parent_id')
                ->where('status', true)
                ->select(['id', 'name', 'name_sw', 'description', 'description_sw'])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(function ($category) use ($language) {
                    return [
                        'id' => $category->id,
                        'name' => $language === 'sw' && $category->name_sw ? $category->name_sw : $category->name,
                        'description' => $language === 'sw' && $category->description_sw ? $category->description_sw : $category->description,
                    ];
                });

            $message = $language === 'sw'
                ? 'Aina kuu za maoni zimepatikana'
                : 'Parent feedback categories retrieved successfully.';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'company_id' => $companyId,
                    'company_name' => $company->name,
                    'categories' => $categories,
                    'total' => $categories->count(),
                    'language' => $language
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found or inactive.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve parent feedback categories', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve parent feedback categories.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Public API to get subcategories for a specific parent feedback category.
     * Used for the second dropdown in user portal feedback submission.
     */
    public function publicSubcategories(Request $request, string $companyId, string $parentId): JsonResponse
    {
        try {
            // Get language parameter (default to English)
            $language = $request->input('language', 'en');

            // Validate language
            if (!in_array($language, ['en', 'sw'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid language. Use "en" or "sw".',
                ], 400);
            }

            // Verify company exists and is active
            $company = Company::where('id', $companyId)
                ->where('status', true)
                ->firstOrFail();

            // Verify parent category exists and belongs to the company
            $parentCategory = FeedbackCategory::where('id', $parentId)
                ->where('company_id', $companyId)
                ->where('status', true)
                ->whereNull('parent_id')
                ->select(['id', 'name', 'name_sw'])
                ->firstOrFail();

            // Get subcategories for this parent
            $subcategories = FeedbackCategory::where('company_id', $companyId)
                ->where('parent_id', $parentId)
                ->where('status', true)
                ->select(['id', 'name', 'name_sw', 'description', 'description_sw'])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(function ($category) use ($language) {
                    return [
                        'id' => $category->id,
                        'name' => $language === 'sw' && $category->name_sw ? $category->name_sw : $category->name,
                        'description' => $language === 'sw' && $category->description_sw ? $category->description_sw : $category->description,
                    ];
                });

            $message = $language === 'sw'
                ? 'Aina ndogo za maoni zimepatikana'
                : 'Feedback subcategories retrieved successfully.';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'company_id' => $companyId,
                    'parent_category' => [
                        'id' => $parentCategory->id,
                        'name' => $language === 'sw' && $parentCategory->name_sw ? $parentCategory->name_sw : $parentCategory->name,
                    ],
                    'subcategories' => $subcategories,
                    'total' => $subcategories->count(),
                    'language' => $language
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Company or parent category not found.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve feedback subcategories', [
                'company_id' => $companyId,
                'parent_id' => $parentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve feedback subcategories.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
