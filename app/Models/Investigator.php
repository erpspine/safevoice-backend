<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Investigator extends BaseModel
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'company_id',
        'is_external',
        'external_name',
        'external_email',
        'external_phone',
        'external_organization',
        'specializations',
        'certification_number',
        'license_expiry',
        'hourly_rate',
        'availability_status',
        'bio',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_external' => 'boolean',
        'specializations' => 'array',
        'license_expiry' => 'date',
        'hourly_rate' => 'decimal:2',
        'status' => 'boolean',
    ];

    /**
     * Get the user associated with this investigator (for internal investigators).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the company that owns the investigator.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The many-to-many relationship: investigator can be assigned to multiple companies.
     */
    public function companies()
    {
        return $this->belongsToMany(Company::class, 'investigator_company', 'investigator_id', 'company_id')
            ->withTimestamps();
    }

    /**
     * Scope a query to only include active investigators.
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
     * Scope a query to get external investigators.
     */
    public function scopeExternal($query)
    {
        return $query->where('is_external', true);
    }

    /**
     * Scope a query to get internal investigators.
     */
    public function scopeInternal($query)
    {
        return $query->where('is_external', false);
    }

    /**
     * Scope a query to filter by availability status.
     */
    public function scopeAvailable($query)
    {
        return $query->where('availability_status', 'available');
    }

    /**
     * Get the investigator's display name.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->is_external) {
            return $this->external_name ?: 'External Investigator';
        }

        return $this->user ? $this->user->name : 'Internal Investigator';
    }

    /**
     * Get the investigator's contact email.
     */
    public function getContactEmailAttribute(): ?string
    {
        if ($this->is_external) {
            return $this->external_email;
        }

        return $this->user ? $this->user->email : null;
    }

    /**
     * Check if the investigator is available.
     */
    public function isAvailable(): bool
    {
        return $this->status && $this->availability_status === 'available';
    }

    /**
     * Get all assignments for this investigator.
     */
    public function assignments()
    {
        return $this->hasMany(CaseAssignment::class);
    }

    /**
     * Get all active assignments for this investigator.
     */
    public function activeAssignments()
    {
        return $this->assignments()->active();
    }

    /**
     * Get all cases assigned to this investigator.
     */
    public function cases()
    {
        return $this->hasManyThrough(
            CaseModel::class,
            CaseAssignment::class,
            'investigator_id',
            'id',
            'id',
            'case_id'
        )->where('case_assignments.status', 'active');
    }

    /**
     * Get current workload (active assignments count).
     */
    public function getCurrentWorkloadAttribute(): int
    {
        return $this->activeAssignments()->count();
    }
}
