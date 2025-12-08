<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IncidentCategory extends BaseModel
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'status',
        'description',
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
     * Get the company that owns the incident category.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the routing rules for this incident category.
     */
    public function routingRules(): HasMany
    {
        return $this->hasMany(RoutingRule::class, 'category_id')
            ->where('type', 'incident');
    }

    /**
     * Get all case category assignments.
     */
    public function caseCategories(): HasMany
    {
        return $this->hasMany(CaseCategory::class, 'category_id')
            ->where('category_type', 'incident');
    }

    /**
     * Get all cases assigned to this incident category (many-to-many).
     */
    public function assignedCases()
    {
        return $this->belongsToMany(
            CaseModel::class,
            'case_categories',
            'category_id',
            'case_id'
        )->wherePivot('category_type', 'incident')
            ->withPivot(['assigned_at', 'assigned_by', 'assignment_note', 'category_type'])
            ->withTimestamps();
    }

    /**
     * Scope a query to only include active incident categories.
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope a query to filter by company.
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
