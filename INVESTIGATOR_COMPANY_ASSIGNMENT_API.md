# Investigator Company Assignment API Documentation

## Overview

This API manages the assignment of investigators to companies. It allows administrators to assign investigators to specific companies, enabling them to handle cases from those companies.

**Base URL:** `/api/admin/investigator-company-assignments`

**Authentication:** Bearer Token (Laravel Sanctum)

**Authorization:** `admin` or `super_admin` roles only

---

## Endpoints

### 1. List All Investigators with Their Companies

**Endpoint:** `GET /api/admin/investigator-company-assignments`

**Description:** Retrieve all investigators and their assigned companies.

#### Query Parameters

| Parameter         | Type    | Required | Description                        |
| ----------------- | ------- | -------- | ---------------------------------- |
| `investigator_id` | string  | No       | Filter by specific investigator ID |
| `status`          | boolean | No       | Filter by investigator status      |

#### Response

```json
{
    "success": true,
    "data": [
        {
            "id": "01k9z080j8tprw2t7bmyekqagx",
            "name": "John Doe",
            "email": "john@example.com",
            "is_external": false,
            "status": true,
            "companies": [
                {
                    "id": "01k9yhbtdq68km9gfaxq94zgjq",
                    "name": "Acme Corporation",
                    "assigned_at": "2025-12-30T09:57:04.000000Z"
                }
            ]
        }
    ]
}
```

---

### 2. Get Assignment Statistics

**Endpoint:** `GET /api/admin/investigator-company-assignments/stats`

**Description:** Get overview statistics for investigator-company assignments.

#### Response

```json
{
    "success": true,
    "data": {
        "total_assignments": 25,
        "investigators_with_companies": 10,
        "companies_with_investigators": 15,
        "average_companies_per_investigator": 2.5,
        "top_investigators_by_companies": [
            {
                "id": "01k9z080j8tprw2t7bmyekqagx",
                "name": "John Doe",
                "companies_count": 5
            }
        ]
    }
}
```

---

### 3. Get Companies Assigned to an Investigator

**Endpoint:** `GET /api/admin/investigator-company-assignments/investigators/{investigator}/companies`

**Description:** Get all companies assigned to a specific investigator.

#### URL Parameters

| Parameter      | Type   | Required | Description            |
| -------------- | ------ | -------- | ---------------------- |
| `investigator` | string | Yes      | Investigator ID (ULID) |

#### Response

```json
{
    "success": true,
    "data": {
        "investigator": {
            "id": "01k9z080j8tprw2t7bmyekqagx",
            "name": "John Doe",
            "email": "john@example.com",
            "is_external": false
        },
        "companies": [
            {
                "id": "01k9yhbtdq68km9gfaxq94zgjq",
                "name": "Acme Corporation",
                "plan": "Premium",
                "status": true,
                "assigned_at": "2025-12-30T09:57:04.000000Z",
                "total_cases": 15,
                "active_cases": 3
            }
        ]
    }
}
```

---

### 4. Get Available Companies for Assignment

**Endpoint:** `GET /api/admin/investigator-company-assignments/investigators/{investigator}/available`

**Description:** Get companies not yet assigned to a specific investigator.

#### URL Parameters

| Parameter      | Type   | Required | Description            |
| -------------- | ------ | -------- | ---------------------- |
| `investigator` | string | Yes      | Investigator ID (ULID) |

#### Response

```json
{
    "success": true,
    "data": [
        {
            "id": "01k9yhbtdq68km9gfaxq94zgjq",
            "name": "New Corp Ltd",
            "plan": "Basic",
            "total_cases": 8
        }
    ]
}
```

---

### 5. Get Investigators Assigned to a Company

**Endpoint:** `GET /api/admin/investigator-company-assignments/companies/{company}/investigators`

**Description:** Get all investigators assigned to a specific company.

#### URL Parameters

| Parameter | Type   | Required | Description       |
| --------- | ------ | -------- | ----------------- |
| `company` | string | Yes      | Company ID (ULID) |

#### Response

```json
{
    "success": true,
    "data": {
        "company": {
            "id": "01k9yhbtdq68km9gfaxq94zgjq",
            "name": "Acme Corporation"
        },
        "investigators": [
            {
                "id": "01k9z080j8tprw2t7bmyekqagx",
                "name": "John Doe",
                "email": "john@example.com",
                "is_external": false,
                "specializations": ["fraud", "harassment"],
                "assigned_at": "2025-12-30T09:57:04.000000Z",
                "active_cases": 2,
                "is_available": true
            }
        ]
    }
}
```

---

### 6. Assign Companies to an Investigator

