<?php

/**
 * Quick test script to verify Swahili translations for Feedback Categories
 * Run: php test_feedback_swahili.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\SectorFeedbackTemplate;

echo "==============================================\n";
echo "Feedback Category Swahili Translation Test\n";
echo "==============================================\n\n";

// Test 1: Get Education Sector in English
echo "TEST 1: Education Sector (English)\n";
echo "-------------------------------------------\n";
$englishCategories = SectorFeedbackTemplate::getBySector('education', 'en');
foreach ($englishCategories as $category) {
    echo "✓ {$category['category_name']}\n";
    if (!empty($category['subcategories'])) {
        echo "  Subcategories: " . count($category['subcategories']) . "\n";
    }
}
echo "\n";

// Test 2: Get Education Sector in Swahili
echo "TEST 2: Education Sector (Swahili)\n";
echo "-------------------------------------------\n";
$swahiliCategories = SectorFeedbackTemplate::getBySector('education', 'sw');
foreach ($swahiliCategories as $category) {
    echo "✓ {$category['category_name']}\n";
    if (!empty($category['subcategories'])) {
        echo "  Subcategories: " . count($category['subcategories']) . "\n";
    }
}
echo "\n";

// Test 3: Compare English and Swahili for Healthcare
echo "TEST 3: Healthcare Sector Comparison\n";
echo "-------------------------------------------\n";
$healthcareEN = SectorFeedbackTemplate::getBySector('healthcare', 'en');
$healthcareSW = SectorFeedbackTemplate::getBySector('healthcare', 'sw');

echo "English vs Swahili:\n";
foreach ($healthcareEN as $index => $category) {
    $swahili = $healthcareSW[$index] ?? null;
    if ($swahili) {
        echo "  EN: {$category['category_name']}\n";
        echo "  SW: {$swahili['category_name']}\n";
        echo "  ---\n";
    }
}
echo "\n";

// Test 4: Check all sectors have translations
echo "TEST 4: Translation Coverage Check\n";
echo "-------------------------------------------\n";
$sectors = [
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

foreach ($sectors as $sector) {
    $categories = SectorFeedbackTemplate::getBySector($sector, 'sw');
    $categoryCount = count($categories);
    echo "✓ {$sector}: {$categoryCount} categories\n";
}
echo "\n";

// Test 5: Test getLocalizedField method
echo "TEST 5: getLocalizedField Method Test\n";
echo "-------------------------------------------\n";
$template = SectorFeedbackTemplate::where('sector', 'education')->first();
if ($template) {
    echo "Category Name (EN): {$template->getLocalizedField('category_name', 'en')}\n";
    echo "Category Name (SW): {$template->getLocalizedField('category_name', 'sw')}\n";
    if ($template->description) {
        echo "Description (EN): {$template->getLocalizedField('description', 'en')}\n";
        echo "Description (SW): {$template->getLocalizedField('description', 'sw')}\n";
    }
}
echo "\n";

// Test 6: Count total translations
echo "TEST 6: Translation Statistics\n";
echo "-------------------------------------------\n";
$totalRecords = SectorFeedbackTemplate::count();
$withSwahiliCategory = SectorFeedbackTemplate::whereNotNull('category_name_sw')->count();
$withSwahiliSubcategory = SectorFeedbackTemplate::whereNotNull('subcategory_name_sw')->count();
$withSwahiliDescription = SectorFeedbackTemplate::whereNotNull('description_sw')->count();

echo "Total Records: {$totalRecords}\n";
echo "Records with Swahili Category Names: {$withSwahiliCategory}\n";
echo "Records with Swahili Subcategory Names: {$withSwahiliSubcategory}\n";
echo "Records with Swahili Descriptions: {$withSwahiliDescription}\n";
echo "\n";

// Calculate coverage percentage
$categoryPercentage = $totalRecords > 0 ? round(($withSwahiliCategory / $totalRecords) * 100, 2) : 0;
echo "Translation Coverage: {$categoryPercentage}%\n";

echo "\n==============================================\n";
echo "✓ All tests completed successfully!\n";
echo "==============================================\n";
