# Recipient Type Documentation

## Overview

A recipient type field has been added to the users table to indicate whether a branch user is a primary recipient or alternative recipient.

## Database Structure

### New Field Added to `users` table:

-   `recipient_type` (enum: 'primary', 'alternative', nullable)

## API Usage

### Creating a Branch User with Recipient Type

**Endpoint:** `POST /api/admin/users`

**Example Request for Primary Recipient:**

```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "phone_number": "+1234567890",
    "role": "branch_admin",
    "company_id": 1,
    "branch_id": 2,
    "recipient_type": "primary"
}
```

**Example Request for Alternative Recipient:**

```json
{
    "name": "Jane Smith",
    "email": "jane@example.com",
    "phone_number": "+1234567891",
    "role": "investigator",
    "company_id": 1,
    "branch_id": 2,
    "recipient_type": "alternative"
}
```

### Updating a User's Recipient Type

**Endpoint:** `PUT /api/admin/users/{id}`

**Example Request:**

```json
{
    "recipient_type": "primary"
}
```

## Validation Rules

### Recipient Type is Only Allowed for Branch Users

-   Users must have a `branch_id` assigned
-   Users must not be admin users (super_admin, admin)
-   If a non-branch user tries to set recipient type, validation will fail with error:
    ```json
    {
        "success": false,
        "message": "Recipient type is only allowed for branch users",
        "errors": {
            "recipient_type": [
                "Recipient type can only be set for users assigned to a branch"
            ]
        }
    }
    ```

### Field Validation

The recipient_type field is optional (nullable) but when provided must meet these requirements:

-   Must be one of: `primary`, `alternative`

## Business Logic

### Who Can Have Recipient Type?

-   ✅ Branch users (users with `branch_id` set)
-   ❌ Admin users (`super_admin`, `admin` roles)
-   ❌ Company users without branch assignment

### Use Cases

1. **Primary Recipient**: Main contact person for notifications/communications within the branch
2. **Alternative Recipient**: Backup contact when primary is unavailable

### Recipient Type Values

-   `primary` - Indicates this user is the primary recipient for their branch
-   `alternative` - Indicates this user is the alternative/backup recipient for their branch
-   `null` - Regular branch user without recipient responsibilities

## Implementation Details

### Model Changes

-   Added `recipient_type` field to `User` model fillable array
-   Field is nullable and indexed for performance

### Controller Changes

-   Added validation rules in `UserController@store` and `UserController@update`
-   Custom validation prevents non-branch users from setting recipient type
-   Automatic inclusion of recipient type when creating/updating branch users

### Migrations

-   Migration file: `2025_10_30_124138_add_recipient_type_to_users_table.php`
-   Adds recipient_type enum field with proper indexing
-   Safely rollbackable

## Database Schema

The recipient_type column is added to the users table with:

-   Type: ENUM('primary', 'alternative')
-   Nullable: Yes
-   Default: NULL
-   Index: Composite index on (branch_id, recipient_type) for efficient queries

## Example Usage

```php
// Create a primary recipient
$primaryUser = User::create([
    'name' => 'John Doe',
    'email' => 'john@company.com',
    'role' => 'branch_admin',
    'company_id' => 1,
    'branch_id' => 2,
    'recipient_type' => 'primary'
]);

// Create an alternative recipient
$altUser = User::create([
    'name' => 'Jane Smith',
    'email' => 'jane@company.com',
    'role' => 'investigator',
    'company_id' => 1,
    'branch_id' => 2,
    'recipient_type' => 'alternative'
]);

// Query recipients by branch
$recipients = User::where('branch_id', 2)
    ->whereNotNull('recipient_type')
    ->get();
```

-   All database columns exist
-   Proper indexing is in place
