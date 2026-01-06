<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SectorFeedbackTemplate extends BaseModel
{
    use HasFactory, HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'sector',
        'category_key',
        'category_name',
        'subcategory_name',
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
    ];

    /**
     * Get templates by sector.
     */
    public static function getBySector(string $sector): array
    {
        $templates = self::where('sector', $sector)
            ->where('status', true)
            ->orderBy('sort_order')
            ->orderBy('category_name')
            ->orderBy('subcategory_name')
            ->get();

        // Group by category
        $grouped = [];
        foreach ($templates as $template) {
            $key = $template->category_key;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'category_key' => $key,
                    'category_name' => $template->category_name,
                    'subcategories' => [],
                ];
            }
            if ($template->subcategory_name) {
                $grouped[$key]['subcategories'][] = $template->subcategory_name;
            }
        }

        return array_values($grouped);
    }

    /**
     * Scope to filter by sector.
     */
    public function scopeBySector($query, string $sector)
    {
        return $query->where('sector', $sector);
    }

    /**
     * Scope to filter active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}
