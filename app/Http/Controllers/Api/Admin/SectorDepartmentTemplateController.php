<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SectorDepartmentTemplate;
use App\Models\Company;
use App\Services\DepartmentTemplateService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class SectorDepartmentTemplateController extends Controller
{
    protected DepartmentTemplateService $templateService;

    public function __construct(DepartmentTemplateService $templateService)
    {
        $this->templateService = $templateService;
    }

    /**
     * Display a listing of department templates.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = SectorDepartmentTemplate::query();

            // Filter by sector
            if ($request->has('sector') && $request->sector !== '') {
                $query->where('sector', $request->sector);
            }

            // Filter by status
            if ($request->has('status') && $request->status !== '') {
                $query->where('status', $request->boolean('status'));
            }

            // Search
            if ($request->has('search') && $request->search !== '') {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('department_name', 'ILIKE', "%{$search}%")
                        ->orWhere('department_code', 'ILIKE', "%{$search}%")
                        ->orWhere('description', 'ILIKE', "%{$search}%");
                });
            }

            $templates = $query->orderBy('sector')
                ->orderBy('sort_order')
                ->orderBy('department_name')
                ->get();

            // Group by sector for better organization
            $grouped = $templates->groupBy('sector')->map(function ($items, $sector) {
                return [
                    'sector' => $sector,
                    'sector_name' => SectorDepartmentTemplate::SECTORS[$sector] ?? $sector,
                    'count' => $items->count(),
                    'templates' => $items,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'message' => 'Department templates retrieved successfully.',
                'data' => [
                    'templates' => $templates,
                    'grouped' => $grouped,
                    'total' => $templates->count(),
                    'sectors' => SectorDepartmentTemplate::SECTORS,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve department templates', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve department templates.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Store a new department template.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'sector' => ['required', 'string', Rule::in(array_keys(SectorDepartmentTemplate::SECTORS))],
                'department_code' => [
                    'required',
                    'string',
                    'max:20',
                    Rule::unique('sector_department_templates')->where(function ($query) use ($request) {
                        return $query->where('sector', $request->sector);
                    }),
                ],
                'department_name' => 'required|string|max:100',
                'description' => 'nullable|string|max:500',
                'status' => 'nullable|boolean',
                'sort_order' => 'nullable|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $template = SectorDepartmentTemplate::create([
                'sector' => $request->sector,
                'department_code' => $request->department_code,
                'department_name' => $request->department_name,
                'description' => $request->description,
                'status' => $request->boolean('status', true),
                'sort_order' => $request->input('sort_order', 0),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Department template created successfully.',
                'data' => $template,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create department template', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create department template.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Display a specific department template.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $template = SectorDepartmentTemplate::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Department template retrieved successfully.',
                'data' => $template,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Department template not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve department template.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update a department template.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $template = SectorDepartmentTemplate::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'sector' => ['sometimes', 'string', Rule::in(array_keys(SectorDepartmentTemplate::SECTORS))],
                'department_code' => [
                    'sometimes',
                    'string',
                    'max:20',
                    Rule::unique('sector_department_templates')->where(function ($query) use ($request, $template) {
                        return $query->where('sector', $request->input('sector', $template->sector));
                    })->ignore($template->id),
                ],
                'department_name' => 'sometimes|string|max:100',
                'description' => 'nullable|string|max:500',
                'status' => 'nullable|boolean',
                'sort_order' => 'nullable|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $template->update($request->only([
                'sector',
                'department_code',
                'department_name',
                'description',
                'status',
                'sort_order',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Department template updated successfully.',
                'data' => $template->fresh(),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Department template not found.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to update department template', [
                'error' => $e->getMessage(),
                'template_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update department template.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Delete a department template.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $template = SectorDepartmentTemplate::findOrFail($id);
            $template->delete();

            return response()->json([
                'success' => true,
                'message' => 'Department template deleted successfully.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Department template not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete department template.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get templates by sector.
     */
    public function bySector(string $sector): JsonResponse
    {
        try {
            if (!array_key_exists($sector, SectorDepartmentTemplate::SECTORS)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid sector.',
                    'valid_sectors' => SectorDepartmentTemplate::SECTORS,
                ], 422);
            }

            $templates = SectorDepartmentTemplate::where('sector', $sector)
                ->where('status', true)
                ->orderBy('sort_order')
                ->orderBy('department_name')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Department templates retrieved successfully.',
                'data' => [
                    'sector' => $sector,
                    'sector_name' => SectorDepartmentTemplate::SECTORS[$sector],
                    'templates' => $templates,
                    'total' => $templates->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve department templates.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Sync templates to all companies in a sector.
     */
    public function syncToCompanies(string $sector): JsonResponse
    {
        try {
            if (!array_key_exists($sector, SectorDepartmentTemplate::SECTORS)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid sector.',
                    'valid_sectors' => SectorDepartmentTemplate::SECTORS,
                ], 422);
            }

            $result = $this->templateService->syncAllCompaniesInSector($sector);

            return response()->json([
                'success' => true,
                'message' => "Departments synced to {$result['synced']} companies in {$sector} sector.",
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to sync departments to companies', [
                'error' => $e->getMessage(),
                'sector' => $sector
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync departments to companies.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Bulk create templates for a sector.
     */
    public function bulkStore(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'sector' => ['required', 'string', Rule::in(array_keys(SectorDepartmentTemplate::SECTORS))],
                'templates' => 'required|array|min:1',
                'templates.*.department_code' => 'required|string|max:20',
                'templates.*.department_name' => 'required|string|max:100',
                'templates.*.description' => 'nullable|string|max:500',
                'templates.*.sort_order' => 'nullable|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $created = [];
            $failed = [];

            DB::transaction(function () use ($request, &$created, &$failed) {
                foreach ($request->templates as $index => $templateData) {
                    try {
                        // Check for duplicate code in sector
                        $exists = SectorDepartmentTemplate::where('sector', $request->sector)
                            ->where('department_code', $templateData['department_code'])
                            ->exists();

                        if ($exists) {
                            $failed[] = [
                                'index' => $index,
                                'code' => $templateData['department_code'],
                                'reason' => 'Department code already exists in this sector',
                            ];
                            continue;
                        }

                        $template = SectorDepartmentTemplate::create([
                            'sector' => $request->sector,
                            'department_code' => $templateData['department_code'],
                            'department_name' => $templateData['department_name'],
                            'description' => $templateData['description'] ?? null,
                            'status' => true,
                            'sort_order' => $templateData['sort_order'] ?? $index,
                        ]);

                        $created[] = $template;
                    } catch (\Exception $e) {
                        $failed[] = [
                            'index' => $index,
                            'code' => $templateData['department_code'] ?? 'unknown',
                            'reason' => $e->getMessage(),
                        ];
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => sprintf('%d templates created, %d failed.', count($created), count($failed)),
                'data' => [
                    'created' => $created,
                    'failed' => $failed,
                ],
            ], count($created) > 0 ? 201 : 422);
        } catch (\Exception $e) {
            Log::error('Failed to bulk create department templates', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk create department templates.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Delete all templates for a sector.
     */
    public function destroyBySector(string $sector): JsonResponse
    {
        try {
            if (!array_key_exists($sector, SectorDepartmentTemplate::SECTORS)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid sector.',
                    'valid_sectors' => SectorDepartmentTemplate::SECTORS,
                ], 422);
            }

            $count = SectorDepartmentTemplate::where('sector', $sector)->delete();

            return response()->json([
                'success' => true,
                'message' => "{$count} department templates deleted from {$sector} sector.",
                'data' => [
                    'deleted_count' => $count,
                    'sector' => $sector,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete department templates.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get statistics for department templates.
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total_templates' => SectorDepartmentTemplate::count(),
                'active_templates' => SectorDepartmentTemplate::where('status', true)->count(),
                'inactive_templates' => SectorDepartmentTemplate::where('status', false)->count(),
                'templates_by_sector' => SectorDepartmentTemplate::select('sector')
                    ->selectRaw('count(*) as template_count')
                    ->selectRaw('sum(case when status = true then 1 else 0 end) as active_count')
                    ->groupBy('sector')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'sector' => $item->sector,
                            'sector_name' => SectorDepartmentTemplate::SECTORS[$item->sector] ?? $item->sector,
                            'template_count' => $item->template_count,
                            'active_count' => $item->active_count,
                        ];
                    }),
                'companies_by_sector' => Company::select('sector')
                    ->selectRaw('count(*) as company_count')
                    ->whereNotNull('sector')
                    ->groupBy('sector')
                    ->get()
                    ->keyBy('sector')
                    ->map(fn($item) => $item->company_count),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Department template statistics retrieved successfully.',
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve department template statistics', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
