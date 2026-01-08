<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$companyId = '01k7rjt9vjh4zdkv38nq4akwdj';
$company = \App\Models\Company::find($companyId);

if (!$company) {
    die("Company not found\n");
}

echo "Company: {$company->name}\n";
echo "Sector: {$company->sector}\n\n";

// Check existing categories
$existing = \App\Models\FeedbackCategory::withTrashed()
    ->where('company_id', $companyId)
    ->get();

echo "Existing feedback categories (including soft-deleted): {$existing->count()}\n";
foreach ($existing as $cat) {
    $deleted = $cat->deleted_at ? " [SOFT DELETED]" : "";
    echo "  - {$cat->name} (ID: {$cat->id}){$deleted}\n";
}

echo "\n";

// Check templates
$templates = \App\Models\SectorFeedbackTemplate::where('sector', $company->sector)
    ->where('status', true)
    ->get();

echo "Sector templates: {$templates->count()}\n";
$names = $templates->pluck('category_name');
$uniqueNames = $names->unique();

echo "Unique category names in templates: {$uniqueNames->count()}\n";

// Check for duplicates
$duplicates = $names->duplicates();
if ($duplicates->count() > 0) {
    echo "\nDUPLICATE CATEGORY NAMES IN TEMPLATES:\n";
    foreach ($duplicates as $dup) {
        echo "  - {$dup}\n";
        $matching = $templates->where('category_name', $dup);
        foreach ($matching as $t) {
            echo "    > ID: {$t->id}, Sort: {$t->sort_order}\n";
        }
    }
} else {
    echo "No duplicates found in templates\n";
}

echo "\n\nAll template names:\n";
foreach ($templates as $t) {
    echo "  - {$t->category_name} (sort: {$t->sort_order})\n";
}
