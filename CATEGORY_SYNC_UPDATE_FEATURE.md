# Category Sync Update Feature

## Overview

The category sync system has been enhanced to **automatically update existing categories** with the latest content from sector templates, including Swahili translations. Previously, existing categories were only preserved without updates.

## Problem Solved

**Before:** When a company's sector templates were updated with new Swahili translations or content changes, existing categories would not reflect these updates. Categories were only created or removed, never updated.

**After:** The sync system now detects changes between existing categories and their templates, automatically updating categories with the latest content including:

-   Name changes (English and Swahili)
-   Description changes (English and Swahili)
-   Sort order changes
-   Parent relationship changes (for subcategories)

---

## When Updates Occur

Category updates are triggered automatically when:

1. **Company sector changes** - Full sync with new sector's templates
2. **Company has incomplete categories** - Sync detects missing categories
3. **Manual sync operation** - Via service methods

### Company Update Triggers

The `CompanyController::update()` method automatically triggers sync when:

```php
// Sync occurs if:
// 1. Sector changed (from one value to another)
// 2. Sector exists but company has no categories (initial sync)
// 3. Sector was set from null to a value
// 4. Company has fewer categories than templates (partial sync needed)
```

---

## Updated Services

### IncidentCategoryService

**Method:** `syncCategoriesFromSector(Company $company)`

**Updated Logic:**

```php
// For existing categories:
if ($existingParent) {
    // 1. Restore if soft-deleted
    if ($existingParent->trashed()) {
        $existingParent->restore();
    }

    // 2. Check for changes and update
    $updatedFields = [];
    if ($existingParent->name !== $categoryData['name']) {
        $updatedFields['name'] = $categoryData['name'];
    }
    if ($existingParent->name_sw !== ($categoryData['name_sw'] ?? null)) {
        $updatedFields['name_sw'] = $categoryData['name_sw'] ?? null;
    }
    // ... check description, description_sw, sort_order

    if (!empty($updatedFields)) {
        $existingParent->update($updatedFields);
        // Track as updated
    }
}
```

**Sync Result Structure:**

```php
[
    'added' => [],      // New categories created
    'updated' => [],    // Existing categories updated with template changes
    'removed' => [],    // Template-based categories no longer in templates
    'preserved' => [],  // Categories that match templates (no changes)
    'message' => 'Sync complete: X added, Y updated, Z removed, W preserved'
]
```

### FeedbackCategoryService

**Method:** `syncCategoriesFromSector(Company $company)`

Same logic as IncidentCategoryService, applied to feedback categories.

---

## Fields That Are Updated

### Parent Categories (Both Incident & Feedback)

| Field            | Description                     | Updated |
| ---------------- | ------------------------------- | ------- |
| `name`           | English name                    | ✅      |
| `name_sw`        | Swahili name                    | ✅      |
| `description`    | English description             | ✅      |
| `description_sw` | Swahili description             | ✅      |
| `sort_order`     | Display order                   | ✅      |
| `category_key`   | Template identifier (immutable) | ❌      |
| `company_id`     | Company association (immutable) | ❌      |

### Subcategories

| Field          | Description                     | Updated |
| -------------- | ------------------------------- | ------- |
| `name`         | English name                    | ✅      |
| `name_sw`      | Swahili name                    | ✅      |
| `parent_id`    | Parent category reference       | ✅      |
| `sort_order`   | Display order                   | ✅      |
| `category_key` | Template identifier (immutable) | ❌      |

---

## Custom Categories Protection

Categories created manually by users (without a `category_key`) are **never modified** during sync. They are preserved as custom categories.

```php
// Custom categories are skipped
if (empty($category->category_key)) {
    $result['preserved'][] = [
        'type' => $category->parent_id ? 'subcategory' : 'parent',
        'name' => $category->name,
        'reason' => 'custom_category',
    ];
    continue;
}
```

---

## Example Scenarios

### Scenario 1: Template Translation Added

**Template Before:**

```php
[
    'name' => 'Academic Misconduct',
    'name_sw' => null,  // No translation
]
```

**Template After:**

```php
[
    'name' => 'Academic Misconduct',
    'name_sw' => 'Tabia Mbaya za Kitaaluma',  // Translation added
]
```

**Result:** Existing category automatically updated with Swahili translation.

---

### Scenario 2: Description Updated

**Template Before:**

```php
[
    'name' => 'Safety Violations',
    'description' => 'Safety issues',
    'description_sw' => null,
]
```

**Template After:**

