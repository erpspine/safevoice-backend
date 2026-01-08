<?php

namespace App\Services;

use App\Models\Company;
use App\Models\FeedbackCategory;
use App\Models\SectorFeedbackTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FeedbackCategoryService
{
    /**
     * Sync feedback categories for a company based on its sector.
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

        $templates = SectorFeedbackTemplate::where('sector', $company->sector)
            ->where('status', true)
            ->orderBy('sort_order')
            ->orderBy('category_name')
            ->orderBy('subcategory_name')
            ->get();

        if ($templates->isEmpty()) {
            Log::info("No feedback templates found for sector: {$company->sector}");
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
            $existingCategories = FeedbackCategory::where('company_id', $company->id)
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
                    $newParent = FeedbackCategory::create([
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
                        FeedbackCategory::create([
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

            // Third pass: Soft-delete template-based categories that are no longer in templates
            // Only remove categories that have a category_key (template-based)
            foreach ($existingCategories as $key => $category) {
                if ($category->category_key && !in_array($key, $templateKeys)) {
                    if (!$category->trashed()) {
                        $category->delete();
                        $result['removed'][] = [
                            'type' => $category->parent_id ? 'subcategory' : 'parent',
                            'name' => $category->name,
                            'action' => 'soft_deleted',
                        ];
                    }
                }
            }
        });

        $addedCount = count($result['added']);
        $updatedCount = count($result['updated']);
        $removedCount = count($result['removed']);
        $preservedCount = count($result['preserved']);

        $result['message'] = sprintf(
            'Sync completed: %d added, %d updated, %d removed, %d preserved',
            $addedCount,
            $updatedCount,
            $removedCount,
            $preservedCount
        );

        Log::info("Feedback categories synced for company {$company->id}: {$addedCount} added, {$updatedCount} updated, {$removedCount} removed, {$preservedCount} preserved", $result);

        return $result;
    }

    /**
     * Create feedback categories from sector templates for a new company.
     * This is used when a company is first created.
     */
    public function createCategoriesFromSector(Company $company): array
    {
        if (!$company->sector) {
            return [
                'created' => [],
                'message' => 'Company has no sector assigned',
            ];
        }

        $templates = SectorFeedbackTemplate::where('sector', $company->sector)
            ->where('status', true)
            ->orderBy('sort_order')
            ->orderBy('category_name')
            ->orderBy('subcategory_name')
            ->get();

        if ($templates->isEmpty()) {
            Log::info("No feedback templates found for sector: {$company->sector}");
            return [
                'created' => [],
                'message' => "No templates found for sector: {$company->sector}",
            ];
        }

        $result = ['created' => []];

        DB::transaction(function () use ($company, $templates, &$result) {
            $templateStructure = $this->buildTemplateStructure($templates);
            $categoryMap = [];

            foreach ($templateStructure as $categoryKey => $categoryData) {
                // Create parent category
                $parent = FeedbackCategory::create([
                    'company_id' => $company->id,
                    'parent_id' => null,
                    'name' => $categoryData['name'],
                    'name_sw' => $categoryData['name_sw'] ?? null,
                    'category_key' => $categoryKey,
                    'status' => true,
                    'description' => $categoryData['description'],
                    'description_sw' => $categoryData['description_sw'] ?? null,
                    'sort_order' => $categoryData['sort_order'],
                ]);

                $categoryMap[$categoryKey] = $parent->id;
                $result['created'][] = [
                    'type' => 'parent',
                    'name' => $categoryData['name'],
                    'id' => $parent->id,
                ];

                // Create subcategories
                foreach ($categoryData['subcategories'] as $subcategoryData) {
                    $subcategory = FeedbackCategory::create([
                        'company_id' => $company->id,
                        'parent_id' => $parent->id,
                        'name' => $subcategoryData['name'],
                        'name_sw' => $subcategoryData['name_sw'] ?? null,
                        'category_key' => $categoryKey,
                        'status' => true,
                        'description' => null,
                        'sort_order' => $subcategoryData['sort_order'],
                    ]);

                    $result['created'][] = [
                        'type' => 'subcategory',
                        'name' => $subcategoryData['name'],
                        'parent' => $categoryData['name'],
                        'id' => $subcategory->id,
                    ];
                }
            }
        });

        $result['message'] = sprintf('%d feedback categories created', count($result['created']));
        Log::info("Feedback categories created for company {$company->id}", $result);

        return $result;
    }

    /**
     * Build a structured array from templates for easier processing.
     */
    protected function buildTemplateStructure($templates): array
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
     * Update sector for a company and sync categories.
     */
    public function updateSectorAndSync(Company $company, string $newSector): array
    {
        $oldSector = $company->sector;

        if ($oldSector === $newSector) {
            return [
                'sector_changed' => false,
                'message' => 'Sector unchanged',
            ];
        }

        $company->update(['sector' => $newSector]);

        return [
            'sector_changed' => true,
            'old_sector' => $oldSector,
            'new_sector' => $newSector,
            'sync_result' => $this->syncCategoriesFromSector($company),
        ];
    }

    /**
     * Sync categories for all companies in a specific sector.
     * Useful when templates are updated.
     */
    public function syncAllCompaniesInSector(string $sector): array
    {
        $companies = Company::where('sector', $sector)->get();
        $results = [];

        foreach ($companies as $company) {
            $results[] = [
                'company_id' => $company->id,
                'company_name' => $company->name,
                'result' => $this->syncCategoriesFromSector($company),
            ];
        }

        return [
            'sector' => $sector,
            'companies_processed' => count($companies),
            'results' => $results,
        ];
    }

    /**
     * Reset categories for a company (delete all and recreate from templates).
     * This is a destructive operation - use syncCategoriesFromSector for non-destructive updates.
     */
    public function resetCategoriesFromSector(Company $company): array
    {
        if (!$company->sector) {
            return [
                'success' => false,
                'message' => 'Company has no sector assigned',
            ];
        }

        DB::transaction(function () use ($company) {
            // Force delete all existing categories (including soft-deleted)
            FeedbackCategory::where('company_id', $company->id)
                ->withTrashed()
                ->forceDelete();
        });

        // Recreate from templates
        $createResult = $this->createCategoriesFromSector($company);

        return [
            'success' => true,
            'message' => 'Categories reset from templates',
            'created' => $createResult['created'],
        ];
    }

    /**
     * Get categories grouped by parent for display.
     */
    public function getCategoriesGrouped(Company $company): array
    {
        $categories = FeedbackCategory::where('company_id', $company->id)
            ->whereNull('parent_id')
            ->with(['children' => function ($query) {
                $query->orderBy('sort_order')->orderBy('name');
            }])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $categories->map(function ($parent) {
            return [
                'id' => $parent->id,
                'name' => $parent->name,
                'category_key' => $parent->category_key,
                'status' => $parent->status,
                'description' => $parent->description,
                'subcategories' => $parent->children->map(function ($child) {
                    return [
                        'id' => $child->id,
                        'name' => $child->name,
                        'status' => $child->status,
                    ];
                }),
            ];
        })->toArray();
    }
}
