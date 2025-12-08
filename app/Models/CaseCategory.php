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
        'category_type',
        'assigned_at',
        'assigned_by',
        'assignment_note',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'assigned_at' => 'datetime',
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
     * Get the feedback category.
     */
    public function feedbackCategory(): BelongsTo
    {
        return $this->belongsTo(FeedbackCategory::class, 'category_id');
    }

    /**
     * Get the user who assigned the category.
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
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
}
