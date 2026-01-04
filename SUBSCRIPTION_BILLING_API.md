# Subscription Plans API - Monthly & Yearly Billing

## Overview

The Subscription Plans API now supports both **monthly** and **yearly** billing periods with automatic discount calculations for annual commitments. This allows companies to choose between flexible monthly payments or discounted yearly subscriptions.

## Features

✅ **Monthly & Yearly Billing Periods**
✅ **Automatic Discount Calculation**
✅ **Amount Saved Display**
✅ **Flexible Pricing Structure**
✅ **Easy Configuration**

## Database Schema

### New Columns Added to `subscription_plans` Table

```sql
-- Billing period (monthly or yearly)
billing_period ENUM('monthly', 'yearly') DEFAULT 'monthly'

-- Yearly price (optional, auto-calculated if discount provided)
yearly_price DECIMAL(10, 2) NULLABLE

-- Discount fields
discount_amount DECIMAL(10, 2) NULLABLE         -- Total discount amount
discount_percentage DECIMAL(5, 2) NULLABLE      -- Discount percentage (0-100)
amount_saved DECIMAL(10, 2) NULLABLE            -- Total savings compared to monthly
```

## Current Pricing Structure

### Starter Plan

-   **Monthly**: $29.99/month
-   **Yearly**: $299.99/year (10% discount = save $59.88)

### Professional Plan

-   **Monthly**: $79.99/month
-   **Yearly**: $799.90/year (15% discount = save $143.98)

### Business Plan

-   **Monthly**: $149.99/month
-   **Yearly**: $1,679.88/year (20% discount = save $419.88)

### Enterprise Plan

-   **Monthly**: $299.99/month
-   **Yearly**: $3,239.88/year (20% discount = save $959.88)

## API Endpoints

### 1. Get All Active Plans (with pricing)

```http
GET /api/subscription-plans/active
```

**Response:**

```json
{
    "success": true,
    "data": [
        {
            "id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
            "name": "Starter",
            "description": "Perfect for small businesses...",
            "billing_period": "monthly",
            "pricing": {
                "monthly_price": 29.99,
                "yearly_price": 299.99,
                "discount_percentage": 10.0,
                "discount_amount": 59.88,
                "amount_saved": 59.88,
                "currency": "USD"
            },
            "features": {
                "max_branches": 1,
                "grace_days": 7
            }
        },
        {
            "id": "01ARZ3NDEKTSV4RRFFQ69G5FAX",
            "name": "Professional",
            "description": "Ideal for growing companies...",
            "billing_period": "monthly",
            "pricing": {
                "monthly_price": 79.99,
                "yearly_price": 799.9,
                "discount_percentage": 15.0,
                "discount_amount": 143.98,
                "amount_saved": 143.98,
                "currency": "USD"
            },
            "features": {
                "max_branches": 5,
                "grace_days": 14
            }
        }
    ]
}
```

### 2. Get Specific Plan Details

```http
GET /api/subscription-plans/{id}
```

**Response:**

```json
{
    "success": true,
    "data": {
        "id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
        "name": "Starter",
        "price": 29.99,
        "billing_period": "monthly",
        "yearly_price": 299.99,
        "discount_percentage": 10.0,
        "discount_amount": 59.88,
        "amount_saved": 59.88,
        "currency": "USD",
        "grace_days": 7,
        "description": "Perfect for small businesses...",
        "is_active": true,
        "created_at": "2025-11-03T12:00:00Z",
        "updated_at": "2025-12-30T10:00:00Z"
    }
}
```

### 3. Calculate Pricing with Custom Discount

```http
GET /api/subscription-plans/{id}/pricing?discount_percentage=25
```

**Query Parameters:**

-   `discount_percentage` (optional): Custom discount percentage to calculate

**Response:**

```json
{
    "success": true,
    "data": {
        "plan": {
            "id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
            "name": "Starter"
        },
        "pricing": {
            "monthly_price": 29.99,
            "monthly_total_12_months": 359.88,
            "discount_percentage": 25.0,
            "discount_amount": 89.97,
            "yearly_price": 269.91,
            "amount_saved": 89.97
        }
    }
}
```

### 4. Create Subscription Plan (Monthly only)

