<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\{Company, SubscriptionPlan, Branch, Subscription};
use App\Services\SubscriptionService;

echo "🚀 Testing Subscription System with Branch Selection\n";
echo "=" . str_repeat("=", 50) . "\n\n";

try {
    // Get a test company and create some branches
    $company = Company::first();
    if (!$company) {
        echo "❌ No company found. Please create a company first.\n";
        exit;
    }

    echo "📋 Company: {$company->name} (ID: {$company->id})\n";

    // Check if company has branches, if not create some test branches
    $branchCount = Branch::where('company_id', $company->id)->count();
    if ($branchCount < 3) {
        echo "🏢 Creating test branches...\n";

        $branches = [
            ['name' => 'Main Office', 'location' => 'Dar es Salaam', 'address' => 'Kivukoni Front'],
            ['name' => 'Mwanza Branch', 'location' => 'Mwanza', 'address' => 'Kenyatta Road'],
            ['name' => 'Arusha Branch', 'location' => 'Arusha', 'address' => 'Clock Tower'],
            ['name' => 'Dodoma Branch', 'location' => 'Dodoma', 'address' => 'Nyerere Road'],
            ['name' => 'Mbeya Branch', 'location' => 'Mbeya', 'address' => 'Jacaranda Street'],
        ];

        foreach ($branches as $branchData) {
            Branch::firstOrCreate(
                ['name' => $branchData['name'], 'company_id' => $company->id],
                array_merge($branchData, [
                    'company_id' => $company->id,
                    'status' => true,
                    'is_active' => false, // Initially inactive
                ])
            );
        }
    }

    // Get available branches
    $branches = Branch::where('company_id', $company->id)->get();
    echo "🏢 Available branches ({$branches->count()}):\n";
    foreach ($branches as $branch) {
        $status = $branch->is_active ? '🟢 Active' : '🔴 Inactive';
        $until = $branch->activated_until ? " (until {$branch->activated_until})" : '';
        echo "   - {$branch->name} - {$branch->location} [{$status}]{$until}\n";
    }

    // Get a subscription plan
    $plan = SubscriptionPlan::where('is_active', true)->first();
    if (!$plan) {
        echo "❌ No active subscription plan found.\n";
        exit;
    }

    echo "\n💰 Using Plan: {$plan->name} (\${$plan->price}, max {$plan->max_branches} branches)\n";

    // Initialize subscription service
    $subscriptionService = new SubscriptionService();

    // Test 1: Get available branches for selection
    echo "\n📋 Test 1: Getting available branches for selection...\n";
    $availableBranches = $subscriptionService->getAvailableBranches($company, $plan);

    echo "   Available branches for selection:\n";
    foreach ($availableBranches['branches'] as $branch) {
        echo "   - ID: {$branch['id']} | {$branch['full_name']} | Currently: " .
            ($branch['is_currently_active'] ? 'Active' : 'Inactive') . "\n";
    }
    echo "   Max selectable: {$availableBranches['max_selectable']}\n";

    // Test 2: Create subscription with selected branches
    echo "\n📋 Test 2: Creating subscription with selected branches...\n";

    // Select first 2 branches (or up to plan limit)
    $maxSelect = min($plan->max_branches ?? 2, 2);
    $selectedBranchIds = $availableBranches['branches']->take($maxSelect)->pluck('id')->toArray();

    echo "   Selected branch IDs: " . implode(', ', $selectedBranchIds) . "\n";

    $paymentData = [
        'payment_method' => 'card',
        'amount_paid' => $plan->price,
        'payment_reference' => 'TEST_PAY_' . now()->format('YmdHis'),
        'auto_renew' => false,
    ];

    $subscription = $subscriptionService->startOrRenew(
        $company,
        $plan,
        1, // 1 month duration
        $paymentData,
        $selectedBranchIds
    );

    echo "   ✅ Subscription created successfully!\n";
    echo "   Subscription ID: {$subscription->id}\n";
    echo "   Period: {$subscription->starts_on} to {$subscription->ends_on}\n";
    echo "   Grace until: {$subscription->grace_until}\n";
    echo "   Status: {$subscription->status}\n";

    // Test 3: Check activated branches
    echo "\n📋 Test 3: Checking activated branches...\n";
    $activatedBranches = $subscription->branches;

    echo "   Activated branches ({$activatedBranches->count()}):\n";
    foreach ($activatedBranches as $branch) {
        $pivot = $branch->pivot;
        echo "   - {$branch->name} - {$branch->location}\n";
        echo "     Activated from: {$pivot->activated_from} to {$pivot->activated_until}\n";
    }

    // Refresh and show all branches status
    $branches = Branch::where('company_id', $company->id)->get();
    echo "\n📋 All branches status after subscription:\n";
    foreach ($branches as $branch) {
        $status = $branch->is_active ? '🟢 Active' : '🔴 Inactive';
        $until = $branch->activated_until ? " (until {$branch->activated_until})" : '';
        echo "   - {$branch->name} - {$branch->location} [{$status}]{$until}\n";
    }

    // Test 4: Update subscription branches
    echo "\n📋 Test 4: Updating subscription branches...\n";

    // Select different branches (last 2 or up to plan limit)
    $newSelectedIds = $availableBranches['branches']->slice(-$maxSelect)->pluck('id')->toArray();
    echo "   New selected branch IDs: " . implode(', ', $newSelectedIds) . "\n";

    $subscriptionService->activateSelectedBranches(
        $company,
        $plan,
        \Carbon\Carbon::parse($subscription->ends_on),
        $subscription,
        $newSelectedIds
    );

    echo "   ✅ Subscription branches updated!\n";

    // Show final status
    $branches = Branch::where('company_id', $company->id)->get();
    echo "\n📋 Final branches status:\n";
    foreach ($branches as $branch) {
        $status = $branch->is_active ? '🟢 Active' : '🔴 Inactive';
        $until = $branch->activated_until ? " (until {$branch->activated_until})" : '';
        echo "   - {$branch->name} - {$branch->location} [{$status}]{$until}\n";
    }

    // Test 5: Show subscription details
    echo "\n📋 Test 5: Subscription details with relationships:\n";
    $subscriptionDetails = Subscription::with(['company', 'plan', 'branches', 'payments'])
        ->find($subscription->id);

    echo "   Company: {$subscriptionDetails->company->name}\n";
    echo "   Plan: {$subscriptionDetails->plan->name} (\${$subscriptionDetails->plan->price})\n";
    echo "   Active branches: {$subscriptionDetails->branches->count()}\n";
    echo "   Payments: {$subscriptionDetails->payments->count()}\n";
    echo "   Days remaining: {$subscriptionDetails->days_remaining}\n";
    echo "   Is active: " . ($subscriptionDetails->isActive() ? 'Yes' : 'No') . "\n";

    echo "\n🎉 All tests completed successfully!\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
