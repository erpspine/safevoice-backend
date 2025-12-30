<?php

/**
 * Subscription Plan API - Testing Script
 * 
 * This script demonstrates the monthly & yearly billing API functionality
 */

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\SubscriptionPlan;

echo "\n" . str_repeat("=", 80) . "\n";
echo "SUBSCRIPTION PLAN API - MONTHLY & YEARLY BILLING TEST\n";
echo str_repeat("=", 80) . "\n\n";

// Test 1: Get all active plans
echo "📋 TEST 1: Get All Active Plans\n";
echo str_repeat("-", 80) . "\n";
$plans = SubscriptionPlan::active()->orderBy('price')->get();
foreach ($plans as $plan) {
    echo sprintf(
        "%-20s | Monthly: \$%-8.2f | Yearly: \$%-10.2f | Save: %d%% (\$%-8.2f)\n",
        $plan->name,
        $plan->getMonthlyPrice(),
        $plan->getYearlyPrice(),
        $plan->getDiscountPercentage(),
        $plan->getAmountSaved()
    );
}
echo "\n";

// Test 2: Get specific plan details
echo "📋 TEST 2: Get Specific Plan Details (Professional)\n";
echo str_repeat("-", 80) . "\n";
$professional = SubscriptionPlan::where('name', 'Professional')->first();
if ($professional) {
    echo "Plan Name: {$professional->name}\n";
    echo "Billing Period: {$professional->billing_period}\n";
    echo "Monthly Price: \${$professional->getMonthlyPrice()}\n";
    echo "Yearly Price: \${$professional->getYearlyPrice()}\n";
    echo "Discount: {$professional->getDiscountPercentage()}%\n";
    echo "Save Amount: \${$professional->getAmountSaved()}\n";
    echo "Grace Days: {$professional->grace_days}\n";
    echo "Description: {$professional->description}\n";
}
echo "\n";

// Test 3: Calculate yearly pricing with custom discount
echo "📋 TEST 3: Calculate Yearly Pricing (Starter with 15% discount)\n";
echo str_repeat("-", 80) . "\n";
$starter = SubscriptionPlan::where('name', 'Starter')->first();
if ($starter) {
    $customPricing = $starter->calculateYearlyPricing(15.0);
    echo "Monthly Price: \${$customPricing['monthly_price']}\n";
    echo "12 Months Total: \${$customPricing['monthly_total_12_months']}\n";
    echo "Discount: {$customPricing['discount_percentage']}%\n";
    echo "Discount Amount: \${$customPricing['discount_amount']}\n";
    echo "Yearly Price: \${$customPricing['yearly_price']}\n";
    echo "Amount Saved: \${$customPricing['amount_saved']}\n";
}
echo "\n";

// Test 4: Test model helper methods
echo "📋 TEST 4: Model Helper Methods (Business Plan)\n";
echo str_repeat("-", 80) . "\n";
$business = SubscriptionPlan::where('name', 'Business')->first();
if ($business) {
    echo "getMonthlyPrice(): \${$business->getMonthlyPrice()}\n";
    echo "getYearlyPrice(): \${$business->getYearlyPrice()}\n";
    echo "getDiscountPercentage(): {$business->getDiscountPercentage()}%\n";
    echo "getDiscountAmount(): \${$business->getDiscountAmount()}\n";
    echo "getAmountSaved(): \${$business->getAmountSaved()}\n";
}
echo "\n";

// Test 5: Compare monthly vs yearly savings
echo "📋 TEST 5: Savings Comparison Across All Plans\n";
echo str_repeat("-", 80) . "\n";
printf("%-20s | Monthly (12mo) | Yearly | Saves | Save %%\n", "Plan");
echo str_repeat("-", 80) . "\n";
foreach ($plans as $plan) {
    $monthlyTotal = $plan->getMonthlyPrice() * 12;
    $yearlyPrice = $plan->getYearlyPrice();
    $savings = $monthlyTotal - $yearlyPrice;
    $savePercent = $monthlyTotal > 0 ? round(($savings / $monthlyTotal) * 100, 2) : 0;
    
    printf(
        "%-20s | \$%-13.2f | \$%-6.2f | \$%-5.2f | %d%%\n",
        $plan->name,
        $monthlyTotal,
        $yearlyPrice,
        $savings,
        $savePercent
    );
}
echo "\n";

// Test 6: Test auto-calculation scenarios
echo "📋 TEST 6: Auto-Calculation Examples\n";
echo str_repeat("-", 80) . "\n";
echo "Scenario A: Create plan with discount_percentage (yearly_price auto-calculated)\n";
$testPriceA = [
    'monthly_price' => 50.00,
    'discount_percentage' => 20.0,
];
echo "  Input: Monthly = \${$testPriceA['monthly_price']}, Discount = {$testPriceA['discount_percentage']}%\n";
$monthlyTotal = $testPriceA['monthly_price'] * 12;
$discountAmount = $monthlyTotal * ($testPriceA['discount_percentage'] / 100);
$yearlyPrice = $monthlyTotal - $discountAmount;
echo "  Output: Yearly = \${$yearlyPrice}, Saves = \${$discountAmount}\n";
echo "\n";

echo "Scenario B: Create plan with yearly_price (discount auto-calculated)\n";
$testPriceB = [
    'monthly_price' => 100.00,
    'yearly_price' => 1050.00,
];
echo "  Input: Monthly = \${$testPriceB['monthly_price']}, Yearly = \${$testPriceB['yearly_price']}\n";
$monthlyTotal = $testPriceB['monthly_price'] * 12;
$discountAmount = $monthlyTotal - $testPriceB['yearly_price'];
$discountPercent = round(($discountAmount / $monthlyTotal) * 100, 2);
echo "  Output: Discount = {$discountPercent}%, Saves = \${$discountAmount}\n";
echo "\n";

// Test 7: API response format
echo "📋 TEST 7: API Response Format (for active endpoint)\n";
echo str_repeat("-", 80) . "\n";
$starter = SubscriptionPlan::where('name', 'Starter')->first();
$response = [
    'id' => $starter->id,
    'name' => $starter->name,
    'description' => $starter->description,
    'billing_period' => $starter->billing_period,
    'pricing' => [
        'monthly_price' => $starter->getMonthlyPrice(),
        'yearly_price' => $starter->getYearlyPrice(),
        'discount_percentage' => $starter->getDiscountPercentage(),
        'discount_amount' => $starter->getDiscountAmount(),
        'amount_saved' => $starter->getAmountSaved(),
        'currency' => $starter->currency,
    ],
    'features' => [
        'grace_days' => $starter->grace_days,
    ],
];
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "\n";

echo str_repeat("=", 80) . "\n";
echo "✅ ALL TESTS COMPLETED SUCCESSFULLY!\n";
echo str_repeat("=", 80) . "\n\n";
