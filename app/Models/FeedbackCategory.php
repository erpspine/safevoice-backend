<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeedbackCategory extends BaseModel
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'parent_id',
        'name',
        'name_sw',
        'category_key',
        'status',
        'description',
        'description_sw',
        'sort_order',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the company that owns the feedback category.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the parent category.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(FeedbackCategory::class, 'parent_id');
    }

    /**
     * Get the child categories (subcategories).
     */
    public function children(): HasMany
    {
        return $this->hasMany(FeedbackCategory::class, 'parent_id');
    }

    /**
     * Get all descendants (recursive children).
     */
    public function descendants()
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get the routing rules for this feedback category.
     */
    public function routingRules(): HasMany
    {
        return $this->hasMany(RoutingRule::class, 'category_id')
            ->where('type', 'feedback');
    }

    /**
     * Get all case category assignments.
     */
    public function caseCategories(): HasMany
    {
        return $this->hasMany(CaseCategory::class, 'category_id')
            ->where('category_type', 'feedback');
    }

    /**
     * Get all cases assigned to this feedback category (many-to-many).
     */
    public function assignedCases()
    {
        return $this->belongsToMany(
            CaseModel::class,
            'case_categories',
            'category_id',
            'case_id'
        )->wherePivot('category_type', 'feedback')
            ->withPivot(['assigned_at', 'assigned_by', 'assignment_note', 'category_type'])
            ->withTimestamps();
    }

    /**
     * Scope a query to only include active feedback categories.
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

    /**
     * Scope to only get parent (root) categories.
     */
    public function scopeParents($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope to only get child (sub) categories.
     */
    public function scopeChildren($query)
    {
        return $query->whereNotNull('parent_id');
    }

    /**
     * Check if this is a parent category.
     */
    public function isParent(): bool
    {
        return is_null($this->parent_id);
    }

    /**
     * Check if this is a child category.
     */
    public function isChild(): bool
    {
        return !is_null($this->parent_id);
    }

    /**
     * Get the full path name (Parent > Child).
     */
    public function getFullNameAttribute(): string
    {
        if ($this->parent) {
            return $this->parent->name . ' > ' . $this->name;
        }
        return $this->name;
    }
}