```php
[
    'name' => 'Safety Violations',
    'description' => 'Issues related to workplace safety violations',
    'description_sw' => 'Masuala yanayohusu ukiukaji wa usalama kazini',
]
```

**Result:** Category updated with enhanced descriptions in both languages.

---

### Scenario 3: Company Sector Change

**Before:** Company sector = `education`

-   Has 37 incident categories from education templates
-   All categories have English + Swahili content

**Change:** Company sector updated to `corporate_workplace`

**Result:**

1. Old education categories soft-deleted (template-based only)
2. New corporate categories created with full bilingual content
3. Custom categories preserved
4. Response includes sync details

---

## API Response Example

When updating a company and triggering sync:

```json
{
    "success": true,
    "message": "Company updated successfully",
    "data": {
        "company": { ... },
        "category_sync": {
            "added": [],
            "updated": [
                {
                    "type": "parent",
                    "name": "Academic Misconduct",
                    "fields": ["name_sw", "description_sw"]
                }
            ],
            "removed": [],
            "preserved": [
                {
                    "type": "parent",
                    "name": "Bullying and Harassment"
                }
            ],
            "message": "Sync complete: 0 added, 1 updated, 0 removed, 36 preserved"
        },
        "feedback_category_sync": {
            "added": [],
            "updated": [
                {
                    "type": "parent",
                    "name": "Teaching Quality",
                    "fields": ["description_sw"]
                }
            ],
            "removed": [],
            "preserved": [ ... ],
            "message": "Sync completed: 0 added, 1 updated, 0 removed, 23 preserved"
        }
    }
}
```

---

## Testing

### Test Script: `test_category_sync_update.php`

**What It Tests:**

1. Initial category creation from templates
2. Category update detection and execution
3. Swahili translation updates
4. Company update trigger behavior
5. Sector change full sync

**Run Test:**

```bash
php test_category_sync_update.php
```

**Expected Output:**

```
✅ SUCCESS: Categories were updated with latest template content
Updated categories:
  - parent: Academic Misconduct (fields: name_sw, description_sw)
  - parent: Teaching Quality (fields: name_sw, description_sw)
```

---

## Implementation Files

| File                                       | Changes                                            |
| ------------------------------------------ | -------------------------------------------------- |
| `app/Services/IncidentCategoryService.php` | Added update logic to `syncCategoriesFromSector()` |
| `app/Services/FeedbackCategoryService.php` | Added update logic to `syncCategoriesFromSector()` |
| `test_category_sync_update.php`            | Comprehensive test for update functionality        |

---

## Benefits

1. **Automatic Translation Updates** - When templates get Swahili translations, all companies automatically receive them
2. **Content Improvements Propagate** - Better descriptions or corrections in templates update all companies
3. **Maintains Data Integrity** - Custom categories are never touched
4. **Transparent Updates** - Sync results clearly show what was updated and why
5. **Zero Manual Work** - No admin intervention needed for template updates

---

## Best Practices

### For Template Maintainers

1. **Update templates first** - Modify seeders with new content
2. **Run seeders** - Execute to update database templates
3. **Companies auto-sync** - Next company update triggers category sync
4. **Verify changes** - Check sync results in API responses

### For Developers

1. **Don't bypass sync** - Always use service methods for category management
2. **Check sync results** - Log and monitor update counts
3. **Test thoroughly** - Use test script after template changes
4. **Preserve custom data** - Never set `category_key` on user-created categories

---

## Migration Path

### Existing Deployments

For companies already using the system:

1. **No immediate action needed** - Updates happen on next company modification
2. **Optional: Force sync** - Run sync service methods for immediate update
3. **Monitor logs** - Check for update counts and patterns
4. **Verify translations** - Confirm Swahili content appears correctly

### New Deployments

Everything works automatically:

1. Create company with sector
2. Categories auto-populate with full bilingual content
3. Future updates propagate automatically

---

## Related Documentation

-   [Swahili Translation Complete Summary](SWAHILI_TRANSLATION_COMPLETE_SUMMARY.md)
-   [Category Creation API Documentation](CATEGORY_CREATION_API_DOCUMENTATION.md)
-   [Incident Category Swahili Translation](INCIDENT_CATEGORY_SWAHILI_TRANSLATION.md)
-   [Feedback Category Swahili Translation](FEEDBACK_CATEGORY_SWAHILI_TRANSLATION.md)

---

**Last Updated:** January 7, 2026  
**Version:** 2.0  
**Status:** ✅ Production Ready
