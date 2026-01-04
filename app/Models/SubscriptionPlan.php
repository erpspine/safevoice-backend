<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionPlan extends BaseModel
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'price',
        'currency',
        'billing_period',
        'yearly_price',
        'discount_amount',
        'discount_percentage',
        'amount_saved',
        'grace_days',
        'description',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'amount_saved' => 'decimal:2',
        'grace_days' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the companies using this subscription plan.
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'plan_id');
    }

    /**
     * Get the payments for this subscription plan.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Scope to get only active plans.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by price.
     */
    public function scopeOrderByPrice($query, $direction = 'asc')
    {
        return $query->orderBy('price', $direction);
    }

    /**
     * Get monthly price.
     */
    public function getMonthlyPrice(): float
    {
        return (float) $this->price;
    }

    /**
     * Get yearly price (with discount if yearly pricing is set).
     */
    public function getYearlyPrice(): float
    {
        if ($this->yearly_price) {
            return (float) $this->yearly_price;
        }
        // If no yearly price set, calculate 12 months of monthly price
        return $this->getMonthlyPrice() * 12;
    }

    /**
     * Get effective discount percentage.
     */
    public function getDiscountPercentage(): float
    {
        if ($this->discount_percentage) {
            return (float) $this->discount_percentage;
        }

        if ($this->discount_amount) {
            $monthlyTotal = $this->getMonthlyPrice() * 12;
            if ($monthlyTotal > 0) {
                return round(($this->discount_amount / $monthlyTotal) * 100, 2);
            }
        }

        // Calculate discount percentage from yearly price
        $monthlyTotal = $this->getMonthlyPrice() * 12;
        if ($monthlyTotal > 0 && $this->yearly_price) {
            $discount = $monthlyTotal - $this->yearly_price;
            return round(($discount / $monthlyTotal) * 100, 2);
        }

        return 0.0;
    }

    /**
     * Get effective discount amount.
     */
    public function getDiscountAmount(): float
    {
        if ($this->discount_amount) {
            return (float) $this->discount_amount;
        }

        $monthlyTotal = $this->getMonthlyPrice() * 12;
        if ($this->yearly_price) {
            return max(0, $monthlyTotal - $this->yearly_price);
        }

        // Calculate from discount percentage
        if ($this->discount_percentage && $this->discount_percentage > 0) {
            return round($monthlyTotal * ($this->discount_percentage / 100), 2);
        }

        return 0.0;
    }

    /**
     * Get amount saved with yearly plan.
     */
    public function getAmountSaved(): float
    {
        if ($this->amount_saved) {
            return (float) $this->amount_saved;
        }

        return $this->getDiscountAmount();
    }

    /**
     * Calculate yearly pricing from monthly with optional discount.
     */
    public function calculateYearlyPricing(float $discountPercentage = null): array
    {
        $monthlyTotal = $this->getMonthlyPrice() * 12;

        if ($discountPercentage === null) {
            $discountPercentage = $this->getDiscountPercentage();
        }

        $discountAmount = round($monthlyTotal * ($discountPercentage / 100), 2);
        $yearlyPrice = round($monthlyTotal - $discountAmount, 2);

        return [
            'monthly_price' => $this->getMonthlyPrice(),
            'monthly_total_12_months' => $monthlyTotal,
            'discount_percentage' => $discountPercentage,
            'discount_amount' => $discountAmount,
            'yearly_price' => $yearlyPrice,
            'amount_saved' => $discountAmount,
        ];
    }
}
