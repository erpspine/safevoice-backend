<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SectorFeedbackTemplate;
use App\Models\Company;
use App\Services\FeedbackCategoryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SectorFeedbackTemplateController extends Controller
{
    /**
     * Display a listing of all feedback templates.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = SectorFeedbackTemplate::query();

            // Filter by sector
            if ($request->filled('sector')) {
                $query->where('sector', $request->sector);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->boolean('status'));
            }

            // Filter by category_key
            if ($request->filled('category_key')) {
                $query->where('category_key', $request->category_key);
            }

            // Search by name
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('category_name', 'ILIKE', "%{$search}%")
                        ->orWhere('subcategory_name', 'ILIKE', "%{$search}%");
                });
            }

            $templates = $query->orderBy('sector')
                ->orderBy('sort_order')
                ->orderBy('category_name')
                ->orderBy('subcategory_name')
                ->get();

            // Group by sector for easier consumption
            $groupedBySector = $templates->groupBy('sector');

            return response()->json([
                'success' => true,
                'data' => [
                    'templates' => $templates,
                    'by_sector' => $groupedBySector,
                    'total' => $templates->count(),
                    'sectors_count' => $groupedBySector->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve feedback templates',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a new feedback template.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sector' => 'required|string|in:education,corporate_workplace,financial_insurance,healthcare,manufacturing_industrial,construction_engineering,security_uniformed_services,hospitality_travel_tourism,ngo_cso_donor_funded,religious_institutions,transport_logistics',
            'category_key' => 'required|string|max:100',
            'category_name' => 'required|string|max:255',
            'subcategory_name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Check for duplicate
            $exists = SectorFeedbackTemplate::where('sector', $request->sector)
                ->where('category_key', $request->category_key)
                ->where('subcategory_name', $request->subcategory_name)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'A template with this sector, category key, and subcategory already exists',
                ], 422);
            }

            $template = SectorFeedbackTemplate::create([
                'sector' => $request->sector,
                'category_key' => $request->category_key,
                'category_name' => $request->category_name,
                'subcategory_name' => $request->subcategory_name,
                'description' => $request->description,
                'status' => $request->boolean('status', true),
                'sort_order' => $request->integer('sort_order', 0),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Feedback template created successfully',
                'data' => $template,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create feedback template',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified feedback template.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $template = SectorFeedbackTemplate::findOrFail($id);

            // Get related templates (same sector and category_key)
            $relatedTemplates = SectorFeedbackTemplate::where('sector', $template->sector)
                ->where('category_key', $template->category_key)
                ->where('id', '!=', $template->id)
                ->orderBy('sort_order')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'template' => $template,
                    'related_templates' => $relatedTemplates,
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Feedback template not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve feedback template',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified feedback template.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category_key' => 'string|max:100',
            'category_name' => 'string|max:255',
            'subcategory_name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $template = SectorFeedbackTemplate::findOrFail($id);

            $template->update($request->only([
                'category_key',
                'category_name',
                'subcategory_name',
                'description',
                'status',
                'sort_order',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Feedback template updated successfully',
                'data' => $template->fresh(),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Feedback template not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update feedback template',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified feedback template.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $template = SectorFeedbackTemplate::findOrFail($id);
            $template->delete();

            return response()->json([
                'success' => true,
                'message' => 'Feedback template deleted successfully',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Feedback template not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete feedback template',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get templates by sector.
     */
    public function bySector(string $sector): JsonResponse
    {
        try {
            $validSectors = [
                'education',
                'corporate_workplace',
                'financial_insurance',
                'healthcare',
                'manufacturing_industrial',
                'construction_engineering',
                'security_uniformed_services',
                'hospitality_travel_tourism',
                'ngo_cso_donor_funded',
                'religious_institutions',
                'transport_logistics',
            ];

            if (!in_array($sector, $validSectors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid sector',
                    'valid_sectors' => $validSectors,
                ], 422);
            }

            $templates = SectorFeedbackTemplate::where('sector', $sector)
                ->orderBy('sort_order')
                ->orderBy('category_name')
                ->orderBy('subcategory_name')
                ->get();

            // Structure the response
            $structured = SectorFeedbackTemplate::getBySector($sector);

            return response()->json([
                'success' => true,
                'data' => [
                    'sector' => $sector,
                    'templates' => $templates,
                    'structured' => $structured,
                    'total' => $templates->count(),
                    'categories_count' => count($structured),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve templates for sector',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Sync templates to all companies in a sector.
     */
    public function syncToCompanies(string $sector): JsonResponse
    {
        try {
            $categoryService = new FeedbackCategoryService();
            $result = $categoryService->syncAllCompaniesInSector($sector);

            return response()->json([
                'success' => true,
                'message' => "Feedback categories synced to {$result['companies_processed']} companies",
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync templates to companies',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Bulk store templates.
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sector' => 'required|string|in:education,corporate_workplace,financial_insurance,healthcare,manufacturing_industrial,construction_engineering,security_uniformed_services,hospitality_travel_tourism,ngo_cso_donor_funded,religious_institutions,transport_logistics',
            'categories' => 'required|array|min:1',
            'categories.*.key' => 'required|string|max:100',
            'categories.*.name' => 'required|string|max:255',
            'categories.*.description' => 'nullable|string|max:1000',
            'categories.*.subcategories' => 'nullable|array',
            'categories.*.subcategories.*' => 'string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $created = [];
            $updated = [];

            DB::transaction(function () use ($request, &$created, &$updated) {
                $sector = $request->sector;
                $categoryOrder = 1;

                foreach ($request->categories as $category) {
                    $subcategoryOrder = 1;

                    // Create/update parent category entry
                    $parentTemplate = SectorFeedbackTemplate::updateOrCreate(
                        [
                            'sector' => $sector,
                            'category_key' => $category['key'],
                            'subcategory_name' => null,
                        ],
                        [
                            'category_name' => $category['name'],
                            'description' => $category['description'] ?? null,
                            'sort_order' => $categoryOrder * 100,
                            'status' => true,
                        ]
                    );

                    if ($parentTemplate->wasRecentlyCreated) {
                        $created[] = $parentTemplate->category_name;
                    } else {
                        $updated[] = $parentTemplate->category_name;
                    }

                    // Create/update subcategories
                    foreach ($category['subcategories'] ?? [] as $subcategory) {
                        $subTemplate = SectorFeedbackTemplate::updateOrCreate(
                            [
                                'sector' => $sector,
                                'category_key' => $category['key'],
                                'subcategory_name' => $subcategory,
                            ],
                            [
                                'category_name' => $category['name'],
                                'description' => null,
                                'sort_order' => ($categoryOrder * 100) + $subcategoryOrder,
                                'status' => true,
                            ]
                        );

                        if ($subTemplate->wasRecentlyCreated) {
                            $created[] = $category['name'] . ' > ' . $subcategory;
                        }

                        $subcategoryOrder++;
                    }

                    $categoryOrder++;
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Templates created/updated successfully',
                'data' => [
                    'created' => $created,
                    'updated' => $updated,
                    'created_count' => count($created),
                    'updated_count' => count($updated),
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create templates',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Delete all templates for a sector.
     */
    public function destroyBySector(string $sector): JsonResponse
    {
        try {
            $count = SectorFeedbackTemplate::where('sector', $sector)->count();
            SectorFeedbackTemplate::where('sector', $sector)->delete();

            return response()->json([
                'success' => true,
                'message' => "{$count} templates deleted for sector: {$sector}",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete templates',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get statistics about feedback templates.
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total_templates' => SectorFeedbackTemplate::count(),
                'active_templates' => SectorFeedbackTemplate::where('status', true)->count(),
                'inactive_templates' => SectorFeedbackTemplate::where('status', false)->count(),
                'by_sector' => SectorFeedbackTemplate::select('sector')
                    ->selectRaw('COUNT(*) as total')
                    ->selectRaw('SUM(CASE WHEN subcategory_name IS NULL THEN 1 ELSE 0 END) as categories')
                    ->selectRaw('SUM(CASE WHEN subcategory_name IS NOT NULL THEN 1 ELSE 0 END) as subcategories')
                    ->groupBy('sector')
                    ->get()
                    ->keyBy('sector'),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
