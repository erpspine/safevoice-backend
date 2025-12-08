<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\SubscriptionPlan;

echo 'Testing Subscription Plan CRUD functionality...' . PHP_EOL . PHP_EOL;

// Test: Read all plans
echo '1. Testing READ (All Plans):' . PHP_EOL;
$plans = SubscriptionPlan::all();
echo "Found {$plans->count()} subscription plans:" . PHP_EOL;
foreach ($plans as $plan) {
    $status = $plan->is_active ? 'Active' : 'Inactive';
    echo "   - {$plan->name}: \${$plan->price} ({$plan->max_branches} branches, {$plan->grace_days} grace days) [{$status}]" . PHP_EOL;
}

echo PHP_EOL . '2. Testing READ (Active Plans Only):' . PHP_EOL;
$activePlans = SubscriptionPlan::active()->get();
echo "Found {$activePlans->count()} active plans:" . PHP_EOL;
foreach ($activePlans as $plan) {
    echo "   - {$plan->name}: \${$plan->price}" . PHP_EOL;
}

// Test: Create a new plan
echo PHP_EOL . '3. Testing CREATE:' . PHP_EOL;
try {
    $newPlan = SubscriptionPlan::create([
        'name' => 'Test Plan',
        'price' => 99.99,
        'max_branches' => 10,
        'grace_days' => 14,
        'description' => 'This is a test subscription plan',
        'is_active' => true
    ]);
    echo "✅ Created new plan: {$newPlan->name} (ID: {$newPlan->id})" . PHP_EOL;

    // Test: Update the plan
    echo PHP_EOL . '4. Testing UPDATE:' . PHP_EOL;
    $newPlan->update([
        'price' => 129.99,
        'description' => 'Updated test subscription plan'
    ]);
    echo "✅ Updated plan price to \${$newPlan->fresh()->price}" . PHP_EOL;

    // Test: Soft delete the plan
    echo PHP_EOL . '5. Testing DELETE (Soft Delete):' . PHP_EOL;
    $newPlan->delete();
    echo "✅ Soft deleted plan: {$newPlan->name}" . PHP_EOL;

    // Verify it's soft deleted
    $deletedCount = SubscriptionPlan::onlyTrashed()->where('name', 'Test Plan')->count();
    echo "✅ Verified soft delete: {$deletedCount} deleted plan(s) found" . PHP_EOL;
} catch (Exception $e) {
    echo "❌ Error during testing: " . $e->getMessage() . PHP_EOL;
}

// Test: Scopes and relationships
echo PHP_EOL . '6. Testing SCOPES:' . PHP_EOL;
$orderedByPrice = SubscriptionPlan::active()->orderByPrice('desc')->first();
if ($orderedByPrice) {
    echo "✅ Most expensive active plan: {$orderedByPrice->name} (\${$orderedByPrice->price})" . PHP_EOL;
}

// Test database structure
echo PHP_EOL . '7. Testing DATABASE STRUCTURE:' . PHP_EOL;
$columns = DB::getSchemaBuilder()->getColumnListing('subscription_plans');
$expectedColumns = ['id', 'name', 'price', 'max_branches', 'grace_days', 'description', 'is_active', 'created_at', 'updated_at', 'deleted_at'];

foreach ($expectedColumns as $column) {
    $exists = in_array($column, $columns);
    echo ($exists ? '✅' : '❌') . " Column '{$column}': " . ($exists ? 'exists' : 'missing') . PHP_EOL;
}

echo PHP_EOL . '🎉 Subscription Plan CRUD testing completed!' . PHP_EOL;
