# Feedback Category Seeder with Swahili Translation

## Overview

This implementation adds comprehensive Swahili translation support to the Feedback Category system across all 11 sectors in the SafeVoice backend.

## Implementation Summary

### 1. Database Schema Updates

**Migration File**: `2026_01_07_155027_add_swahili_translations_to_sector_feedback_templates_table.php`

Added three new columns to the `sector_feedback_templates` table:

-   `category_name_sw` - Swahili translation for category names
-   `subcategory_name_sw` - Swahili translation for subcategory names
-   `description_sw` - Swahili translation for descriptions

All columns are nullable to maintain backward compatibility.

### 2. Model Updates

**File**: `app/Models/SectorFeedbackTemplate.php`

#### Added Fillable Fields:

```php
'category_name_sw',
'subcategory_name_sw',
'description_sw',
```

#### New Method: `getLocalizedField()`

Returns the appropriate field value based on the requested language:

```php
public function getLocalizedField(string $field, string $language = 'en'): ?string
{
    if ($language === 'sw') {
        $swahiliField = $field . '_sw';
        return $this->$swahiliField ?? $this->$field;
    }
    return $this->$field;
}
```

#### Updated Method: `getBySector()`

Now accepts a language parameter and returns localized content:

```php
public static function getBySector(string $sector, string $language = 'en'): array
```

### 3. Seeder with Swahili Translations

**File**: `database/seeders/SectorFeedbackTemplateSeederWithSwahili.php`

Comprehensive seeder containing:

-   ✅ All 11 sectors fully translated
-   ✅ All main categories with Swahili names and descriptions
-   ✅ All subcategories translated to Swahili
-   ✅ 400+ individual translations

#### Sectors Covered:

1. **Education** (Elimu)
2. **Corporate/Workplace** (Mahali pa Kazi)
3. **Financial & Insurance** (Fedha na Bima)
4. **Healthcare** (Huduma za Afya)
5. **Manufacturing & Industrial** (Viwanda)
6. **Construction & Engineering** (Ujenzi)
7. **Security & Uniformed Services** (Ulinzi)
8. **Hospitality, Travel & Tourism** (Utalii)
9. **NGO/CSO/Donor Funded** (Mashirika yasiyo ya Kiserikali)
10. **Religious Institutions** (Taasisi za Kidini)
11. **Transport & Logistics** (Usafiri)

## Usage Examples

### 1. Get Feedback Categories in English (Default)

```php
$categories = SectorFeedbackTemplate::getBySector('education');
// Returns categories with English names
```

### 2. Get Feedback Categories in Swahili

```php
$categories = SectorFeedbackTemplate::getBySector('education', 'sw');
// Returns categories with Swahili names
```

### 3. Get Localized Field Value

```php
$template = SectorFeedbackTemplate::find($id);
$categoryName = $template->getLocalizedField('category_name', 'sw');
// Returns Swahili name if available, falls back to English
```

### 4. API Controller Implementation Example

```php
public function getFeedbackCategories(Request $request, $sector)
{
    $language = $request->input('language', 'en'); // Default to English

    $categories = SectorFeedbackTemplate::getBySector($sector, $language);

    return response()->json([
        'success' => true,
        'data' => $categories,
        'language' => $language
    ]);
}
```

## Translation Coverage

### Sample Translations by Sector:

#### Education Sector

| English                | Swahili                   |
| ---------------------- | ------------------------- |
| Teaching Quality       | Ubora wa Ufundishaji      |
| Facilities & Resources | Miundombinu na Rasilimali |
| Student Services       | Huduma za Wanafunzi       |
| Campus Environment     | Mazingira ya Kampasi      |

#### Healthcare Sector

| English                 | Swahili              |
| ----------------------- | -------------------- |
| Patient Care            | Huduma kwa Wagonjwa  |
| Facilities & Equipment  | Miundombinu na Vifaa |
| Administrative Services | Huduma za Utawala    |
| Communication           | Mawasiliano          |

#### Corporate/Workplace Sector

