<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaseCategory extends BaseModel
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'case_id',
        'category_id',
        'parent_category_id',
        'category_type',
        'categorization_source',
        'is_primary',
        'confidence_level',
        'is_verified',
        'verified_by',
        'verified_at',
        'assigned_at',
        'assigned_by',
        'assignment_note',
        'recategorization_reason',
        'original_category_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'assigned_at' => 'datetime',
        'verified_at' => 'datetime',
        'is_primary' => 'boolean',
        'is_verified' => 'boolean',
    ];

    /**
     * Get the case that this category assignment belongs to.
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    /**
     * Get the category (polymorphic - can be IncidentCategory or FeedbackCategory).
     */
    public function category(): MorphTo
    {
        return $this->morphTo('category', 'category_type', 'category_id');
    }

    /**
     * Get the incident category.
     */
    public function incidentCategory(): BelongsTo
    {
        return $this->belongsTo(IncidentCategory::class, 'category_id');
    }

    /**
     * Get the parent incident category.
     */
    public function parentIncidentCategory(): BelongsTo
    {
        return $this->belongsTo(IncidentCategory::class, 'parent_category_id');
    }

    /**
     * Get the feedback category.
     */
    public function feedbackCategory(): BelongsTo
    {
        return $this->belongsTo(FeedbackCategory::class, 'category_id');
    }

    /**
     * Get the original category (before recategorization).
     */
    public function originalCategory(): BelongsTo
    {
        return $this->belongsTo(IncidentCategory::class, 'original_category_id');
    }

    /**
     * Get the user who assigned the category.
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Get the user who verified the category.
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Scope a query to only include incident categories.
     */
    public function scopeIncidentCategories($query)
    {
        return $query->where('category_type', 'incident');
    }

    /**
     * Scope a query to only include feedback categories.
     */
    public function scopeFeedbackCategories($query)
    {
        return $query->where('category_type', 'feedback');
    }

    /**
     * Scope a query to only include user-selected categories.
     */
    public function scopeUserSelected($query)
    {
        return $query->where('categorization_source', 'user');
    }

    /**
     * Scope a query to only include company-assigned categories.
     */
    public function scopeCompanyAssigned($query)
    {
        return $query->where('categorization_source', 'company');
    }

    /**
     * Scope a query to only include branch-assigned categories.
     */
    public function scopeBranchAssigned($query)
    {
        return $query->where('categorization_source', 'branch');
    }

    /**
     * Scope a query to only include primary categories.
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope a query to only include verified categories.
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Check if this categorization was recategorized from user's original selection.
     */
    public function wasRecategorized(): bool
    {
        return !is_null($this->original_category_id);
    }
}
