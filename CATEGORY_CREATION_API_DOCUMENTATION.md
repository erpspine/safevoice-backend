# Category Creation & Update API Documentation

## Overview

This document describes the API endpoints for creating and updating Feedback Categories and Incident Categories with support for English and Swahili translations.

---

## 📋 Table of Contents

-   [Feedback Category APIs](#feedback-category-apis)
    -   [Create Feedback Category](#create-feedback-category)
    -   [Update Feedback Category](#update-feedback-category)
-   [Incident Category APIs](#incident-category-apis)
    -   [Create Incident Category](#create-incident-category)
    -   [Update Incident Category](#update-incident-category)
-   [Common Response Codes](#common-response-codes)
-   [Error Handling](#error-handling)

---

## Feedback Category APIs

### Create Feedback Category

Creates a new feedback category for a company with optional Swahili translations.

**Endpoint:** `POST /api/admin/feedback-categories`

**Authentication:** Required (Bearer Token)

**Headers:**

```
Content-Type: application/json
Authorization: Bearer {access_token}
```

#### Request Body Parameters

| Parameter        | Type    | Required | Max Length | Description                                     |
| ---------------- | ------- | -------- | ---------- | ----------------------------------------------- |
| `company_id`     | string  | Yes      | -          | Company ID (must exist in companies table)      |
| `parent_id`      | string  | No       | -          | Parent category ID (for creating subcategories) |
| `name`           | string  | Yes      | 100        | Category name in English                        |
| `name_sw`        | string  | No       | 100        | Category name in Swahili                        |
| `description`    | string  | No       | 500        | Category description in English                 |
| `description_sw` | string  | No       | 500        | Category description in Swahili                 |
| `status`         | boolean | Yes      | -          | Active status (true/false)                      |

#### Validation Rules

-   `name` must be unique within the company (case-sensitive)
-   `company_id` must reference an existing company
-   Swahili fields are optional but recommended for bilingual support

#### Request Example

```json
{
    "company_id": "01k9yhbtdq68km9gfaxq94zgjq",
    "name": "Workplace Harassment",
    "name_sw": "Udhalimu Kazini",
    "description": "Issues related to workplace harassment and bullying",
    "description_sw": "Masuala yanayohusiana na udhalimu na uonevu kazini",
    "status": true
}
```

#### Success Response (201 Created)

```json
{
    "success": true,
    "message": "Feedback category created successfully.",
    "data": {
        "feedback_category": {
            "id": "01ke1ntzv6qvjvpk6v2kdx8y3p",
            "company_id": "01k9yhbtdq68km9gfaxq94zgjq",
            "parent_id": null,
            "name": "Workplace Harassment",
            "name_sw": "Udhalimu Kazini",
            "description": "Issues related to workplace harassment and bullying",
            "description_sw": "Masuala yanayohusiana na udhalimu na uonevu kazini",
            "category_key": null,
            "sort_order": null,
            "status": true,
            "created_at": "2026-01-07T10:30:00.000000Z",
            "updated_at": "2026-01-07T10:30:00.000000Z",
            "deleted_at": null,
            "company": {
                "id": "01k9yhbtdq68km9gfaxq94zgjq",
                "name": "Technoguru Digital Systems Ltd"
            }
        }
    }
}
```

#### Error Response (422 Validation Failed)

```json
{
    "success": false,
    "message": "Validation failed.",
    "errors": {
        "name": ["The name has already been taken."],
        "company_id": ["The selected company id is invalid."]
    }
}
```

---

### Update Feedback Category

Updates an existing feedback category with optional Swahili translations.

**Endpoint:** `PUT /api/admin/feedback-categories/{id}`

**Authentication:** Required (Bearer Token)

**Headers:**

```
Content-Type: application/json
Authorization: Bearer {access_token}
```

#### URL Parameters

| Parameter | Type   | Required | Description          |
| --------- | ------ | -------- | -------------------- |
| `id`      | string | Yes      | Feedback category ID |

#### Request Body Parameters

| Parameter        | Type    | Required | Max Length | Description                                     |
| ---------------- | ------- | -------- | ---------- | ----------------------------------------------- |
| `company_id`     | string  | No       | -          | Company ID (must exist)                         |
| `parent_id`      | string  | No       | -          | Parent category ID (null for parent categories) |
| `name`           | string  | No       | 100        | Category name in English                        |
| `name_sw`        | string  | No       | 100        | Category name in Swahili                        |
| `description`    | string  | No       | 500        | Category description in English                 |
| `description_sw` | string  | No       | 500        | Category description in Swahili                 |
| `status`         | boolean | No       | -          | Active status (true/false)                      |

**Note:** All fields are optional. Only include fields you want to update.

#### Request Example

```json
{
    "name": "Workplace Harassment & Bullying",
    "name_sw": "Udhalimu na Uonevu Kazini",
    "description": "Issues related to workplace harassment, bullying, and intimidation",
    "description_sw": "Masuala yanayohusiana na udhalimu, uonevu na kutisha kazini",
    "status": true
}
```

#### Success Response (200 OK)

```json
{
    "success": true,
    "message": "Feedback category updated successfully.",
    "data": {
        "feedback_category": {
            "id": "01ke1ntzv6qvjvpk6v2kdx8y3p",
            "company_id": "01k9yhbtdq68km9gfaxq94zgjq",
            "parent_id": null,
            "name": "Workplace Harassment & Bullying",
            "name_sw": "Udhalimu na Uonevu Kazini",
            "description": "Issues related to workplace harassment, bullying, and intimidation",
            "description_sw": "Masuala yanayohusiana na udhalimu, uonevu na kutisha kazini",
            "category_key": null,
            "sort_order": null,
            "status": true,
            "created_at": "2026-01-07T10:30:00.000000Z",
            "updated_at": "2026-01-07T11:45:00.000000Z",
            "deleted_at": null,
            "company": {
                "id": "01k9yhbtdq68km9gfaxq94zgjq",
                "name": "Technoguru Digital Systems Ltd"
            }
        }
    }
}
```

#### Error Response (404 Not Found)

```json
{
    "success": false,
    "message": "Feedback category not found."
}
```

---

## Incident Category APIs

### Create Incident Category

Creates a new incident category for a company with optional Swahili translations.

**Endpoint:** `POST /api/admin/incident-categories`

**Authentication:** Required (Bearer Token)

**Headers:**

```
Content-Type: application/json
Authorization: Bearer {access_token}
```

#### Request Body Parameters

| Parameter        | Type    | Required | Max Length | Description                                     |
| ---------------- | ------- | -------- | ---------- | ----------------------------------------------- |
| `company_id`     | string  | Yes      | -          | Company ID (must exist in companies table)      |
| `parent_id`      | string  | No       | -          | Parent category ID (for creating subcategories) |
| `name`           | string  | Yes      | 255        | Category name in English                        |
| `name_sw`        | string  | No       | 255        | Category name in Swahili                        |
| `description`    | string  | No       | -          | Category description in English                 |
| `description_sw` | string  | No       | -          | Category description in Swahili                 |
| `status`         | boolean | No       | -          | Active status (default: true)                   |

#### Validation Rules

-   `company_id` must reference an existing active company
-   `name` is required and must be unique within the company context
-   Swahili translations are optional

#### Request Example

```json
{
    "company_id": "01k9yhbtdq68km9gfaxq94zgjq",
    "name": "Safety Violations",
    "name_sw": "Ukiukaji wa Usalama",
    "description": "Incidents related to workplace safety violations and hazards",
    "description_sw": "Matukio yanayohusiana na ukiukaji wa usalama na hatari kazini",
    "status": true
}
```

#### Success Response (201 Created)

```json
{
    "success": true,
    "message": "Incident category created successfully",
    "data": {
        "incident_category": {
            "id": "01ke1p2mkwq8xryvhqjb4nz9xy",
            "company_id": "01k9yhbtdq68km9gfaxq94zgjq",
            "parent_id": null,
            "name": "Safety Violations",
            "name_sw": "Ukiukaji wa Usalama",
            "description": "Incidents related to workplace safety violations and hazards",
            "description_sw": "Matukio yanayohusiana na ukiukaji wa usalama na hatari kazini",
            "category_key": null,
            "sort_order": null,
            "status": true,
            "created_at": "2026-01-07T12:00:00.000000Z",
            "updated_at": "2026-01-07T12:00:00.000000Z",
            "deleted_at": null,
            "company": {
                "id": "01k9yhbtdq68km9gfaxq94zgjq",
                "name": "Technoguru Digital Systems Ltd"
            }
        }
    }
}
```

#### Error Response (422 Validation Failed)

```json
{
    "success": false,
    "message": "Validation errors",
    "errors": {
        "name": ["The name field is required."],
        "company_id": ["The selected company id is invalid."]
    }
}
```

---

### Update Incident Category

Updates an existing incident category with optional Swahili translations.

**Endpoint:** `PUT /api/admin/incident-categories/{id}`

**Authentication:** Required (Bearer Token)

**Headers:**

```
Content-Type: application/json
Authorization: Bearer {access_token}
```

#### URL Parameters

| Parameter | Type   | Required | Description          |
| --------- | ------ | -------- | -------------------- |
| `id`      | string | Yes      | Incident category ID |

#### Request Body Parameters

| Parameter        | Type    | Required | Max Length | Description                                     |
| ---------------- | ------- | -------- | ---------- | ----------------------------------------------- |
| `company_id`     | string  | No       | -          | Company ID (must exist)                         |
| `parent_id`      | string  | No       | -          | Parent category ID (null for parent categories) |
| `name`           | string  | No       | 255        | Category name in English                        |
| `name_sw`        | string  | No       | 255        | Category name in Swahili                        |
| `description`    | string  | No       | -          | Category description in English                 |
| `description_sw` | string  | No       | -          | Category description in Swahili                 |
| `status`         | boolean | No       | -          | Active status (true/false)                      |

**Note:** All fields are optional. Only include fields you want to update.

#### Request Example

```json
{
    "name": "Safety & Security Violations",
    "name_sw": "Ukiukaji wa Usalama na Ulinzi",
    "description": "Incidents related to workplace safety, security violations, and hazards",
    "description_sw": "Matukio yanayohusiana na usalama, ukiukaji wa ulinzi na hatari kazini"
}
```

#### Success Response (200 OK)

```json
{
    "success": true,
    "message": "Incident category updated successfully",
    "data": {
        "incident_category": {
            "id": "01ke1p2mkwq8xryvhqjb4nz9xy",
            "company_id": "01k9yhbtdq68km9gfaxq94zgjq",
            "parent_id": null,
            "name": "Safety & Security Violations",
            "name_sw": "Ukiukaji wa Usalama na Ulinzi",
            "description": "Incidents related to workplace safety, security violations, and hazards",
            "description_sw": "Matukio yanayohusiana na usalama, ukiukaji wa ulinzi na hatari kazini",
            "category_key": null,
            "sort_order": null,
            "status": true,
            "created_at": "2026-01-07T12:00:00.000000Z",
            "updated_at": "2026-01-07T13:15:00.000000Z",
            "deleted_at": null,
            "company": {
                "id": "01k9yhbtdq68km9gfaxq94zgjq",
                "name": "Technoguru Digital Systems Ltd"
            }
        }
    }
}
```

#### Error Response (404 Not Found)

```json
{
    "success": false,
    "message": "Incident category not found or invalid company/department"
}
```

---

## Common Response Codes

| Status Code | Description                                            |
| ----------- | ------------------------------------------------------ |
| 200         | OK - Request successful (for updates)                  |
| 201         | Created - Resource created successfully                |
| 401         | Unauthorized - Invalid or missing authentication token |
| 404         | Not Found - Resource does not exist                    |
| 422         | Unprocessable Entity - Validation failed               |
| 500         | Internal Server Error - Server error occurred          |

---

## Error Handling

### Validation Errors (422)

When validation fails, the API returns a structured error response:

```json
{
    "success": false,
    "message": "Validation failed.",
    "errors": {
        "field_name": ["Error message 1", "Error message 2"]
    }
}
```

### Common Validation Errors

#### Feedback Categories

-   `name` field:
    -   Required
    -   Maximum 100 characters
    -   Must be unique within the company
-   `name_sw` field:
    -   Optional
    -   Maximum 100 characters
-   `description` field:
    -   Optional
    -   Maximum 500 characters
-   `description_sw` field:
    -   Optional
    -   Maximum 500 characters

#### Incident Categories

-   `name` field:
    -   Required
    -   Maximum 255 characters
-   `name_sw` field:
    -   Optional
    -   Maximum 255 characters
-   `description` and `description_sw`:
    -   Optional
    -   No strict length limit

### Server Errors (500)

```json
{
    "success": false,
    "message": "Failed to create feedback category.",
    "error": "Detailed error message (only in debug mode)"
}
```

---

## Usage Examples

### Example 1: Create Bilingual Feedback Category

**Request:**

```bash
curl -X POST https://api.example.com/api/admin/feedback-categories \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your_token_here" \
  -d '{
    "company_id": "01k9yhbtdq68km9gfaxq94zgjq",
    "name": "Customer Service",
    "name_sw": "Huduma kwa Wateja",
    "description": "Feedback about customer service quality",
    "description_sw": "Maoni kuhusu ubora wa huduma kwa wateja",
    "status": true
  }'
```

### Example 2: Update Only Swahili Translation

**Request:**

```bash
curl -X PUT https://api.example.com/api/admin/feedback-categories/01ke1ntzv6qvjvpk6v2kdx8y3p \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your_token_here" \
  -d '{
    "name_sw": "Huduma Bora kwa Wateja",
    "description_sw": "Maoni kuhusu ubora na ufanisi wa huduma kwa wateja"
  }'
```

### Example 3: Create Incident Category (English Only)

**Request:**

```bash
curl -X POST https://api.example.com/api/admin/incident-categories \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your_token_here" \
  -d '{
    "company_id": "01k9yhbtdq68km9gfaxq94zgjq",
    "name": "Data Privacy Breach",
    "description": "Incidents related to unauthorized access or disclosure of sensitive data",
    "status": true
  }'
```

### Example 4: Add Swahili Translation to Existing Category

**Request:**

```bash
curl -X PUT https://api.example.com/api/admin/incident-categories/01ke1p2mkwq8xryvhqjb4nz9xy \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your_token_here" \
  -d '{
    "name_sw": "Ukiukaji wa Faragha ya Data",
    "description_sw": "Matukio yanayohusiana na upatikanaji bila idhini au ufichuaji wa data nyeti"
  }'
```

---

## Best Practices

### 1. **Always Provide Swahili Translations**

When creating categories for companies operating in Tanzania or East Africa, provide both English and Swahili translations for better user experience.

### 2. **Use Consistent Terminology**

Reference the sector templates for standard translations to maintain consistency across the platform.

### 3. **Validate Before Submission**

Ensure all required fields are present and within character limits before making API calls.

### 4. **Handle Errors Gracefully**

Always check the `success` field in the response and handle validation errors appropriately in your frontend.

### 5. **Update vs Create**

Use POST for creating new categories and PUT for updating existing ones. The update endpoint allows partial updates.

### 6. **Character Limits**

Be mindful of character limits:

-   Feedback category names: 100 characters
-   Incident category names: 255 characters
-   Descriptions: 500 characters (feedback), unlimited (incident)

---

## Related Documentation

-   [Swahili Translation Complete Summary](SWAHILI_TRANSLATION_COMPLETE_SUMMARY.md)
-   [Feedback Category Swahili Translation](FEEDBACK_CATEGORY_SWAHILI_TRANSLATION.md)
-   [Incident Category Swahili Translation](INCIDENT_CATEGORY_SWAHILI_TRANSLATION.md)
-   [API Authentication Documentation](API_AUTHENTICATION_DOCS.md)

---

## Support

For questions or issues with these APIs, please refer to:

-   Technical documentation in the repository
-   API error messages for specific guidance
-   Development team for implementation support

---

**Last Updated:** January 7, 2026  
**Version:** 1.0  
**Status:** ✅ Production Ready