```http
POST /api/subscription-plans
Content-Type: application/json

{
  "name": "Starter",
  "price": 29.99,
  "billing_period": "monthly",
  "currency": "USD",
  "grace_days": 7,
  "description": "Perfect for small businesses",
  "is_active": true
}
```

### 5. Create Subscription Plan (with Yearly & Discount)

**Option A: Provide discount percentage (yearly_price auto-calculated)**

```http
POST /api/subscription-plans
Content-Type: application/json

{
  "name": "Starter",
  "price": 29.99,
  "billing_period": "monthly",
  "discount_percentage": 10.0,
  "currency": "USD",
  "grace_days": 7,
  "description": "Perfect for small businesses",
  "is_active": true
}
```

Response: `yearly_price`, `discount_amount`, `amount_saved` auto-calculated as:

-   `yearly_price` = (29.99 × 12) - discount = 359.88 - 35.99 = 299.99
-   `discount_amount` = 359.88 × (10 / 100) = 35.99
-   `amount_saved` = 35.99

**Option B: Provide yearly price (discount auto-calculated)**

```http
POST /api/subscription-plans
Content-Type: application/json

{
  "name": "Professional",
  "price": 79.99,
  "billing_period": "monthly",
  "yearly_price": 799.90,
  "currency": "USD",
  "grace_days": 14,
  "description": "Ideal for growing companies",
  "is_active": true
}
```

Response: `discount_percentage`, `discount_amount`, `amount_saved` auto-calculated as:

-   Monthly total (12 months) = 79.99 × 12 = 959.88
-   `discount_amount` = 959.88 - 799.90 = 159.98
-   `discount_percentage` = (159.98 / 959.88) × 100 = 16.67%
-   `amount_saved` = 159.98

### 6. Update Subscription Plan

```http
PUT /api/subscription-plans/{id}
Content-Type: application/json

{
  "price": 34.99,
  "discount_percentage": 12.0
}
```

The API will automatically recalculate:

-   New yearly price based on new monthly price and discount
-   New discount amount and amount saved

## Model Methods

The `SubscriptionPlan` model provides convenient methods for pricing calculations:

```php
// Get monthly price
$plan->getMonthlyPrice()           // Returns: 29.99

// Get yearly price (with discount if configured)
$plan->getYearlyPrice()            // Returns: 299.99

// Get discount percentage (auto-calculated if not set)
$plan->getDiscountPercentage()     // Returns: 10.0

// Get discount amount (auto-calculated if not set)
$plan->getDiscountAmount()         // Returns: 59.88

// Get amount saved
$plan->getAmountSaved()            // Returns: 59.88

// Full pricing calculation with optional custom discount
$plan->calculateYearlyPricing(15.0)  // Returns array with all pricing details

// Array structure:
[
  'monthly_price' => 29.99,
  'monthly_total_12_months' => 359.88,
  'discount_percentage' => 15.0,
  'discount_amount' => 53.98,
  'yearly_price' => 305.90,
  'amount_saved' => 53.98,
]
```

## Usage Examples

### Example 1: Frontend - Display Monthly vs Yearly Plans

```javascript
// Fetch active plans
const response = await fetch("/api/subscription-plans/active");
const { data: plans } = await response.json();

// Display pricing options for each plan
plans.forEach((plan) => {
    const pricing = plan.pricing;
    console.log(`${plan.name}:`);
    console.log(`  Monthly: $${pricing.monthly_price}/month`);
    console.log(`  Yearly:  $${pricing.yearly_price}/year`);
    console.log(
        `  Save:    ${pricing.discount_percentage}% ($${pricing.amount_saved})`
    );
});

// Output:
// Starter:
//   Monthly: $29.99/month
//   Yearly:  $299.99/year
//   Save:    10% ($59.88)
```

### Example 2: PHP Backend - Create Plan with Auto Discount

```php
use App\Models\SubscriptionPlan;

$plan = SubscriptionPlan::create([
    'name' => 'Premium Plus',
    'price' => 199.99,
    'billing_period' => 'monthly',
    'discount_percentage' => 18.0,  // Provide discount %
    // yearly_price auto-calculated
    'currency' => 'USD',
    'grace_days' => 21,
    'is_active' => true,
]);

echo $plan->yearly_price;        // 1967.92 (auto-calculated)
echo $plan->discount_amount;     // 431.96 (auto-calculated)
echo $plan->amount_saved;        // 431.96 (auto-calculated)
```

