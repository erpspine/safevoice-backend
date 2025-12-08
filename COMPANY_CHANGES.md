# Company Model Changes

## Overview

The Company model has been updated to replace URL-based logo storage with attachment-based logo storage, and the established date field has been removed for simplification.

## Changes Made

### Removed Fields:

-   ❌ `logo_url` (string) - Previously stored URL to company logo
-   ❌ `established_at` (date) - Company establishment date

### Added Fields:

-   ✅ `logo` (string, nullable) - Stores file path or attachment reference for company logo

## Database Changes

-   **Migration**: `2025_11_03_143219_modify_companies_table_remove_logo_url_established_date_add_logo.php`
-   **Schema Updated**: Companies table structure modified
-   **Indexes**: No index changes needed for the new logo field

## API Usage

### Creating a Company with Logo

```json
{
    "name": "Tech Solutions Inc",
    "email": "contact@techsolutions.com",
    "contact": "+1234567890",
    "logo": "uploads/company_logos/tech_solutions_logo.png",
    "address": "123 Business Street, City, State",
    "website": "https://techsolutions.com",
    "description": "Leading technology solutions provider",
    "tax_id": "TAX123456789",
    "plan": "premium",
    "status": true
}
```

### Updating Company Logo

```json
{
    "logo": "uploads/company_logos/new_company_logo.jpg"
}
```

## Model Changes

### Updated Fillable Fields

```php
protected $fillable = [
    'name',
    'email',
    'contact',
    'logo',           // New field
    'status',
    'plan',
    'address',
    'website',
    'description',
    'tax_id',
    // 'established_at' removed
];
```

### Updated Casts

```php
protected $casts = [
    'status' => 'boolean',
    // 'established_at' => 'date' removed
];
```

## Controller Updates

### Validation Rules

**Create Company (POST /api/admin/companies):**

```php
'logo' => 'nullable|string|max:255',  // New validation
// 'established_at' => 'nullable|date' removed
```

**Update Company (PUT /api/admin/companies/{id}):**

```php
'logo' => 'nullable|string|max:255',  // New validation
// 'established_at' => 'nullable|date' removed
```

### Response Fields

Company responses now include `logo` field instead of `logo_url`:

```json
{
    "id": "01abc123...",
    "name": "Company Name",
    "email": "contact@company.com",
    "contact": "+1234567890",
    "logo": "uploads/company_logos/logo.png",
    "address": "Company Address",
    "status": true,
    "plan": "premium"
}
```

## Implementation Notes

1. **Logo Storage**: The `logo` field stores the file path or reference to the uploaded logo file
2. **File Management**: Implement proper file upload handling for logo attachments
3. **Validation**: Logo field accepts file paths up to 255 characters
4. **Backwards Compatibility**: Migration safely removes old fields and adds new ones
5. **Rollback Support**: Migration includes down() method to restore previous structure if needed

## Migration Details

### Up Migration:

-   Drops `logo_url` column if exists
-   Drops `established_at` column if exists
-   Adds `logo` column (nullable string)

### Down Migration:

-   Restores `logo_url` column
-   Restores `established_at` column
-   Removes `logo` column

The migration is designed to be safe and reversible.
