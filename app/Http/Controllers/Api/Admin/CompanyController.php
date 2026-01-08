<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\SubscriptionPlan;
use App\Services\IncidentCategoryService;
use App\Services\FeedbackCategoryService;
use App\Services\DepartmentTemplateService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    /**
     * Display a listing of companies with pagination and filters.
     */
    public function index(Request $request): JsonResponse
    {

        try {
            $query = Company::with('subscriptionPlan');

            // Get all companies without pagination
            $companies = $query->get();

            return response()->json([
                'success' => true,
                'data' => $companies,

            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve companies',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created company.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:companies,name',
            'email' => 'required|email|max:255|unique:companies,email',
            'contact' => 'nullable|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,jpg,png,gif,svg|max:2048',
            'address' => 'nullable|string',
            'website' => 'nullable|url|max:255',
            'description' => 'nullable|string',
            'sector' => 'nullable|in:education,corporate_workplace,financial_insurance,healthcare,manufacturing_industrial,construction_engineering,security_uniformed_services,hospitality_travel_tourism,ngo_cso_donor_funded,religious_institutions,transport_logistics',
            'tax_id' => 'nullable|string|max:255|unique:companies,tax_id',
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
            $data = $request->except(['logo']);

            // Handle logo upload
            if ($request->hasFile('logo')) {
                $logo = $request->file('logo');
                $logoName = time() . '_' . uniqid() . '.' . $logo->getClientOriginalExtension();
                $logoPath = $logo->storeAs('companies/logos', $logoName, 'public');
                $data['logo'] = $logoPath;
            }

            $company = Company::create($data);

            // Auto-populate incident categories based on sector
            if ($company->sector) {
                $categoryService = new IncidentCategoryService();
                $categories = $categoryService->createCategoriesFromSector($company);
            }

            // Auto-populate feedback categories based on sector
            if ($company->sector) {
                $feedbackCategoryService = new FeedbackCategoryService();
                $feedbackCategories = $feedbackCategoryService->createCategoriesFromSector($company);
            }

            // Auto-populate departments based on sector
            if ($company->sector) {
                $departmentService = new DepartmentTemplateService();
                $departments = $departmentService->createDepartmentsFromSector($company);
            }

            // Load the subscription plan relationship if exists
            $company->load('subscriptionPlan');

            return response()->json([
                'success' => true,
                'message' => 'Company created successfully',
                'data' => $company,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create company',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified company with its relationships.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $company = Company::with([
                'subscriptionPlan',
                'branches' => function ($query) {
                    $query->select('id', 'name', 'location', 'contact_phone', 'status', 'company_id')
                        ->orderBy('name');
                },
                'departments' => function ($query) {
                    $query->select('id', 'name', 'description', 'status', 'company_id')
                        ->orderBy('name');
                },
                'users' => function ($query) {
                    $query->select('id', 'name', 'email', 'role', 'status', 'company_id')
                        ->orderBy('name');
                }
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $company,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve company',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified company.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $company = Company::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('companies', 'name')->ignore($company->id)
                ],
                'email' => [
                    'sometimes',
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('companies', 'email')->ignore($company->id)
                ],
                'contact' => 'nullable|string|max:255',
                'logo' => 'nullable|image|mimes:jpeg,jpg,png,gif,svg|max:2048',
                'address' => 'nullable|string',
                'website' => 'nullable|url|max:255',
                'description' => 'nullable|string',
                'sector' => 'nullable|in:education,corporate_workplace,financial_insurance,healthcare,manufacturing_industrial,construction_engineering,security_uniformed_services,hospitality_travel_tourism,ngo_cso_donor_funded,religious_institutions,transport_logistics',
                'tax_id' => [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::unique('companies', 'tax_id')->ignore($company->id)
                ],
                'plan' => 'sometimes|required|exists:subscription_plans,id',
                'status' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $request->except(['logo', 'plan']);

            // Handle plan update if provided
            if ($request->filled('plan')) {
                $subscriptionPlan = SubscriptionPlan::findOrFail($request->plan);

                // Map subscription plan name to plan type enum
                $planName = strtolower($subscriptionPlan->name);
                $planType = 'free'; // default

                if (str_contains($planName, 'enterprise')) {
                    $planType = 'enterprise';
                } elseif (str_contains($planName, 'premium')) {
                    $planType = 'premium';
                } elseif (str_contains($planName, 'basic')) {
                    $planType = 'basic';
                }

                $data['plan'] = $planType;
                $data['plan_id'] = $subscriptionPlan->id;
            }

            // Handle logo upload
            if ($request->hasFile('logo')) {
                // Delete old logo if exists
                if ($company->logo && Storage::disk('public')->exists($company->logo)) {
                    Storage::disk('public')->delete($company->logo);
                }

                $logo = $request->file('logo');
                $logoName = time() . '_' . uniqid() . '.' . $logo->getClientOriginalExtension();
                $logoPath = $logo->storeAs('companies/logos', $logoName, 'public');
                $data['logo'] = $logoPath;
            }

            // Track sector changes
            $oldSector = $company->sector;
            $newSector = $data['sector'] ?? $oldSector;
            $sectorChanged = $oldSector !== $newSector && isset($data['sector']);

            $company->update($data);

            // Auto-sync incident categories if:
            // 1. Sector changed (from one value to another)
            // 2. Sector exists but company has no categories (initial sync)
            // 3. Sector was set from null to a value
            // 4. Company has fewer incident categories than templates (partial sync needed)
            // 5. Categories exist but some are missing Swahili translations
            $syncResult = null;
            $categoryService = new IncidentCategoryService();

            if ($company->sector) {
                $existingCategoriesCount = $company->incidentCategories()->count();
                $incidentTemplateCount = \App\Models\SectorIncidentTemplate::where('sector', $company->sector)
                    ->where('status', true)
                    ->count();

                // Check if any template-based categories are missing Swahili translations
                $categoriesMissingSwahili = $company->incidentCategories()
                    ->whereNotNull('category_key')
                    ->whereNull('name_sw')
                    ->exists();

                // Sync if sector changed, no categories exist, categories are incomplete, or translations missing
                if ($sectorChanged || $existingCategoriesCount === 0 || $existingCategoriesCount < $incidentTemplateCount || $categoriesMissingSwahili) {
                    $syncResult = $categoryService->syncCategoriesFromSector($company);
                }
            }

            // Auto-sync feedback categories if:
            // 1. Sector changed (from one value to another)
            // 2. Sector exists but company has no feedback categories (initial sync)
            // 3. Sector was set from null to a value
            // 4. Company has fewer feedback categories than templates (partial sync needed)
            // 5. Categories exist but some are missing Swahili translations
            $feedbackSyncResult = null;
            $feedbackCategoryService = new FeedbackCategoryService();

            if ($company->sector) {
                $existingFeedbackCategoriesCount = $company->feedbackCategories()->count();
                $templateCount = \App\Models\SectorFeedbackTemplate::where('sector', $company->sector)
                    ->where('status', true)
                    ->count();

                // Check if any template-based categories are missing Swahili translations
                $feedbackMissingSwahili = $company->feedbackCategories()
                    ->whereNotNull('category_key')
                    ->whereNull('name_sw')
                    ->exists();

                // Sync if sector changed, no categories exist, categories are incomplete, or translations missing
                if ($sectorChanged || $existingFeedbackCategoriesCount === 0 || $existingFeedbackCategoriesCount < $templateCount || $feedbackMissingSwahili) {
                    $feedbackSyncResult = $feedbackCategoryService->syncCategoriesFromSector($company);
                }
            }

            // Auto-sync departments if:
            // 1. Sector changed (from one value to another)
            // 2. Sector exists but company has no departments (initial sync)
            // 3. Company has fewer departments than templates (partial sync needed)
            $departmentSyncResult = null;
            $departmentService = new DepartmentTemplateService();

            if ($company->sector) {
                $existingDepartmentsCount = $company->departments()->count();
                $departmentTemplateCount = \App\Models\SectorDepartmentTemplate::where('sector', $company->sector)
                    ->where('status', true)
                    ->count();

                // Sync if sector changed, no departments exist, or departments are incomplete
                if ($sectorChanged || $existingDepartmentsCount === 0 || $existingDepartmentsCount < $departmentTemplateCount) {
                    $departmentSyncResult = $departmentService->syncDepartmentsFromSector($company);
                }
            }

            // Load the subscription plan relationship
            $company->load('subscriptionPlan');

            $response = [
                'success' => true,
                'message' => 'Company updated successfully',
                'data' => [
                    'company' => $company->fresh(['subscriptionPlan'])
                ]
            ];

            // Include sync result if categories were synced
            if ($syncResult) {
                $response['data']['category_sync'] = $syncResult;
            }

            // Include feedback sync result if categories were synced
            if ($feedbackSyncResult) {
                $response['data']['feedback_category_sync'] = $feedbackSyncResult;
            }

            // Include department sync result if departments were synced
            if ($departmentSyncResult) {
                $response['data']['department_sync'] = $departmentSyncResult;
            }

            return response()->json($response);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update company',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified company (soft delete).
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $company = Company::findOrFail($id);

            // Check if company has active branches or users
            $activeBranches = $company->branches()->where('status', true)->count();
            $activeUsers = $company->users()->where('status', 'active')->count();

            if ($activeBranches > 0 || $activeUsers > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete company with active branches or users',
                    'details' => [
                        'active_branches' => $activeBranches,
                        'active_users' => $activeUsers
                    ]
                ], 422);
            }

            // Delete logo file if exists
            if ($company->logo && Storage::disk('public')->exists($company->logo)) {
                Storage::disk('public')->delete($company->logo);
            }

            $company->delete();

            return response()->json([
                'success' => true,
                'message' => 'Company deleted successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete company',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reassign (delete and recreate) incident categories from sector templates.
     */
    public function reassignIncidentCategories(string $id): JsonResponse
    {
        try {
            $company = Company::findOrFail($id);

            if (!$company->sector) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company must have a sector assigned to reassign categories'
                ], 422);
            }

            // Get templates for this sector
            $templates = \App\Models\SectorIncidentTemplate::where('sector', $company->sector)
                ->where('status', true)
                ->get();

            $templateNames = $templates->pluck('category_name')
                ->merge($templates->pluck('subcategory_name'))
                ->filter()
                ->unique()
                ->values();

            $createdCategories = [];
            $deletedCount = 0;

            // First, delete ALL existing incident categories BEFORE transaction
            // Get all records including soft-deleted ones
            $existingCategories = \App\Models\IncidentCategory::withTrashed()
                ->where('company_id', $company->id)
                ->get();

            foreach ($existingCategories as $category) {
                $category->forceDelete();
                $deletedCount++;
            }

            // Now wrap creation in a transaction for atomicity
            DB::transaction(function () use ($company, $templates, &$createdCategories) {
                // Create fresh categories from sector templates
                $categoryMap = [];

                foreach ($templates as $template) {
                    if (!$template->subcategory_name) {
                        continue;
                    }

                    $parentCategoryId = $categoryMap[$template->category_key] ?? null;

                    if (!$parentCategoryId) {
                        $parentCategory = \App\Models\IncidentCategory::create([
                            'company_id' => $company->id,
                            'parent_id' => null,
                            'name' => $template->category_name,
                            'name_sw' => $template->category_name_sw,
                            'category_key' => $template->category_key,
                            'status' => true,
                            'description' => $template->description ?? "Category for {$template->category_name}",
                            'description_sw' => $template->description_sw,
                            'sort_order' => $template->sort_order,
                        ]);

                        $categoryMap[$template->category_key] = $parentCategory->id;
                        $parentCategoryId = $parentCategory->id;
                        $createdCategories[] = $parentCategory->toArray();
                    }

                    $subcategory = \App\Models\IncidentCategory::create([
                        'company_id' => $company->id,
                        'parent_id' => $parentCategoryId,
                        'name' => $template->subcategory_name,
                        'name_sw' => $template->subcategory_name_sw,
                        'category_key' => $template->category_key,
                        'status' => true,
                        'description' => null,
                        'sort_order' => $template->sort_order,
                    ]);

                    $createdCategories[] = $subcategory->toArray();
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Incident categories reassigned successfully',
                'data' => [
                    'deleted' => $deletedCount,
                    'created' => count($createdCategories),
                    'details' => [
                        'created' => $createdCategories,
                        'sector' => $company->sector
                    ]
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
                'message' => 'Failed to reassign incident categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reassign (delete and recreate) feedback categories from sector templates.
     */
    public function reassignFeedbackCategories(string $id): JsonResponse
    {
        try {
            $company = Company::findOrFail($id);

            if (!$company->sector) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company must have a sector assigned to reassign categories'
                ], 422);
            }

            // Get templates for this sector
            $templates = \App\Models\SectorFeedbackTemplate::where('sector', $company->sector)
                ->where('status', true)
                ->get();

            $templateNames = $templates->pluck('category_name')
                ->filter()
                ->unique()
                ->values();

            $createdCategories = [];
            $deletedCount = 0;

            // First, delete ALL existing feedback categories BEFORE transaction
            // Get all records including soft-deleted ones
            $existingCategories = \App\Models\FeedbackCategory::withTrashed()
                ->where('company_id', $company->id)
                ->get();

            foreach ($existingCategories as $category) {
                $category->forceDelete();
                $deletedCount++;
            }

            // Now wrap creation in a transaction for atomicity
            DB::transaction(function () use ($company, $templates, &$createdCategories) {
                // Group templates by category_name to avoid duplicates
                $groupedTemplates = $templates->groupBy('category_name');

                foreach ($groupedTemplates as $categoryName => $categoryTemplates) {
                    // Get the first template for this category (for main category data)
                    $mainTemplate = $categoryTemplates->first();

                    // Create the parent category once
                    $parentCategory = \App\Models\FeedbackCategory::create([
                        'company_id' => $company->id,
                        'name' => $mainTemplate->category_name,
                        'name_sw' => $mainTemplate->category_name_sw,
                        'description' => $mainTemplate->description,
                        'description_sw' => $mainTemplate->description_sw,
                        'category_key' => $mainTemplate->category_key,
                        'sort_order' => $categoryTemplates->min('sort_order'), // Use lowest sort order
                        'status' => true,
                    ]);

                    $createdCategories[] = $parentCategory->toArray();
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Feedback categories reassigned successfully',
                'data' => [
                    'deleted' => $deletedCount,
                    'created' => count($createdCategories),
                    'details' => [
                        'created' => $createdCategories,
                        'sector' => $company->sector
                    ]
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
                'message' => 'Failed to reassign feedback categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reassign (delete and recreate) departments from sector templates.
     */
    public function reassignDepartments(string $id): JsonResponse
    {
        try {
            $company = Company::findOrFail($id);

            if (!$company->sector) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company must have a sector assigned to reassign departments'
                ], 422);
            }

            // Delete all template-based departments (keep custom ones)
            $deletedCount = $company->departments()
                ->whereNotNull('department_key')
                ->delete();

            // Create fresh departments from sector templates
            $departmentService = new DepartmentTemplateService();
            $result = $departmentService->createDepartmentsFromSector($company);

            return response()->json([
                'success' => true,
                'message' => 'Departments reassigned successfully',
                'data' => [
                    'deleted' => $deletedCount,
                    'created' => count($result['created'] ?? []),
                    'details' => $result
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
                'message' => 'Failed to reassign departments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reassign all (incident categories, feedback categories, and departments) from sector templates.
     */
    public function reassignAll(string $id): JsonResponse
    {
        try {
            $company = Company::findOrFail($id);

            if (!$company->sector) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company must have a sector assigned to reassign resources'
                ], 422);
            }

            $results = [];

            // Reassign incident categories
            $deletedIncidents = $company->incidentCategories()
                ->whereNotNull('category_key')
                ->delete();
            $incidentService = new IncidentCategoryService();
            $incidentResult = $incidentService->createCategoriesFromSector($company);
            $results['incident_categories'] = [
                'deleted' => $deletedIncidents,
                'created' => count($incidentResult['created'] ?? []),
            ];

            // Reassign feedback categories
            $deletedFeedback = $company->feedbackCategories()
                ->whereNotNull('category_key')
                ->delete();
            $feedbackService = new FeedbackCategoryService();
            $feedbackResult = $feedbackService->createCategoriesFromSector($company);
            $results['feedback_categories'] = [
                'deleted' => $deletedFeedback,
                'created' => count($feedbackResult['created'] ?? []),
            ];

            // Reassign departments
            $deletedDepartments = $company->departments()
                ->whereNotNull('department_key')
                ->delete();
            $departmentService = new DepartmentTemplateService();
            $departmentResult = $departmentService->createDepartmentsFromSector($company);
            $results['departments'] = [
                'deleted' => $deletedDepartments,
                'created' => count($departmentResult['created'] ?? []),
            ];

            return response()->json([
                'success' => true,
                'message' => 'All resources reassigned successfully from sector templates',
                'data' => $results
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reassign resources',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get company statistics dashboard.
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total_companies' => Company::count(),
                'active_companies' => Company::where('status', true)->count(),
                'inactive_companies' => Company::where('status', false)->count(),
                'companies_by_plan' => Company::selectRaw('plan, count(*) as count')
                    ->groupBy('plan')
                    ->pluck('count', 'plan'),
                'recent_companies' => Company::latest()
                    ->take(5)
                    ->get(['id', 'name', 'email', 'plan', 'status', 'created_at']),
                'monthly_growth' => Company::selectRaw('DATE_TRUNC(\'month\', created_at) as month, count(*) as count')
                    ->where('created_at', '>=', now()->subMonths(12))
                    ->groupBy('month')
                    ->orderBy('month')
                    ->get()
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
     * Public API to get companies for frontend use (no authentication required).
     * Returns only active companies with minimal information.
     */
    public function publicIndex(Request $request): JsonResponse
    {
        try {
            $query = Company::where('status', true); // Only active companies

            // Optional search functionality
            if ($request->has('search') && $request->search !== '') {
                $search = $request->search;
                $query->where('name', 'ilike', "%{$search}%");
            }

            // Filter by sector if provided
            if ($request->has('sector') && $request->sector !== '') {
                $query->where('sector', $request->sector);
            }

            // Get companies with basic fields (avoiding potentially missing fields)
            $companies = $query->select([
                'id',
                'name',
                'email',
                'contact',
                'address',
                'sector',
                'logo'
            ])
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Companies retrieved successfully.',
                'data' => $companies
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve companies.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all available sectors for companies.
     */
    public function sectors(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Company::SECTORS
        ]);
    }
}