**Endpoint:** `POST /api/admin/investigator-company-assignments/investigators/{investigator}/assign`

**Description:** Assign one or more companies to an investigator.

#### URL Parameters

| Parameter      | Type   | Required | Description            |
| -------------- | ------ | -------- | ---------------------- |
| `investigator` | string | Yes      | Investigator ID (ULID) |

#### Request Body

```json
{
    "company_ids": ["01k9yhbtdq68km9gfaxq94zgjq", "01k9yhctdq68km9gfaxq94zgjr"]
}
```

#### Validation Rules

| Field           | Rules               |
| --------------- | ------------------- |
| `company_ids`   | required, array     |
| `company_ids.*` | exists:companies,id |

#### Success Response

```json
{
    "success": true,
    "message": "Companies assigned successfully",
    "data": [
        {
            "id": "01k9yhbtdq68km9gfaxq94zgjq",
            "name": "Acme Corporation"
        }
    ]
}
```

#### Error Responses

**Inactive Investigator (422):**

```json
{
    "success": false,
    "message": "Cannot assign companies to an inactive investigator"
}
```

**Inactive Companies (422):**

```json
{
    "success": false,
    "message": "One or more companies are inactive or not found"
}
```

---

### 7. Unassign Companies from an Investigator

**Endpoint:** `POST /api/admin/investigator-company-assignments/investigators/{investigator}/unassign`

**Description:** Remove company assignments from an investigator.

#### URL Parameters

| Parameter      | Type   | Required | Description            |
| -------------- | ------ | -------- | ---------------------- |
| `investigator` | string | Yes      | Investigator ID (ULID) |

#### Request Body

```json
{
    "company_ids": ["01k9yhbtdq68km9gfaxq94zgjq"]
}
```

#### Success Response

```json
{
    "success": true,
    "message": "Companies unassigned successfully"
}
```

#### Error Response

**Active Cases Exist (422):**

```json
{
    "success": false,
    "message": "Cannot unassign companies with active cases"
}
```

---

## Common Error Responses

### 401 Unauthorized

```json
{
    "success": false,
    "message": "Authentication required. Please provide a valid authorization token."
}
```

### 403 Forbidden

```json
{
    "success": false,
    "message": "Access denied. Only admins can access investigator assignments."
}
```

### 404 Not Found

```json
{
    "success": false,
    "message": "Investigator not found"
}
```

### 422 Validation Error

```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "company_ids": ["The company_ids field is required."]
    }
}
```

### 500 Internal Server Error

```json
{
    "success": false,
    "message": "Failed to retrieve investigator company assignments",
    "error": "Error details..."
}
```

---

## Routes Summary

| Method | Endpoint                                                                   | Description                           |
| ------ | -------------------------------------------------------------------------- | ------------------------------------- |
| GET    | `/investigator-company-assignments`                                        | List all investigators with companies |
| GET    | `/investigator-company-assignments/stats`                                  | Get assignment statistics             |
| GET    | `/investigator-company-assignments/investigators/{investigator}/companies` | Get companies for investigator        |
| GET    | `/investigator-company-assignments/investigators/{investigator}/available` | Get available companies               |
| GET    | `/investigator-company-assignments/companies/{company}/investigators`      | Get investigators for company         |
| POST   | `/investigator-company-assignments/investigators/{investigator}/assign`    | Assign companies                      |
| POST   | `/investigator-company-assignments/investigators/{investigator}/unassign`  | Unassign companies                    |

---

## Usage Examples

### cURL Examples

**List all investigators with companies:**

```bash
curl -X GET "http://localhost:8000/api/admin/investigator-company-assignments" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

**Assign companies to investigator:**

```bash
curl -X POST "http://localhost:8000/api/admin/investigator-company-assignments/investigators/01k9z080j8tprw2t7bmyekqagx/assign" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"company_ids": ["01k9yhbtdq68km9gfaxq94zgjq"]}'
```

**Unassign companies from investigator:**

```bash
curl -X POST "http://localhost:8000/api/admin/investigator-company-assignments/investigators/01k9z080j8tprw2t7bmyekqagx/unassign" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"company_ids": ["01k9yhbtdq68km9gfaxq94zgjq"]}'
```

---

## Business Rules

1. **Assignment Requirements:**

    - Investigator must be active (`status: true`)
    - Company must be active (`status: true`)
    - Duplicate assignments are ignored (syncWithoutDetaching)

2. **Unassignment Restrictions:**

    - Cannot unassign if investigator has active cases for that company
    - All active case assignments must be completed or reassigned first

3. **Access Control:**
    - Only `admin` and `super_admin` roles can manage assignments
    - All endpoints require valid authentication token
