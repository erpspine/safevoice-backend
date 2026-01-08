<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class SectorIncidentTemplate extends BaseModel
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'sector',
        'category_key',
        'category_name',
        'category_name_sw',
        'subcategory_name',
        'subcategory_name_sw',
        'description',
        'description_sw',
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
     * Get templates by sector with localization support.
     * 
     * @param string $sector The sector to filter
     * @param string $language Language code ('en' or 'sw')
     * @return array
     */
    public static function getBySector(string $sector, string $language = 'en'): array
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
                    'category_name' => $template->getLocalizedField('category_name', $language),
                    'description' => $template->getLocalizedField('description', $language),
                    'subcategories' => [],
                ];
            }
            if ($template->subcategory_name) {
                $grouped[$key]['subcategories'][] = $template->getLocalizedField('subcategory_name', $language);
            }
        }

        return array_values($grouped);
    }

    /**
     * Get localized field value based on language.
     * 
     * @param string $field The base field name
     * @param string $language Language code ('en' or 'sw')
     * @return string|null
     */
    public function getLocalizedField(string $field, string $language = 'en'): ?string
    {
        if ($language === 'sw') {
            $swahiliField = $field . '_sw';
            return $this->$swahiliField ?? $this->$field;
        }

        return $this->$field;
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