### Example 3: PHP Backend - Get All Pricing Details

```php
use App\Models\SubscriptionPlan;

$plan = SubscriptionPlan::findOrFail($planId);

// Get complete pricing breakdown
$pricing = $plan->calculateYearlyPricing();

return response()->json([
    'plan' => $plan->name,
    'pricing' => $pricing,
    'effective_discount' => $plan->getDiscountPercentage() . '%',
]);
```

### Example 4: Custom Discount Calculation

```php
use App\Models\SubscriptionPlan;

$plan = SubscriptionPlan::findOrFail($planId);

// Calculate custom 25% discount
$customPricing = $plan->calculateYearlyPricing(25.0);

// Result:
// [
//   'monthly_price' => 29.99,
//   'monthly_total_12_months' => 359.88,
//   'discount_percentage' => 25.0,
//   'discount_amount' => 89.97,
//   'yearly_price' => 269.91,
//   'amount_saved' => 89.97,
// ]
```

## Validation Rules

### Creating/Updating Plans

```
name                   : required|string|max:255|unique:subscription_plans,name
price                  : required|numeric|min:0|max:999999.99
billing_period         : nullable|in:monthly,yearly
yearly_price           : nullable|numeric|min:0|max:999999.99
discount_amount        : nullable|numeric|min:0|max:999999.99
discount_percentage    : nullable|numeric|min:0|max:100
currency               : nullable|string|size:3
grace_days             : nullable|integer|min:0|max:365
description            : nullable|string|max:1000
is_active              : nullable|boolean
```

## Auto-Calculation Rules

When creating or updating a plan:

1. **If `discount_percentage` is provided (and `yearly_price` is NOT):**

    - `yearly_price` = (monthly_price × 12) - discount_amount
    - `discount_amount` = (monthly_price × 12) × (discount_percentage / 100)
    - `amount_saved` = discount_amount

2. **If `yearly_price` is provided (and `discount_percentage` is NOT):**

    - `discount_amount` = (monthly_price × 12) - yearly_price
    - `discount_percentage` = (discount_amount / (monthly_price × 12)) × 100
    - `amount_saved` = discount_amount

3. **If both or neither are provided:**
    - Manually provided values are used (if both)
    - Zero values are used (if neither)

## Migration

The migration `2025_12_30_000000_add_billing_period_to_subscription_plans.php` adds:

-   `billing_period` column (ENUM: monthly, yearly)
-   `yearly_price` column (DECIMAL nullable)
-   `discount_amount` column (DECIMAL nullable)
-   `discount_percentage` column (DECIMAL nullable)
-   `amount_saved` column (DECIMAL nullable)
-   Index on `billing_period` for faster queries

Run migration:

```bash
php artisan migrate
```

## Seeders

The updated `SubscriptionPlanSeeder` creates:

-   4 monthly plans (Starter, Professional, Business, Enterprise)
-   4 yearly variants of each with appropriate discounts
-   1 legacy inactive plan

Seed the database:

```bash
php artisan db:seed --class=SubscriptionPlanSeeder
```

## Error Handling

### 404 - Plan Not Found

```json
{
    "success": false,
    "message": "Subscription plan not found"
}
```

### 422 - Validation Failed

```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "price": ["The price field is required."],
        "discount_percentage": [
            "The discount percentage may not be greater than 100."
        ]
    }
}
```

### 500 - Server Error

```json
{
    "success": false,
    "message": "Failed to create subscription plan",
    "error": "Error details..."
}
```

## Best Practices

1. **Always provide discount OR yearly_price, not both** - API will auto-calculate the missing one
2. **Keep discount percentages reasonable** - Use 0-30% range for typical yearly discounts
3. **Display savings prominently** - Show customers how much they save with yearly billing
4. **Round calculations** - All monetary values are rounded to 2 decimal places
5. **Test price changes** - Always test pricing endpoint when changing base prices

## Response Codes

| Code | Meaning                           |
| ---- | --------------------------------- |
| 200  | Successful GET/PUT request        |
| 201  | Successful POST request (created) |
| 404  | Plan not found                    |
| 422  | Validation failed                 |
| 500  | Server error                      |
