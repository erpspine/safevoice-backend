<?php

/**
 * Force sync all categories for Technoguru company
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Company;
use App\Services\IncidentCategoryService;
use App\Services\FeedbackCategoryService;

echo "=== Force Category Sync for Technoguru ===\n\n";

// Find Technoguru company
$company = Company::where('name', 'LIKE', '%Technoguru%')->first();

if (!$company) {
    echo "❌ Technoguru company not found\n";
    exit(1);
}

echo "Found company: {$company->name}\n";
echo "Sector: {$company->sector}\n\n";

// Force sync incident categories
echo "1. Syncing Incident Categories...\n";
$incidentService = new IncidentCategoryService();
$incidentResult = $incidentService->syncCategoriesFromSector($company);

echo "   Result: {$incidentResult['message']}\n";

if (count($incidentResult['updated']) > 0) {
    echo "\n   Updated categories:\n";
    foreach ($incidentResult['updated'] as $updated) {
        $fields = implode(', ', $updated['fields']);
        echo "      - {$updated['type']}: {$updated['name']} ({$fields})\n";
    }
}

// Force sync feedback categories
echo "\n2. Syncing Feedback Categories...\n";
$feedbackService = new FeedbackCategoryService();
$feedbackResult = $feedbackService->syncCategoriesFromSector($company);

echo "   Result: {$feedbackResult['message']}\n";

if (count($feedbackResult['updated']) > 0) {
    echo "\n   Updated categories:\n";
    foreach ($feedbackResult['updated'] as $updated) {
        $fields = implode(', ', $updated['fields']);
        echo "      - {$updated['type']}: {$updated['name']} ({$fields})\n";
    }
}

// Verify "Conflict of Interest" was updated
echo "\n3. Verifying 'Conflict of Interest' update...\n";
$conflictCategory = \App\Models\IncidentCategory::where('company_id', $company->id)
    ->where('name', 'Conflict of Interest')
    ->first();

if ($conflictCategory) {
    echo "   Name: {$conflictCategory->name}\n";
    echo "   Name (Swahili): " . ($conflictCategory->name_sw ?? 'NULL') . "\n";
    echo "   Description (Swahili): " . ($conflictCategory->description_sw ?? 'NULL') . "\n";

    if ($conflictCategory->name_sw === 'Mgongano wa Maslahi') {
        echo "   ✅ Successfully updated!\n";
    } else {
        echo "   ❌ Still not updated\n";
    }
}

// Count remaining categories without Swahili
$incidentsMissing = \App\Models\IncidentCategory::where('company_id', $company->id)
    ->whereNotNull('category_key')
    ->whereNull('name_sw')
    ->count();

$feedbackMissing = \App\Models\FeedbackCategory::where('company_id', $company->id)
    ->whereNotNull('category_key')
    ->whereNull('name_sw')
    ->count();

echo "\n4. Remaining categories without Swahili translations:\n";
echo "   Incident categories: {$incidentsMissing}\n";
echo "   Feedback categories: {$feedbackMissing}\n";

if ($incidentsMissing === 0 && $feedbackMissing === 0) {
    echo "\n   ✅ All template-based categories now have Swahili translations!\n";
} else {
    echo "\n   ⚠️  Some categories still missing Swahili translations\n";
}

echo "\n=== Sync Complete ===\n";
