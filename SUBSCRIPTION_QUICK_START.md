# Subscription Plans - Quick Start Guide

## 🚀 Overview

Your subscription API now supports **monthly and yearly billing periods** with automatic discount calculations!

---

## 📋 What You Get

### 4 Main Plans with Both Billing Options:

```
┌─────────────┬─────────────┬─────────────┬──────────┐
│ Plan        │ Monthly     │ Yearly      │ Discount │
├─────────────┼─────────────┼─────────────┼──────────┤
│ Starter     │ $29.99/mo   │ $299.99/yr  │ Save 10% │
│ Professional│ $79.99/mo   │ $799.90/yr  │ Save 15% │
│ Business    │ $149.99/mo  │ $1,679.88/yr│ Save 20% │
│ Enterprise  │ $299.99/mo  │ $3,239.88/yr│ Save 20% │
└─────────────┴─────────────┴─────────────┴──────────┘
```

---

## 💻 API Endpoints

### 1️⃣ Get All Plans (with pricing)

```bash
curl -X GET http://localhost:8000/api/subscription-plans/active
```

**Response:**

```json
{
    "success": true,
    "data": [
        {
            "id": "01k958g5k65cpdyktgbtvae7nn",
            "name": "Starter",
            "billing_period": "monthly",
            "pricing": {
                "monthly_price": 29.99,
                "yearly_price": 299.99,
                "discount_percentage": 10,
                "discount_amount": 59.88,
                "amount_saved": 59.88,
                "currency": "USD"
            }
        }
    ]
}
```

### 2️⃣ Get Specific Plan

```bash
curl -X GET http://localhost:8000/api/subscription-plans/{id}
```

### 3️⃣ Calculate Custom Pricing

```bash
curl -X GET "http://localhost:8000/api/subscription-plans/{id}/pricing?discount_percentage=25"
```

### 4️⃣ Create Plan (with discount %)

```bash
curl -X POST http://localhost:8000/api/subscription-plans \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Premium",
    "price": 99.99,
    "discount_percentage": 12.5
  }'
```

_yearly_price auto-calculated_

### 5️⃣ Create Plan (with yearly price)

```bash
curl -X POST http://localhost:8000/api/subscription-plans \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Premium",
    "price": 99.99,
    "yearly_price": 1049.99
  }'
```

_discount % auto-calculated_

### 6️⃣ Update Plan

```bash
curl -X PUT http://localhost:8000/api/subscription-plans/{id} \
  -H "Content-Type: application/json" \
  -d '{
    "price": 34.99,
    "discount_percentage": 15
  }'
```

---

## 🔧 Using in Your Code

### PHP

```php
use App\Models\SubscriptionPlan;

$plan = SubscriptionPlan::find($planId);

// Get prices
echo $plan->getMonthlyPrice();        // 29.99
echo $plan->getYearlyPrice();         // 299.99

// Get discount info
echo $plan->getDiscountPercentage();  // 10
echo $plan->getAmountSaved();         // 59.88

// Calculate with custom discount
$pricing = $plan->calculateYearlyPricing(20);
echo $pricing['yearly_price'];        // Price with 20% discount
```

### JavaScript / Frontend

```javascript
// Fetch and display plans
const response = await fetch("/api/subscription-plans/active");
const { data: plans } = await response.json();

plans.forEach((plan) => {
    console.log(`${plan.name}`);
    console.log(`Monthly: $${plan.pricing.monthly_price}`);
    console.log(`Yearly: $${plan.pricing.yearly_price}`);
    console.log(
        `Save: ${plan.pricing.discount_percentage}% ($${plan.pricing.amount_saved})`
    );
});
```

---

## 📊 Pricing Calculation Logic

### Option A: Provide discount percentage

```
Input: price = 29.99, discount_percentage = 10%

Calculations:
- Monthly Total (12 months) = 29.99 × 12 = 359.88
- Discount Amount = 359.88 × (10 / 100) = 35.99
- Yearly Price = 359.88 - 35.99 = 323.89
- Amount Saved = 35.99
```

