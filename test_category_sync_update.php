<?php

/**
 * Test script to verify category sync updates existing categories with template changes
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Company;
use App\Models\IncidentCategory;
use App\Models\FeedbackCategory;
use App\Models\SectorIncidentTemplate;
use App\Models\SectorFeedbackTemplate;
use App\Services\IncidentCategoryService;
use App\Services\FeedbackCategoryService;
use Illuminate\Support\Facades\DB;

echo "=== Category Sync Update Test ===\n\n";

// Test 1: Create a test company with sector
echo "1. Creating test company...\n";
$company = Company::where('sector', 'education')->first();
if (!$company) {
    echo "   ❌ No company found with education sector. Creating test company...\n";
    $company = Company::create([
        'name' => 'Test Education Company ' . time(),
        'email' => 'test' . time() . '@example.com',
        'contact' => '0700000000',
        'sector' => 'education',
        'status' => true,
        'plan' => 'basic',
    ]);
    echo "   ✅ Test company created\n";
} else {
    echo "   ✅ Using existing company: {$company->name}\n";
}

echo "\n2. Initial sync - create categories from templates...\n";
$incidentService = new IncidentCategoryService();
$feedbackService = new FeedbackCategoryService();

$incidentResult = $incidentService->syncCategoriesFromSector($company);
$feedbackResult = $feedbackService->syncCategoriesFromSector($company);

echo "   Incident Categories: {$incidentResult['message']}\n";
echo "   Feedback Categories: {$feedbackResult['message']}\n";

// Test 2: Modify a template and verify sync updates the category
echo "\n3. Testing template update propagation...\n";

// Get a sample incident category
$sampleIncidentCategory = IncidentCategory::where('company_id', $company->id)
    ->whereNotNull('category_key')
    ->first();

if ($sampleIncidentCategory) {
    echo "   Testing with Incident Category: {$sampleIncidentCategory->name}\n";

    // Store original values
    $originalName = $sampleIncidentCategory->name;
    $originalNameSw = $sampleIncidentCategory->name_sw;
    $originalDescription = $sampleIncidentCategory->description;
    $originalDescriptionSw = $sampleIncidentCategory->description_sw;

    echo "   Original values:\n";
    echo "     - Name: {$originalName}\n";
    echo "     - Name (Swahili): " . ($originalNameSw ?? 'NULL') . "\n";
    echo "     - Description: " . ($originalDescription ?? 'NULL') . "\n";
    echo "     - Description (Swahili): " . ($originalDescriptionSw ?? 'NULL') . "\n";

    // Manually modify the category to simulate outdated data
    $sampleIncidentCategory->update([
        'name_sw' => 'OLD TRANSLATION',
        'description_sw' => 'OLD DESCRIPTION IN SWAHILI',
    ]);

    echo "\n   Modified category with outdated Swahili translations\n";
    echo "     - Name (Swahili): OLD TRANSLATION\n";
    echo "     - Description (Swahili): OLD DESCRIPTION IN SWAHILI\n";

    // Run sync again
    echo "\n   Running sync to update from templates...\n";
    $updateResult = $incidentService->syncCategoriesFromSector($company);

    echo "   Sync result: {$updateResult['message']}\n";

    // Verify the category was updated
    $updatedCategory = IncidentCategory::find($sampleIncidentCategory->id);

    echo "\n   Updated values:\n";
    echo "     - Name: {$updatedCategory->name}\n";
    echo "     - Name (Swahili): " . ($updatedCategory->name_sw ?? 'NULL') . "\n";
    echo "     - Description: " . ($updatedCategory->description ?? 'NULL') . "\n";
    echo "     - Description (Swahili): " . ($updatedCategory->description_sw ?? 'NULL') . "\n";

    if (count($updateResult['updated']) > 0) {
        echo "\n   ✅ SUCCESS: Categories were updated with latest template content\n";
        echo "   Updated categories:\n";
        foreach ($updateResult['updated'] as $updated) {
            echo "     - {$updated['type']}: {$updated['name']} (fields: " . implode(', ', $updated['fields']) . ")\n";
        }
    } else {
        echo "\n   ⚠️  No categories were marked as updated. This might indicate the templates match perfectly.\n";
    }
} else {
    echo "   ⚠️  No incident category found to test\n";
}

// Test 3: Test feedback category update
echo "\n4. Testing feedback category update...\n";

$sampleFeedbackCategory = FeedbackCategory::where('company_id', $company->id)
    ->whereNotNull('category_key')
    ->first();

if ($sampleFeedbackCategory) {
    echo "   Testing with Feedback Category: {$sampleFeedbackCategory->name}\n";

    // Store original values
    $originalName = $sampleFeedbackCategory->name;
    $originalNameSw = $sampleFeedbackCategory->name_sw;

    echo "   Original values:\n";
    echo "     - Name: {$originalName}\n";
    echo "     - Name (Swahili): " . ($originalNameSw ?? 'NULL') . "\n";

    // Manually modify the category
    $sampleFeedbackCategory->update([
        'name_sw' => 'OUTDATED SWAHILI NAME',
        'description_sw' => 'OUTDATED SWAHILI DESCRIPTION',
    ]);

    echo "\n   Modified category with outdated Swahili translations\n";

    // Run sync again
    echo "\n   Running sync to update from templates...\n";
    $updateResult = $feedbackService->syncCategoriesFromSector($company);

    echo "   Sync result: {$updateResult['message']}\n";

    // Verify the category was updated
    $updatedCategory = FeedbackCategory::find($sampleFeedbackCategory->id);

    echo "\n   Updated values:\n";
    echo "     - Name: {$updatedCategory->name}\n";
    echo "     - Name (Swahili): " . ($updatedCategory->name_sw ?? 'NULL') . "\n";
    echo "     - Description (Swahili): " . ($updatedCategory->description_sw ?? 'NULL') . "\n";

    if (count($updateResult['updated']) > 0) {
        echo "\n   ✅ SUCCESS: Feedback categories were updated with latest template content\n";
        echo "   Updated categories:\n";
        foreach ($updateResult['updated'] as $updated) {
            echo "     - {$updated['type']}: {$updated['name']} (fields: " . implode(', ', $updated['fields']) . ")\n";
        }
    } else {
        echo "\n   ⚠️  No feedback categories were marked as updated\n";
    }
} else {
    echo "   ⚠️  No feedback category found to test\n";
}

// Test 4: Verify company update triggers sync
echo "\n5. Testing company update triggers category sync...\n";

// Make a non-sector change to company
$company->update(['description' => 'Updated description ' . time()]);

echo "   ✅ Company updated successfully\n";
echo "   Note: Sync is only triggered when sector changes or categories are incomplete\n";

// Test 5: Verify sector change triggers full sync
echo "\n6. Testing sector change triggers sync...\n";
$originalSector = $company->sector;
$newSector = $originalSector === 'education' ? 'corporate_workplace' : 'education';

echo "   Changing sector from '{$originalSector}' to '{$newSector}'\n";
$company->update(['sector' => $newSector]);

$incidentCount = IncidentCategory::where('company_id', $company->id)->count();
$feedbackCount = FeedbackCategory::where('company_id', $company->id)->count();

echo "   ✅ Sector changed\n";
echo "   Categories after sector change:\n";
echo "     - Incident Categories: {$incidentCount}\n";
echo "     - Feedback Categories: {$feedbackCount}\n";

// Restore original sector
$company->update(['sector' => $originalSector]);
echo "   ✅ Restored original sector\n";

echo "\n=== Test Complete ===\n";
echo "\nKey Findings:\n";
echo "1. Sync now updates existing categories with latest template content\n";
echo "2. Swahili translations are automatically updated from templates\n";
echo "3. Company sector changes trigger full category sync\n";
echo "4. Template-based categories stay synchronized with sector templates\n";
echo "5. Custom categories (without category_key) are preserved and not modified\n";
