<?php

/**
 * Debug script to check why sync isn't triggering
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Company;
use App\Models\IncidentCategory;

echo "=== Debug Sync Trigger Logic ===\n\n";

$company = Company::where('name', 'LIKE', '%Technoguru%')->first();

echo "Company: {$company->name}\n";
echo "Sector: {$company->sector}\n\n";

// Manually check the condition
echo "1. Checking sync trigger conditions...\n";

$existingCategoriesCount = $company->incidentCategories()->count();
echo "   Existing incident categories: {$existingCategoriesCount}\n";

$incidentTemplateCount = \App\Models\SectorIncidentTemplate::where('sector', $company->sector)
    ->where('status', true)
    ->count();
echo "   Template count: {$incidentTemplateCount}\n";

$categoriesMissingSwahili = $company->incidentCategories()
    ->whereNotNull('category_key')
    ->whereNull('name_sw')
    ->exists();
echo "   Categories missing Swahili: " . ($categoriesMissingSwahili ? 'YES' : 'NO') . "\n";

$categoriesMissingSwahiliCount = $company->incidentCategories()
    ->whereNotNull('category_key')
    ->whereNull('name_sw')
    ->count();
echo "   Count of categories missing Swahili: {$categoriesMissingSwahiliCount}\n";

echo "\n2. Evaluating trigger condition...\n";
$sectorChanged = false; // Simulating no sector change
echo "   Sector changed: " . ($sectorChanged ? 'YES' : 'NO') . "\n";
echo "   Categories count === 0: " . ($existingCategoriesCount === 0 ? 'YES' : 'NO') . "\n";
echo "   Categories incomplete: " . ($existingCategoriesCount < $incidentTemplateCount ? 'YES' : 'NO') . "\n";
echo "   Missing Swahili translations: " . ($categoriesMissingSwahili ? 'YES' : 'NO') . "\n";

$shouldSync = $sectorChanged || $existingCategoriesCount === 0 || $existingCategoriesCount < $incidentTemplateCount || $categoriesMissingSwahili;
echo "\n   Should sync: " . ($shouldSync ? 'YES ✅' : 'NO ❌') . "\n";

if ($shouldSync) {
    echo "\n3. Manually triggering sync...\n";
    $categoryService = new \App\Services\IncidentCategoryService();
    $syncResult = $categoryService->syncCategoriesFromSector($company);
    echo "   Result: {$syncResult['message']}\n";

    if (count($syncResult['updated']) > 0) {
        echo "   Updated categories:\n";
        foreach ($syncResult['updated'] as $update) {
            echo "      - {$update['name']}\n";
        }
    }

    // Check if the missing ones are now filled
    $stillMissing = $company->incidentCategories()
        ->whereNotNull('category_key')
        ->whereNull('name_sw')
        ->count();
    echo "\n   Categories still missing Swahili after sync: {$stillMissing}\n";
}

echo "\n=== Debug Complete ===\n";
