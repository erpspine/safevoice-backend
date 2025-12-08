<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends BaseModel
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
     * Get the company that owns the department.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the cases for this department.
     */
    public function cases(): HasMany
    {
        return $this->hasMany(CaseModel::class);
    }

    /**
     * Get the routing rules for this department.
     */
    public function routingRules(): HasMany
    {
        return $this->hasMany(RoutingRule::class);
    }

    /**
     * Get all case department assignments.
     */
    public function caseDepartments(): HasMany
    {
        return $this->hasMany(CaseDepartment::class, 'department_id');
    }

    /**
     * Get all cases assigned to this department (many-to-many).
     */
    public function assignedCases()
    {
        return $this->belongsToMany(
            CaseModel::class,
            'case_departments',
            'department_id',
            'case_id'
        )->withPivot(['assigned_at', 'assigned_by', 'assignment_note'])
            ->withTimestamps();
    }

    /**
     * Scope a query to only include active departments.
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
