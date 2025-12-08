<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasUlids;

    protected $fillable = [
        'company_id',
        'subscription_plan_id',
        'subscription_id',
        'duration_months',
        'amount_paid',
        'payment_method',
        'payment_reference',
        'period_start',
        'period_end',
        'status',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
        'duration_months' => 'integer',
    ];

    /**
     * Get the company that owns the payment.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the subscription plan that this payment is for.
     */
    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    /**
     * Scope a query to only include successful payments.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to only include pending payments.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include failed payments.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope a query to filter by payment method.
     */
    public function scopeByPaymentMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeInPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('period_start', [$startDate, $endDate]);
    }

    /**
     * Check if the payment is active (within the subscription period).
     */
    public function isActive(): bool
    {
        return $this->status === 'completed' &&
            now()->between($this->period_start, $this->period_end);
    }

    /**
     * Check if the payment period has expired.
     */
    public function isExpired(): bool
    {
        return now()->gt($this->period_end);
    }

    /**
     * Get the remaining days in the subscription period.
     */
    public function getRemainingDaysAttribute(): int
    {
        if ($this->isExpired()) {
            return 0;
        }

        return now()->diffInDays($this->period_end, false);
    }
}