| English                  | Swahili                |
| ------------------------ | ---------------------- |
| Work Environment         | Mazingira ya Kazi      |
| Management & Leadership  | Usimamizi na Uongozi   |
| Compensation & Benefits  | Malipo na Manufaa      |
| Professional Development | Maendeleo ya Kitaaluma |

## API Request/Response Examples

### Request with Language Parameter

```http
GET /api/feedback-categories/education?language=sw
```

### Response (Swahili)

```json
{
    "success": true,
    "data": [
        {
            "category_key": "teaching_quality",
            "category_name": "Ubora wa Ufundishaji",
            "description": "Maoni yanayohusu ubora wa ufundishaji na maelekezo",
            "subcategories": [
                "Umuhimu wa maudhui ya kozi",
                "Ufanisi wa njia za ufundishaji",
                "Ushirikishwaji wa mwalimu",
                "Usawa wa tathmini",
                "Ubora wa vifaa vya kujifunzia"
            ]
        }
    ],
    "language": "sw"
}
```

### Request without Language Parameter (Default English)

```http
GET /api/feedback-categories/education
```

### Response (English)

```json
{
    "success": true,
    "data": [
        {
            "category_key": "teaching_quality",
            "category_name": "Teaching Quality",
            "description": "Feedback related to teaching and instruction quality",
            "subcategories": [
                "Course content relevance",
                "Teaching methods effectiveness",
                "Instructor engagement",
                "Assessment fairness",
                "Learning materials quality"
            ]
        }
    ],
    "language": "en"
}
```

## Running the Implementation

### Step 1: Run Migration

```bash
php artisan migrate
```

### Step 2: Seed Database with Swahili Translations

```bash
php artisan db:seed --class=SectorFeedbackTemplateSeederWithSwahili
```

### Step 3: Verify in Database

```sql
SELECT
    category_name,
    category_name_sw,
    subcategory_name,
    subcategory_name_sw
FROM sector_feedback_templates
WHERE sector = 'education'
LIMIT 5;
```

## Best Practices

1. **Fallback Mechanism**: Always fallback to English if Swahili translation is not available
2. **Language Code**: Use ISO 639-1 codes ('en' for English, 'sw' for Swahili)
3. **API Design**: Accept language parameter in request query string or headers
4. **Validation**: Validate language codes to prevent invalid inputs
5. **Caching**: Consider caching translated results for better performance

## Future Enhancements

1. **Additional Languages**: Structure supports easy addition of more languages
2. **Translation Management**: Consider admin interface for translation updates
3. **Language Detection**: Automatic language detection based on user preferences
4. **Translation Quality**: Professional review of all Swahili translations
5. **Missing Translations Report**: Tool to identify untranslated content

## Testing Recommendations

### Unit Tests

```php
public function test_get_sector_categories_in_english()
{
    $categories = SectorFeedbackTemplate::getBySector('education', 'en');
    $this->assertNotEmpty($categories);
    $this->assertEquals('Teaching Quality', $categories[0]['category_name']);
}

public function test_get_sector_categories_in_swahili()
{
    $categories = SectorFeedbackTemplate::getBySector('education', 'sw');
    $this->assertNotEmpty($categories);
    $this->assertEquals('Ubora wa Ufundishaji', $categories[0]['category_name']);
}

public function test_fallback_to_english_when_swahili_missing()
{
    $template = SectorFeedbackTemplate::first();
    $template->category_name_sw = null;
    $template->save();

    $localizedName = $template->getLocalizedField('category_name', 'sw');
    $this->assertEquals($template->category_name, $localizedName);
}
```

## Benefits

✅ **Improved Accessibility**: Users can interact in their preferred language  
✅ **Better User Experience**: Localized content increases engagement  
✅ **Scalable Solution**: Easy to add more languages in the future  
✅ **Backward Compatible**: Existing functionality remains unchanged  
✅ **Professional Quality**: Comprehensive translations across all sectors  
✅ **Maintainable Code**: Clean, well-documented implementation

## Support

For questions or issues related to the translation implementation, please contact the development team or refer to the Laravel localization documentation.

---

**Last Updated**: January 7, 2026  
**Version**: 1.0  
**Author**: SafeVoice Development Team
