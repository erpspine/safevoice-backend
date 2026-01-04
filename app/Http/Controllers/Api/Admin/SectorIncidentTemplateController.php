<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SectorIncidentTemplate;
use App\Models\Company;
use App\Services\IncidentCategoryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SectorIncidentTemplateController extends Controller
{
    /**
     * Available sectors.
     */
    protected array $sectors = [
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

    /**
     * Display a listing of all category templates.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = SectorIncidentTemplate::query();

            // Filter by sector
            if ($request->has('sector') && $request->sector) {
                $query->where('sector', $request->sector);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', filter_var($request->status, FILTER_VALIDATE_BOOLEAN));
            }

            // Filter by category_key
            if ($request->has('category_key') && $request->category_key) {
                $query->where('category_key', $request->category_key);
            }

            // Search by name
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('category_name', 'ilike', "%{$search}%")
                        ->orWhere('subcategory_name', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%");
                });
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'sort_order');
            $sortDirection = $request->get('sort_direction', 'asc');

            if (in_array($sortBy, ['sector', 'category_name', 'subcategory_name', 'sort_order', 'created_at'])) {
                $query->orderBy($sortBy, $sortDirection);
            }

            // Secondary sorting
            $query->orderBy('category_name', 'asc')
                ->orderBy('subcategory_name', 'asc');

            // Pagination
            $perPage = min($request->get('per_page', 50), 100);
            $templates = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $templates->items(),
                'meta' => [
                    'current_page' => $templates->currentPage(),
                    'last_page' => $templates->lastPage(),
                    'per_page' => $templates->perPage(),
                    'total' => $templates->total(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve category templates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created category template.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sector' => ['required', 'string', Rule::in($this->sectors)],
            'category_key' => 'required|string|max:100',
            'category_name' => 'required|string|max:255',
            'subcategory_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check for duplicate
            $exists = SectorIncidentTemplate::where('sector', $request->sector)
                ->where('category_key', $request->category_key)
                ->where('subcategory_name', $request->subcategory_name)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'A template with this sector, category key, and subcategory already exists'
                ], 422);
            }

            $template = SectorIncidentTemplate::create([
                'sector' => $request->sector,
                'category_key' => $request->category_key,
                'category_name' => $request->category_name,
                'subcategory_name' => $request->subcategory_name,
                'description' => $request->description,
                'status' => $request->status ?? true,
                'sort_order' => $request->sort_order ?? 0,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Category template created successfully',
                'data' => [
                    'template' => $template
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create category template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified category template.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $template = SectorIncidentTemplate::findOrFail($id);

            // Get count of companies using this sector
            $companiesCount = Company::where('sector', $template->sector)->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'template' => $template,
                    'companies_using_sector' => $companiesCount
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Category template not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve category template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified category template.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $template = SectorIncidentTemplate::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'sector' => ['sometimes', 'required', 'string', Rule::in($this->sectors)],
                'category_key' => 'sometimes|required|string|max:100',
                'category_name' => 'sometimes|required|string|max:255',
                'subcategory_name' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'status' => 'boolean',
                'sort_order' => 'integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check for duplicate if changing key fields
            if ($request->has('sector') || $request->has('category_key') || $request->has('subcategory_name')) {
                $sector = $request->sector ?? $template->sector;
                $categoryKey = $request->category_key ?? $template->category_key;
                $subcategoryName = $request->subcategory_name ?? $template->subcategory_name;

                $exists = SectorIncidentTemplate::where('sector', $sector)
                    ->where('category_key', $categoryKey)
                    ->where('subcategory_name', $subcategoryName)
                    ->where('id', '!=', $id)
                    ->exists();

                if ($exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'A template with this sector, category key, and subcategory already exists'
                    ], 422);
                }
            }

            $template->update($request->only([
                'sector',
                'category_key',
                'category_name',
                'subcategory_name',
                'description',
                'status',
                'sort_order',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Category template updated successfully',
                'data' => [
                    'template' => $template->fresh()
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Category template not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update category template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified category template.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $template = SectorIncidentTemplate::findOrFail($id);
            $template->delete();

            return response()->json([
                'success' => true,
                'message' => 'Category template deleted successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Category template not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete category template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get templates grouped by sector.
     */
    public function bySector(string $sector): JsonResponse
    {
        try {
            if (!in_array($sector, $this->sectors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid sector',
                    'valid_sectors' => $this->sectors
                ], 422);
            }

            $templates = SectorIncidentTemplate::getBySector($sector);

            // Get companies using this sector
            $companiesCount = Company::where('sector', $sector)->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'sector' => $sector,
                    'categories' => $templates,
                    'total_categories' => count($templates),
                    'companies_using_sector' => $companiesCount
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve templates for sector',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get statistics for category templates.
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total_templates' => SectorIncidentTemplate::count(),
                'active_templates' => SectorIncidentTemplate::where('status', true)->count(),
                'inactive_templates' => SectorIncidentTemplate::where('status', false)->count(),
                'templates_by_sector' => SectorIncidentTemplate::select('sector')
                    ->selectRaw('COUNT(*) as count')
                    ->selectRaw('COUNT(DISTINCT category_key) as unique_categories')
                    ->groupBy('sector')
                    ->get()
                    ->keyBy('sector')
                    ->map(function ($item) {
                        return [
                            'total_templates' => $item->count,
                            'unique_categories' => $item->unique_categories,
                        ];
                    }),
                'companies_by_sector' => Company::select('sector')
                    ->whereNotNull('sector')
                    ->selectRaw('COUNT(*) as count')
                    ->groupBy('sector')
                    ->pluck('count', 'sector'),
                'available_sectors' => $this->sectors,
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
     * Sync templates to all companies in a sector.
     */
    public function syncToCompanies(Request $request, string $sector): JsonResponse
    {
        try {
            if (!in_array($sector, $this->sectors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid sector',
                    'valid_sectors' => $this->sectors
                ], 422);
            }

            $categoryService = new IncidentCategoryService();
            $results = $categoryService->syncAllCompaniesForSector($sector);

            return response()->json([
                'success' => true,
                'message' => "Synced templates to {$results['companies_synced']} companies",
                'data' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync templates to companies',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk create templates for a sector.
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sector' => ['required', 'string', Rule::in($this->sectors)],
            'templates' => 'required|array|min:1',
            'templates.*.category_key' => 'required|string|max:100',
            'templates.*.category_name' => 'required|string|max:255',
            'templates.*.subcategory_name' => 'nullable|string|max:255',
            'templates.*.description' => 'nullable|string',
            'templates.*.sort_order' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $sector = $request->sector;
            $created = [];
            $skipped = [];

            foreach ($request->templates as $index => $templateData) {
                // Check for duplicate
                $exists = SectorIncidentTemplate::where('sector', $sector)
                    ->where('category_key', $templateData['category_key'])
                    ->where('subcategory_name', $templateData['subcategory_name'] ?? null)
                    ->exists();

                if ($exists) {
                    $skipped[] = [
                        'index' => $index,
                        'category_key' => $templateData['category_key'],
                        'subcategory_name' => $templateData['subcategory_name'] ?? null,
                        'reason' => 'Already exists'
                    ];
                    continue;
                }

                $template = SectorIncidentTemplate::create([
                    'sector' => $sector,
                    'category_key' => $templateData['category_key'],
                    'category_name' => $templateData['category_name'],
                    'subcategory_name' => $templateData['subcategory_name'] ?? null,
                    'description' => $templateData['description'] ?? null,
                    'status' => true,
                    'sort_order' => $templateData['sort_order'] ?? 0,
                ]);

                $created[] = $template;
            }

            return response()->json([
                'success' => true,
                'message' => count($created) . ' templates created, ' . count($skipped) . ' skipped',
                'data' => [
                    'created' => $created,
                    'skipped' => $skipped,
                    'created_count' => count($created),
                    'skipped_count' => count($skipped),
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk create templates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete all templates for a sector.
     */
    public function destroyBySector(string $sector): JsonResponse
    {
        try {
            if (!in_array($sector, $this->sectors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid sector',
                    'valid_sectors' => $this->sectors
                ], 422);
            }

            $count = SectorIncidentTemplate::where('sector', $sector)->count();
            SectorIncidentTemplate::where('sector', $sector)->delete();

            return response()->json([
                'success' => true,
                'message' => "Deleted {$count} templates for sector: {$sector}"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete templates',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
