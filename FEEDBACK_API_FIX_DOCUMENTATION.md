# Feedback Category API - Swahili Translation Fix

## Problem

The feedback category APIs were not working with Swahili translations:

-   `GET /api/companies/:companyId/feedback-categories/parents?language=en|sw`
-   `GET /api/companies/:companyId/feedback-categories/:parentId/subcategories?language=en|sw`

## Solution Applied

### 1. Database Migration

**File**: `2026_01_07_163957_add_swahili_translations_to_feedback_categories_table.php`

Added two new columns to `feedback_categories` table:

-   `name_sw` (string, nullable) - Swahili translation of category name
-   `description_sw` (text, nullable) - Swahili translation of description

### 2. Model Update

**File**: `app/Models/FeedbackCategory.php`

Updated `$fillable` array to include:

```php
'name_sw',
'description_sw',
```

### 3. Controller Updates

**File**: `app/Http/Controllers/Api/Admin/FeedbackCategoryController.php`

#### Updated Methods:

**a) publicParentCategories()**

-   Now accepts `Request $request` parameter
-   Reads `language` query parameter (defaults to 'en')
-   Validates language (must be 'en' or 'sw')
-   Returns localized category names and descriptions
-   Falls back to English if Swahili translation not available

**b) publicSubcategories()**

-   Now accepts `Request $request` parameter
-   Reads `language` query parameter (defaults to 'en')
-   Validates language (must be 'en' or 'sw')
-   Returns localized subcategory names and descriptions
-   Falls back to English if Swahili translation not available

## API Usage

### Get Parent Categories

#### English (Default)

```http
GET /api/companies/{companyId}/feedback-categories/parents
GET /api/companies/{companyId}/feedback-categories/parents?language=en
```

#### Swahili

```http
GET /api/companies/{companyId}/feedback-categories/parents?language=sw
```

#### Response Example (English)

```json
{
    "success": true,
    "message": "Parent feedback categories retrieved successfully.",
    "data": {
        "company_id": "01jf5...",
        "company_name": "Example Company",
        "categories": [
            {
                "id": "01jf6...",
                "name": "Customer Service",
                "description": "Feedback about customer service quality"
            },
            {
                "id": "01jf7...",
                "name": "Product Quality",
                "description": "Feedback about product quality"
            }
        ],
        "total": 2,
        "language": "en"
    }
}
```

#### Response Example (Swahili)

```json
{
    "success": true,
    "message": "Aina kuu za maoni zimepatikana",
    "data": {
        "company_id": "01jf5...",
        "company_name": "Example Company",
        "categories": [
            {
                "id": "01jf6...",
                "name": "Huduma kwa Wateja",
                "description": "Maoni kuhusu ubora wa huduma kwa wateja"
            },
            {
                "id": "01jf7...",
                "name": "Ubora wa Bidhaa",
                "description": "Maoni kuhusu ubora wa bidhaa"
            }
        ],
        "total": 2,
        "language": "sw"
    }
}
```

### Get Subcategories

#### English (Default)

```http
GET /api/companies/{companyId}/feedback-categories/{parentId}/subcategories
GET /api/companies/{companyId}/feedback-categories/{parentId}/subcategories?language=en
```

#### Swahili

```http
GET /api/companies/{companyId}/feedback-categories/{parentId}/subcategories?language=sw
```

#### Response Example (English)

```json
{
    "success": true,
    "message": "Feedback subcategories retrieved successfully.",
    "data": {
        "company_id": "01jf5...",
        "parent_category": {
            "id": "01jf6...",
            "name": "Customer Service"
        },
        "subcategories": [
            {
                "id": "01jf8...",
                "name": "Response Time",
                "description": "How quickly we respond to inquiries"
            },
            {
                "id": "01jf9...",
                "name": "Staff Attitude",
                "description": "Friendliness and professionalism of staff"
            }
        ],
        "total": 2,
        "language": "en"
    }
}
```

#### Response Example (Swahili)

```json
{
    "success": true,
    "message": "Aina ndogo za maoni zimepatikana",
    "data": {
        "company_id": "01jf5...",
        "parent_category": {
            "id": "01jf6...",
            "name": "Huduma kwa Wateja"
        },
        "subcategories": [
            {
                "id": "01jf8...",
                "name": "Muda wa Majibu",
                "description": "Kasi tunayojibu maswali"
            },
            {
                "id": "01jf9...",
                "name": "Tabia ya Wafanyakazi",
                "description": "Urafiki na utaalamu wa wafanyakazi"
            }
        ],
        "total": 2,
        "language": "sw"
    }
}
```

## Error Responses

### Invalid Language

```json
{
    "success": false,
    "message": "Invalid language. Use \"en\" or \"sw\"."
}
```

**Status Code**: 400

### Company Not Found

```json
{
    "success": false,
    "message": "Company not found or inactive."
}
```

**Status Code**: 404

### Parent Category Not Found

```json
{
    "success": false,
    "message": "Company or parent category not found."
}
```

**Status Code**: 404

## Features

✅ **Language Support**: Full English and Swahili translation support  
✅ **Fallback Mechanism**: Automatically falls back to English if Swahili not available  
✅ **Validation**: Validates language parameter  
✅ **Backward Compatible**: Works with existing data (no Swahili translations required)  
✅ **Error Logging**: Comprehensive error logging for debugging  
✅ **Clean Response**: Localized messages based on language

## Next Steps for Companies

To add Swahili translations to your feedback categories:

1. **Via Admin Panel**: Update categories through the admin interface
2. **Via API**: Use the update endpoint with `name_sw` and `description_sw` fields
3. **Via Database**: Directly update the `name_sw` and `description_sw` columns

### Example Update Request

```http
PUT /api/admin/feedback-categories/{id}
Content-Type: application/json

{
  "name": "Customer Service",
  "name_sw": "Huduma kwa Wateja",
  "description": "Feedback about customer service quality",
  "description_sw": "Maoni kuhusu ubora wa huduma kwa wateja"
}
```

## Testing

### Test English API

```bash
curl -X GET "http://yourapi.com/api/companies/{companyId}/feedback-categories/parents?language=en" \
  -H "Accept: application/json"
```

### Test Swahili API

```bash
curl -X GET "http://yourapi.com/api/companies/{companyId}/feedback-categories/parents?language=sw" \
  -H "Accept: application/json"
```

## Migration Status

✅ Migration completed successfully  
✅ Columns added to `feedback_categories` table  
✅ Model updated  
✅ Controllers updated  
✅ APIs ready to use

---

**Date Fixed**: January 7, 2026  
**Version**: 1.0  
**Status**: ✅ Working
