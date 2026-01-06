<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class SectorDepartmentTemplate extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sector_department_templates';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'sector',
        'department_code',
        'department_name',
        'description',
        'status',
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
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Available sectors for templates.
     */
    public const SECTORS = [
        'education' => 'Education',
        'corporate_workplace' => 'Corporate & Workplace',
        'financial_insurance' => 'Financial & Insurance',
        'healthcare' => 'Healthcare',
        'manufacturing_industrial' => 'Manufacturing & Industrial',
        'construction_engineering' => 'Construction & Engineering',
        'security_uniformed_services' => 'Security & Uniformed Services',
        'hospitality_travel_tourism' => 'Hospitality, Travel & Tourism',
        'ngo_cso_donor_funded' => 'NGO, CSO & Donor-Funded',
        'religious_institutions' => 'Religious Institutions',
        'transport_logistics' => 'Transport & Logistics',
        'government_public_sector' => 'Government & Public Sector',
    ];

    /**
     * Get all templates for a specific sector.
     */
    public static function getBySector(string $sector): Collection
    {
        return static::where('sector', $sector)
            ->where('status', true)
            ->orderBy('sort_order')
            ->orderBy('department_name')
            ->get();
    }

    /**
     * Get all templates grouped by sector.
     */
    public static function getGroupedBySector(): Collection
    {
        return static::where('status', true)
            ->orderBy('sector')
            ->orderBy('sort_order')
            ->orderBy('department_name')
            ->get()
            ->groupBy('sector');
    }

    /**
     * Scope a query to only include templates for a specific sector.
     */
    public function scopeBySector($query, string $sector)
    {
        return $query->where('sector', $sector);
    }

    /**
     * Scope a query to only include active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Get the sector display name.
     */
    public function getSectorNameAttribute(): string
    {
        return self::SECTORS[$this->sector] ?? $this->sector;
    }
}
