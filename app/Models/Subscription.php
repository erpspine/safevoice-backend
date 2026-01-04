<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Subscription extends Model
{
    protected $fillable = [
        'company_id',
        'plan_id',
        'starts_on',
        'ends_on',
        'grace_until',
        'auto_renew',
        'renewal_method',
        'renewal_token',
        'cancel_at_period_end',
        'status',
        'billing_period',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'grace_until' => 'date',
        'auto_renew' => 'boolean',
        'cancel_at_period_end' => 'boolean',
        'billing_period' => 'string',
    ];

    /**
     * Get the company that owns the subscription.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the subscription plan.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /**
     * Get the payments for this subscription.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the branches activated by this subscription.
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'subscription_branch')
            ->withPivot(['activated_from', 'activated_until'])
            ->withTimestamps();
    }

    /**
     * Scope to get active subscriptions.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get subscriptions in grace period.
     */
    public function scopeInGrace($query)
    {
        return $query->where('status', 'in_grace');
    }

    /**
     * Scope to get expired subscriptions.
     */
    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    /**
     * Check if subscription is currently active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active' &&
            now()->between($this->starts_on, $this->ends_on);
    }

    /**
     * Check if subscription is in grace period.
     */
    public function isInGrace(): bool
    {
        return $this->status === 'in_grace' &&
            $this->grace_until &&
            now()->between(Carbon::parse($this->ends_on)->addDay(), $this->grace_until);
    }

    /**
     * Check if subscription has expired.
     */
    public function hasExpired(): bool
    {
        return now()->gt($this->grace_until ?? $this->ends_on);
    }

    /**
     * Get the days remaining in the subscription.
     */
    public function getDaysRemainingAttribute(): int
    {
        if ($this->hasExpired()) {
            return 0;
        }

        $endDate = $this->isInGrace() ? $this->grace_until : $this->ends_on;
        return now()->diffInDays($endDate, false);
    }

    /**
     * Get the subscription status with context.
     */
    public function getStatusWithContextAttribute(): string
    {
        if ($this->isActive()) {
            return 'active';
        }

        if ($this->isInGrace()) {
            return 'in_grace';
        }

        if ($this->hasExpired()) {
            return 'expired';
        }

        return $this->status;
    }
}
