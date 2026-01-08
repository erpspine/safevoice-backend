<?php

namespace App\Services;

use App\Models\Company;
use App\Models\IncidentCategory;
use App\Models\SectorIncidentTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IncidentCategoryService
{
    /**
     * Sync incident categories for a company based on its sector.
     * - Adds new categories from templates that don't exist
     * - Removes template-based categories that no longer exist in templates
     * - Preserves custom categories (those without category_key or with modified names)
     */
    public function syncCategoriesFromSector(Company $company): array
    {
        if (!$company->sector) {
            return [
                'added' => [],
                'removed' => [],
                'preserved' => [],
                'message' => 'Company has no sector assigned',
            ];
        }

        $templates = SectorIncidentTemplate::where('sector', $company->sector)
            ->where('status', true)
            ->orderBy('sort_order')
            ->orderBy('category_name')
            ->orderBy('subcategory_name')
            ->get();

        if ($templates->isEmpty()) {
            Log::info("No incident templates found for sector: {$company->sector}");
            return [
                'added' => [],
                'removed' => [],
                'preserved' => [],
                'message' => "No templates found for sector: {$company->sector}",
            ];
        }

        $result = [
            'added' => [],
            'updated' => [],
            'removed' => [],
            'preserved' => [],
        ];

        DB::transaction(function () use ($company, $templates, &$result) {
            // Get existing categories for this company
            $existingCategories = IncidentCategory::where('company_id', $company->id)
                ->withTrashed() // Include soft-deleted
                ->get()
                ->keyBy(function ($cat) {
                    // Create a unique key: category_key + name (for subcategories)
                    return $cat->category_key . '::' . $cat->name;
                });

            // Build template structure
            $templateStructure = $this->buildTemplateStructure($templates);

            // Track what should exist from templates
            $templateKeys = [];
            $categoryMap = []; // Maps category_key to parent category ID

            // First pass: Handle parent categories
            foreach ($templateStructure as $categoryKey => $categoryData) {
                $parentKey = $categoryKey . '::' . $categoryData['name'];
                $templateKeys[] = $parentKey;

                $existingParent = $existingCategories->get($parentKey);

                if ($existingParent) {
                    // Restore if soft-deleted
                    if ($existingParent->trashed()) {
                        $existingParent->restore();
                        $result['added'][] = [
                            'type' => 'parent',
                            'name' => $categoryData['name'],
                            'action' => 'restored',
                        ];
                    }

                    // Update existing category with latest template content
                    $updatedFields = [];
                    if ($existingParent->name !== $categoryData['name']) {
                        $updatedFields['name'] = $categoryData['name'];
                    }
                    if ($existingParent->name_sw !== ($categoryData['name_sw'] ?? null)) {
                        $updatedFields['name_sw'] = $categoryData['name_sw'] ?? null;
                    }
                    $templateDescription = $categoryData['description'] ?? "Category for {$categoryData['name']}";
                    if ($existingParent->description !== $templateDescription) {
                        $updatedFields['description'] = $templateDescription;
                    }
                    if ($existingParent->description_sw !== ($categoryData['description_sw'] ?? null)) {
                        $updatedFields['description_sw'] = $categoryData['description_sw'] ?? null;
                    }
                    if ($existingParent->sort_order !== $categoryData['sort_order']) {
                        $updatedFields['sort_order'] = $categoryData['sort_order'];
                    }

                    if (!empty($updatedFields)) {
                        $existingParent->update($updatedFields);
                        $result['updated'][] = [
                            'type' => 'parent',
                            'name' => $categoryData['name'],
                            'fields' => array_keys($updatedFields),
                        ];
                    } else {
                        $result['preserved'][] = [
                            'type' => 'parent',
                            'name' => $categoryData['name'],
                        ];
                    }

                    $categoryMap[$categoryKey] = $existingParent->id;
                } else {
                    // Create new parent category
                    $newParent = IncidentCategory::create([
                        'company_id' => $company->id,
                        'parent_id' => null,
                        'name' => $categoryData['name'],
                        'name_sw' => $categoryData['name_sw'] ?? null,
                        'category_key' => $categoryKey,
                        'status' => true,
                        'description' => $categoryData['description'] ?? "Category for {$categoryData['name']}",
                        'description_sw' => $categoryData['description_sw'] ?? null,
                        'sort_order' => $categoryData['sort_order'],
                    ]);
                    $categoryMap[$categoryKey] = $newParent->id;
                    $result['added'][] = [
                        'type' => 'parent',
                        'name' => $categoryData['name'],
                        'action' => 'created',
                    ];
                }

                // Second pass: Handle subcategories
                foreach ($categoryData['subcategories'] as $subcategoryData) {
                    $subKey = $categoryKey . '::' . $subcategoryData['name'];
                    $templateKeys[] = $subKey;

                    $existingSub = $existingCategories->get($subKey);

                    if ($existingSub) {
                        // Restore if soft-deleted
                        if ($existingSub->trashed()) {
                            $existingSub->restore();
                            $result['added'][] = [
                                'type' => 'subcategory',
                                'name' => $subcategoryData['name'],
                                'parent' => $categoryData['name'],
                                'action' => 'restored',
                            ];
                        }

                        // Update existing subcategory with latest template content
                        $updatedFields = [];
                        if ($existingSub->parent_id !== $categoryMap[$categoryKey]) {
                            $updatedFields['parent_id'] = $categoryMap[$categoryKey];
                        }
                        if ($existingSub->name !== $subcategoryData['name']) {
                            $updatedFields['name'] = $subcategoryData['name'];
                        }
                        if ($existingSub->name_sw !== ($subcategoryData['name_sw'] ?? null)) {
                            $updatedFields['name_sw'] = $subcategoryData['name_sw'] ?? null;
                        }
                        if ($existingSub->sort_order !== $subcategoryData['sort_order']) {
                            $updatedFields['sort_order'] = $subcategoryData['sort_order'];
                        }

                        if (!empty($updatedFields)) {
                            $existingSub->update($updatedFields);
                            $result['updated'][] = [
                                'type' => 'subcategory',
                                'name' => $subcategoryData['name'],
                                'parent' => $categoryData['name'],
                                'fields' => array_keys($updatedFields),
                            ];
                        } else {
                            $result['preserved'][] = [
                                'type' => 'subcategory',
                                'name' => $subcategoryData['name'],
                                'parent' => $categoryData['name'],
                            ];
                        }
                    } else {
                        // Create new subcategory
                        IncidentCategory::create([
                            'company_id' => $company->id,
                            'parent_id' => $categoryMap[$categoryKey],
                            'name' => $subcategoryData['name'],
                            'name_sw' => $subcategoryData['name_sw'] ?? null,
                            'category_key' => $categoryKey,
                            'status' => true,
                            'description' => null,
                            'sort_order' => $subcategoryData['sort_order'],
                        ]);
                        $result['added'][] = [
                            'type' => 'subcategory',
                            'name' => $subcategoryData['name'],
                            'parent' => $categoryData['name'],
                            'action' => 'created',
                        ];
                    }
                }
            }

            // Remove template-based categories that no longer exist in templates
            // Only remove categories that have a category_key (template-based)
            foreach ($existingCategories as $key => $category) {
                // Skip custom categories (no category_key)
                if (empty($category->category_key)) {
                    $result['preserved'][] = [
                        'type' => $category->parent_id ? 'subcategory' : 'parent',
                        'name' => $category->name,
                        'reason' => 'custom_category',
                    ];
                    continue;
                }

                // Skip already soft-deleted
                if ($category->trashed()) {
                    continue;
                }

                // If this template-based category is not in current templates, soft-delete it
                if (!in_array($key, $templateKeys)) {
                    $category->delete(); // Soft delete
                    $result['removed'][] = [
                        'type' => $category->parent_id ? 'subcategory' : 'parent',
                        'name' => $category->name,
                        'category_key' => $category->category_key,
                    ];
                }
            }
        });

        $addedCount = count($result['added']);
        $updatedCount = count($result['updated']);
        $removedCount = count($result['removed']);
        $preservedCount = count($result['preserved']);

        Log::info("Synced incident categories for {$company->name}: {$addedCount} added, {$updatedCount} updated, {$removedCount} removed, {$preservedCount} preserved");

        $result['message'] = "Sync complete: {$addedCount} added, {$updatedCount} updated, {$removedCount} removed, {$preservedCount} preserved";

        return $result;
    }

    /**
     * Build a structured array from templates for easier comparison.
     */
    private function buildTemplateStructure($templates): array
    {
        $structure = [];

        foreach ($templates as $template) {
            $key = $template->category_key;

            if (!isset($structure[$key])) {
                $structure[$key] = [
                    'name' => $template->category_name,
                    'name_sw' => $template->category_name_sw,
                    'description' => $template->description,
                    'description_sw' => $template->description_sw,
                    'sort_order' => $template->sort_order,
                    'subcategories' => [],
                ];
            }

            if ($template->subcategory_name) {
                $structure[$key]['subcategories'][] = [
                    'name' => $template->subcategory_name,
                    'name_sw' => $template->subcategory_name_sw,
                    'sort_order' => $template->sort_order,
                ];
            }
        }

        return $structure;
    }

    /**
     * Create incident categories for a company based on its sector.
     * Use this for initial creation only.
     */
    public function createCategoriesFromSector(Company $company): array
    {
        if (!$company->sector) {
            return [];
        }

        // Check if company already has categories
        $existingCount = IncidentCategory::where('company_id', $company->id)->count();

        if ($existingCount > 0) {
            // Use sync instead to avoid duplicates
            $syncResult = $this->syncCategoriesFromSector($company);
            return $syncResult['added'];
        }

        $templates = SectorIncidentTemplate::where('sector', $company->sector)
            ->where('status', true)
            ->orderBy('sort_order')
            ->orderBy('category_name')
            ->orderBy('subcategory_name')
            ->get();

        if ($templates->isEmpty()) {
            Log::info("No incident templates found for sector: {$company->sector}");
            return [];
        }

        $createdCategories = [];

        DB::transaction(function () use ($company, $templates, &$createdCategories) {
            $categoryMap = []; // Maps category_key to created parent category ID

            foreach ($templates as $template) {
                // Skip parent-only entries (no subcategory)
                if (!$template->subcategory_name) {
                    continue;
                }

                // Get or create parent category
                $parentCategoryId = $categoryMap[$template->category_key] ?? null;

                if (!$parentCategoryId) {
                    // Create parent category
                    $parentCategory = IncidentCategory::create([
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
                    $createdCategories[] = $parentCategory;
                }

                // Create subcategory
                $subcategory = IncidentCategory::create([
                    'company_id' => $company->id,
                    'parent_id' => $parentCategoryId,
                    'name' => $template->subcategory_name,
                    'name_sw' => $template->subcategory_name_sw,
                    'category_key' => $template->category_key,
                    'status' => true,
                    'description' => null,
                    'sort_order' => $template->sort_order,
                ]);

                $createdCategories[] = $subcategory;
            }
        });

        Log::info("Created " . count($createdCategories) . " incident categories for company: {$company->name}");

        return $createdCategories;
    }

    /**
     * Delete all incident categories for a company (hard delete).
     */
    public function deleteCategoriesForCompany(Company $company): int
    {
        return IncidentCategory::where('company_id', $company->id)->forceDelete();
    }

    /**
     * Soft delete all template-based categories for a company.
     * Preserves custom categories.
     */
    public function softDeleteTemplateCategoriesForCompany(Company $company): int
    {
        return IncidentCategory::where('company_id', $company->id)
            ->whereNotNull('category_key')
            ->delete();
    }

    /**
     * Recreate incident categories for a company when sector changes.
     * This is a destructive operation - use syncCategoriesFromSector for non-destructive updates.
     */
    public function recreateCategoriesForCompany(Company $company): array
    {
        // Soft delete template-based categories (preserves custom ones)
        $this->softDeleteTemplateCategoriesForCompany($company);

        // Create new categories from the new sector
        return $this->createCategoriesFromSector($company);
    }

    /**
     * Get categories with subcategories for a company.
     */
    public function getCategoriesWithSubcategories(string $companyId): array
    {
        return IncidentCategory::where('company_id', $companyId)
            ->whereNull('parent_id')
            ->with(['subcategories' => function ($query) {
                $query->where('status', true)->orderBy('sort_order')->orderBy('name');
            }])
            ->where('status', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    /**
     * Sync categories for all companies with a specific sector.
     * Useful when templates are updated.
     */
    public function syncAllCompaniesForSector(string $sector): array
    {
        $companies = Company::where('sector', $sector)
            ->where('status', true)
            ->get();

        $results = [];

        foreach ($companies as $company) {
            $results[$company->id] = [
                'company' => $company->name,
                'result' => $this->syncCategoriesFromSector($company),
            ];
        }

        return $results;
    }
}