### Option B: Provide yearly price

```
Input: price = 29.99, yearly_price = 299.99

Calculations:
- Monthly Total (12 months) = 29.99 × 12 = 359.88
- Discount Amount = 359.88 - 299.99 = 59.89
- Discount Percentage = (59.89 / 359.88) × 100 = 16.64%
- Amount Saved = 59.89
```

---

## ✨ Highlighted Features

✅ **Smart Auto-Calculation**

-   Provide discount % → yearly_price auto-calculated
-   Provide yearly_price → discount % auto-calculated

✅ **Flexible Pricing**

-   Easy to adjust discounts
-   Works with any price point
-   Supports custom calculations via API

✅ **Clear Savings Display**

-   `discount_percentage` - Show % to customers
-   `amount_saved` - Show $ savings
-   `discount_amount` - Total discount

✅ **Full API Control**

-   Create/Read/Update plans
-   Calculate custom pricing
-   Get active plans with pricing

---

## 🧪 Test It

Run the test script to see it in action:

```bash
php test_subscription_billing.php
```

Output shows:

-   All active plans with pricing
-   Specific plan details
-   Custom discount calculations
-   Savings comparisons
-   Model helper methods

---

## 🎯 Common Use Cases

### Display Pricing Comparison

```javascript
const starter = plans.find((p) => p.name === "Starter");
console.log(`
  Monthly: $${starter.pricing.monthly_price}/month
  Yearly: $${starter.pricing.yearly_price}/year
  Save: ${starter.pricing.discount_percentage}% ($${starter.pricing.amount_saved})
`);
```

### Create Custom Pricing Tier

```php
SubscriptionPlan::create([
    'name' => 'Custom Enterprise',
    'price' => 500,           // $500/month
    'discount_percentage' => 25  // 25% yearly discount
    // yearly_price auto-calculated: 45,000
]);
```

### Get Yearly Cost for Planning

```php
$monthlyPlans = SubscriptionPlan::where('billing_period', 'monthly')->get();
$monthlyPlans->map(function($plan) {
    return [
        'name' => $plan->name,
        'monthly_cost' => $plan->price,
        'yearly_cost' => $plan->getYearlyPrice(),
        'savings' => $plan->getAmountSaved(),
    ];
});
```

---

## 📚 Documentation

For detailed information, see:

-   **Full API Guide**: `SUBSCRIPTION_BILLING_API.md`
-   **Implementation Details**: `SUBSCRIPTION_BILLING_IMPLEMENTATION.md`
-   **Test Examples**: `test_subscription_billing.php`

---

## 🔍 Database Fields

```sql
-- New columns in subscription_plans table:
billing_period          ENUM('monthly', 'yearly')    -- Billing period
yearly_price            DECIMAL(10,2) NULLABLE       -- Yearly pricing
discount_amount         DECIMAL(10,2) NULLABLE       -- Total discount amount
discount_percentage     DECIMAL(5,2) NULLABLE        -- Discount percentage (0-100)
amount_saved            DECIMAL(10,2) NULLABLE       -- Amount customer saves
```

---

## ✅ Status

-   ✅ Migration applied
-   ✅ Database schema updated
-   ✅ Model methods implemented
-   ✅ API endpoints ready
-   ✅ Auto-calculation working
-   ✅ Documentation complete
-   ✅ Tests passing

**Ready to use!** 🎉

---

## 🚨 Important Notes

1. **Auto-Calculation**: Provide EITHER discount % OR yearly price (not both for auto-calc)
2. **Precision**: All monetary values use 2 decimal places
3. **Discounts**: Valid range 0-100%
4. **Currency**: Default USD, configurable per plan
5. **Yearly Plans**: Consider as separate plan entries for clear UI

---

**Happy Selling!** 💰
