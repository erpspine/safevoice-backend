<?php

/**
 * Test that company update now triggers sync for missing Swahili translations
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Company;
use App\Models\IncidentCategory;
use App\Models\FeedbackCategory;

echo "=== Test Auto-Sync on Company Update ===\n\n";

// Find a company with categories
$company = Company::where('name', 'LIKE', '%Technoguru%')->first();

if (!$company) {
    echo "❌ Technoguru company not found\n";
    exit(1);
}

echo "Testing with company: {$company->name}\n";
echo "Sector: {$company->sector}\n\n";

// Manually set some categories to have NULL Swahili (simulate outdated data)
echo "1. Simulating outdated data (removing Swahili from sample categories)...\n";

$sampleIncident = IncidentCategory::where('company_id', $company->id)
    ->whereNotNull('category_key')
    ->whereNotNull('name_sw')
    ->first();

$sampleFeedback = FeedbackCategory::where('company_id', $company->id)
    ->whereNotNull('category_key')
    ->whereNotNull('name_sw')
    ->first();

if ($sampleIncident) {
    $sampleIncident->update(['name_sw' => null, 'description_sw' => null]);
    echo "   Set '{$sampleIncident->name}' Swahili fields to NULL\n";
}

if ($sampleFeedback) {
    $sampleFeedback->update(['name_sw' => null, 'description_sw' => null]);
    echo "   Set '{$sampleFeedback->name}' Swahili fields to NULL\n";
}

// Count categories missing Swahili before update
$incidentMissingBefore = IncidentCategory::where('company_id', $company->id)
    ->whereNotNull('category_key')
    ->whereNull('name_sw')
    ->count();

$feedbackMissingBefore = FeedbackCategory::where('company_id', $company->id)
    ->whereNotNull('category_key')
    ->whereNull('name_sw')
    ->count();

echo "\n2. Categories missing Swahili BEFORE company update:\n";
echo "   Incident categories: {$incidentMissingBefore}\n";
echo "   Feedback categories: {$feedbackMissingBefore}\n";

// Update company (just change description to trigger update)
echo "\n3. Updating company (minor change)...\n";
$company->update(['description' => 'Updated ' . date('Y-m-d H:i:s')]);
echo "   ✅ Company updated\n";

// Count categories missing Swahili after update
$incidentMissingAfter = IncidentCategory::where('company_id', $company->id)
    ->whereNotNull('category_key')
    ->whereNull('name_sw')
    ->count();

$feedbackMissingAfter = FeedbackCategory::where('company_id', $company->id)
    ->whereNotNull('category_key')
    ->whereNull('name_sw')
    ->count();

echo "\n4. Categories missing Swahili AFTER company update:\n";
echo "   Incident categories: {$incidentMissingAfter}\n";
echo "   Feedback categories: {$feedbackMissingAfter}\n";

// Verify specific categories were updated
if ($sampleIncident) {
    $sampleIncident->refresh();
    echo "\n5. Verifying '{$sampleIncident->name}' was auto-updated:\n";
    echo "   Name (Swahili): " . ($sampleIncident->name_sw ?? 'NULL') . "\n";

    if ($sampleIncident->name_sw !== null) {
        echo "   ✅ Auto-sync worked! Swahili translation restored\n";
    } else {
        echo "   ❌ Auto-sync failed - still NULL\n";
    }
}

if ($sampleFeedback) {
    $sampleFeedback->refresh();
    echo "\n6. Verifying '{$sampleFeedback->name}' was auto-updated:\n";
    echo "   Name (Swahili): " . ($sampleFeedback->name_sw ?? 'NULL') . "\n";

    if ($sampleFeedback->name_sw !== null) {
        echo "   ✅ Auto-sync worked! Swahili translation restored\n";
    } else {
        echo "   ❌ Auto-sync failed - still NULL\n";
    }
}

echo "\n=== Summary ===\n";
if ($incidentMissingAfter === 0 && $feedbackMissingAfter === 0) {
    echo "✅ SUCCESS: All categories now have Swahili translations\n";
    echo "✅ Auto-sync on company update is working correctly\n";
} else {
    echo "⚠️  Some categories still missing translations\n";
    echo "   This might indicate an issue with the sync logic\n";
}

echo "\n=== Test Complete ===\n";
