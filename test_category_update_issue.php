<?php

/**
 * Diagnostic script to identify why some categories aren't updating
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Company;
use App\Models\IncidentCategory;
use App\Models\FeedbackCategory;
use App\Models\SectorIncidentTemplate;
use App\Models\SectorFeedbackTemplate;

echo "=== Category Update Diagnostic ===\n\n";

// Find Technoguru company
$company = Company::where('name', 'LIKE', '%Technoguru%')->first();

if (!$company) {
    echo "❌ Technoguru company not found\n";
    exit(1);
}

echo "Found company: {$company->name}\n";
echo "Sector: {$company->sector}\n\n";

// Check "Conflict of Interest" in incident categories
echo "1. Checking 'Conflict of Interest' incident category...\n";

$conflictCategory = IncidentCategory::where('company_id', $company->id)
    ->where('name', 'LIKE', '%Conflict%')
    ->first();

if ($conflictCategory) {
    echo "   Found category:\n";
    echo "   - ID: {$conflictCategory->id}\n";
    echo "   - Name: {$conflictCategory->name}\n";
    echo "   - Name (Swahili): " . ($conflictCategory->name_sw ?? 'NULL') . "\n";
    echo "   - Description: " . ($conflictCategory->description ?? 'NULL') . "\n";
    echo "   - Description (Swahili): " . ($conflictCategory->description_sw ?? 'NULL') . "\n";
    echo "   - Category Key: " . ($conflictCategory->category_key ?? 'NULL') . "\n";

    // Check what the template says
    if ($company->sector && $conflictCategory->category_key) {
        echo "\n2. Checking template for this category...\n";

        $template = SectorIncidentTemplate::where('sector', $company->sector)
            ->where('category_name', 'LIKE', '%Conflict%')
            ->first();

        if ($template) {
            echo "   Found template:\n";
            echo "   - Category Name: {$template->category_name}\n";
            echo "   - Category Name (Swahili): " . ($template->category_name_sw ?? 'NULL') . "\n";
            echo "   - Subcategory: " . ($template->subcategory_name ?? 'NULL') . "\n";
            echo "   - Subcategory (Swahili): " . ($template->subcategory_name_sw ?? 'NULL') . "\n";
            echo "   - Description (Swahili): " . ($template->description_sw ?? 'NULL') . "\n";

            echo "\n3. Comparison analysis:\n";

            // Check if names match
            if ($conflictCategory->name !== $template->category_name) {
                echo "   ⚠️  Name mismatch:\n";
                echo "      Category: '{$conflictCategory->name}'\n";
                echo "      Template: '{$template->category_name}'\n";
            } else {
                echo "   ✅ Names match\n";
            }

            // Check Swahili name
            if ($conflictCategory->name_sw !== ($template->category_name_sw ?? null)) {
                echo "   ⚠️  Swahili name differs:\n";
                echo "      Category: '" . ($conflictCategory->name_sw ?? 'NULL') . "'\n";
                echo "      Template: '" . ($template->category_name_sw ?? 'NULL') . "'\n";
                echo "      Should update: YES\n";
            } else {
                echo "   ✅ Swahili names match\n";
            }

            // Check description
            $expectedDescription = "Category for {$template->category_name}";
            if ($conflictCategory->description !== $expectedDescription) {
                echo "   ⚠️  Description differs:\n";
                echo "      Category: '" . ($conflictCategory->description ?? 'NULL') . "'\n";
                echo "      Expected: '{$expectedDescription}'\n";
            } else {
                echo "   ✅ Descriptions match\n";
            }

            // Check Swahili description
            if ($conflictCategory->description_sw !== ($template->description_sw ?? null)) {
                echo "   ⚠️  Swahili description differs:\n";
                echo "      Category: '" . ($conflictCategory->description_sw ?? 'NULL') . "'\n";
                echo "      Template: '" . ($template->description_sw ?? 'NULL') . "'\n";
                echo "      Should update: YES\n";
            } else {
                echo "   ✅ Swahili descriptions match\n";
            }
        } else {
            echo "   ❌ No template found for 'Conflict of Interest'\n";
        }
    }
} else {
    echo "   ❌ 'Conflict of Interest' category not found\n";
}

// Check all categories that are missing Swahili translations
echo "\n4. Finding all categories missing Swahili translations...\n";

$incidentsMissingSwahili = IncidentCategory::where('company_id', $company->id)
    ->whereNotNull('category_key')
    ->whereNull('name_sw')
    ->get();

$feedbackMissingSwahili = FeedbackCategory::where('company_id', $company->id)
    ->whereNotNull('category_key')
    ->whereNull('name_sw')
    ->get();

echo "\n   Incident categories missing Swahili name: " . $incidentsMissingSwahili->count() . "\n";
if ($incidentsMissingSwahili->count() > 0) {
    foreach ($incidentsMissingSwahili as $cat) {
        echo "      - {$cat->name} (key: {$cat->category_key})\n";
    }
}

echo "\n   Feedback categories missing Swahili name: " . $feedbackMissingSwahili->count() . "\n";
if ($feedbackMissingSwahili->count() > 0) {
    foreach ($feedbackMissingSwahili as $cat) {
        echo "      - {$cat->name} (key: {$cat->category_key})\n";
    }
}

// Check if templates have translations for these categories
if ($incidentsMissingSwahili->count() > 0 && $company->sector) {
    echo "\n5. Checking if templates have translations for missing categories...\n";

    foreach ($incidentsMissingSwahili->take(5) as $cat) {
        $template = SectorIncidentTemplate::where('sector', $company->sector)
            ->where('category_name', $cat->name)
            ->first();

        if ($template && $template->category_name_sw) {
            echo "   ⚠️  {$cat->name}\n";
            echo "      Template has Swahili: '{$template->category_name_sw}'\n";
            echo "      But category has: NULL\n";
            echo "      → This should have been updated!\n";
        }
    }
}

echo "\n=== Diagnostic Complete ===\n";
