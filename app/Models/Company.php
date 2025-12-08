<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends BaseModel
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'contact',
        'logo',
        'status',
        'plan',
        'plan_id',
        'address',
        'website',
        'description',
        'tax_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * The attributes that should be appended to the model's array form.
     *
     * @var array
     */
    protected $appends = ['logo_url'];

    /**
     * Get the logo URL attribute.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo) {
            return null;
        }

        return url('storage/' . $this->logo);
    }

    /**
     * Get the subscription plan for the company.
     */
    public function subscriptionPlan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /**
     * Get the branches for the company.
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * Get the departments for the company.
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /**
     * Get the incident categories for the company.
     */
    public function incidentCategories(): HasMany
    {
        return $this->hasMany(IncidentCategory::class);
    }

    /**
     * Get the feedback categories for the company.
     */
    public function feedbackCategories(): HasMany
    {
        return $this->hasMany(FeedbackCategory::class);
    }

    /**
     * Get the investigators for the company.
     */
    public function investigators(): HasMany
    {
        return $this->hasMany(Investigator::class);
    }

    /**
     * Many-to-many relationship: investigators assigned to this company (external/internal assignments).
     */
    public function assignedInvestigators()
    {
        return $this->belongsToMany(Investigator::class, 'investigator_company', 'company_id', 'investigator_id')
            ->withTimestamps();
    }

    /**
     * Get the cases for the company.
     */
    public function cases(): HasMany
    {
        return $this->hasMany(CaseModel::class);
    }

    /**
     * Get the routing rules for the company.
     */
    public function routingRules(): HasMany
    {
        return $this->hasMany(RoutingRule::class);
    }

    /**
     * Get the users associated with this company.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the payments for the company.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get all subscriptions for the company.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the active subscription for the company.
     */
    public function activeSubscription()
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->where('starts_on', '<=', now())
            ->where('ends_on', '>=', now())
            ->latest('ends_on')
            ->first();
    }

    /**
     * Scope a query to only include active companies.
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope a query to filter by plan.
     */
    public function scopePlan($query, $plan)
    {
        return $query->where('plan', $plan);
    }
}
