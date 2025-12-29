# Departmental Case Distribution API

## Overview

The Departmental Case Distribution API provides analytics and reporting for case distribution across departments within organizations. It includes role-based access control, trend analysis, and CSV export capabilities.

## Endpoints

### 1. Get Distribution Analytics

**URL**: `/api/case-distribution/analytics`  
**Method**: GET  
**Authentication**: Required (Bearer token)

**Description**: Retrieve departmental case distribution with analytics data including case counts, closure rates, and resolution times.

**Response Format**:

```json
{
  "status": "success",
  "message": "Departmental case distribution retrieved successfully",
  "data": {
    "departments": [
      {
        "department": {
          "id": "dept_id",
          "name": "Department Name",
          "description": "Department Description"
        },
        "number_of_cases": 10,
        "closed": 8,
        "pending": 2,
        "avg_resolution_time": "5.2 days",
        "avg_resolution_days": 5.2,
        "cases": [
          {
            "id": "case_id",
            "case_token": "CASE_TOKEN",
            "title": "Case Title",
            "status": "closed",
            "type": "incident",
            "created_at": "2024-01-15",
            "closed_at": "2024-01-20",
            "company": "Company Name",
            "branch": "Branch Name"
          }
        ]
      }
    ],
    "summary": {
      "total_departments": 2,
      "total_cases": 10,
      "total_closed": 8,
      "total_pending": 2,
      "overall_closure_rate": 80.0
    },
    "filter_options": {
      "companies": [...],
      "branches": [...],
      "departments": [...]
    }
  }
}
```

**Query Parameters**:

-   `company_id` (optional): Filter by specific company (super admin only)
-   `branch_id` (optional): Filter by specific branch (company admin and super admin)
-   `department_id` (optional): Filter by specific department
-   `start_date` (optional): Filter cases from this date (YYYY-MM-DD)
-   `end_date` (optional): Filter cases until this date (YYYY-MM-DD)
-   `status` (optional): Filter by case status (`open`, `in_progress`, `closed`)

### 2. Get Distribution Trends

**URL**: `/api/case-distribution/trends`  
**Method**: GET  
**Authentication**: Required (Bearer token)

**Description**: Retrieve departmental case distribution trends over time.

**Query Parameters**:

-   `period` (optional): Time period (`weekly` or `monthly`, default: `monthly`)
-   `start_date` (optional): Start date for trend analysis (YYYY-MM-DD)
-   `end_date` (optional): End date for trend analysis (YYYY-MM-DD)
-   `department_id` (optional): Filter by specific department
-   All other filters from analytics endpoint

**Response Format**:

```json
{
    "status": "success",
    "message": "Departmental case distribution trends retrieved successfully",
    "data": {
        "period": "monthly",
        "trends": [
            {
                "period_key": "2024-01",
                "period_label": "Jan 2024",
                "departments": [
                    {
                        "department_name": "IT",
                        "cases": 5,
                        "closed": 4,
                        "pending": 1,
                        "closure_rate": 80
                    }
                ]
            }
        ],
        "department": {
            "id": "dept_id",
            "name": "Department Name"
        }
    }
}
```

### 3. Export Distribution Data

**URL**: `/api/case-distribution/export`  
**Method**: GET  
**Authentication**: Required (Bearer token)

**Description**: Export departmental case distribution data as CSV file.

**Query Parameters**: Same as analytics endpoint

**Response**: CSV file download with the following headers:

-   Department
-   Number of Cases
-   Closed
-   Pending
-   Avg Resolution Time

### 4. Get Filter Options

**URL**: `/api/case-distribution/filters`  
**Method**: GET  
**Authentication**: Required (Bearer token)

**Description**: Get available filter options based on user role and permissions.

**Response Format**:

```json
{
    "status": "success",
    "message": "Filter options retrieved successfully",
    "data": {
        "companies": [
            {
                "id": "company_id",
                "name": "Company Name"
            }
        ],
        "branches": [
            {
                "id": "branch_id",
                "name": "Branch Name",
                "company_id": "company_id"
            }
        ],
        "departments": [
            {
                "id": "department_id",
                "name": "Department Name",
                "company_id": "company_id"
            }
        ]
    }
}
```

## Role-Based Access Control

### Branch Admin (`branch_admin`)

-   Can only see cases from their specific branch
-   Cannot filter by company or other branches
-   Sees departments available in their company

### Company Admin (`company_admin`)

-   Can see cases from all branches under their company
-   Can filter by branches within their company
-   Cannot see other companies' data

### Super Admin (`super_admin`, `system_admin`, `admin`)

-   Can see data from all companies and branches
-   Can filter by any company, branch, or department
-   Has access to all filter options

## Error Responses

All endpoints return standardized error responses:

```json
{
    "status": "error",
    "message": "Error description",
    "error": "Detailed error message"
}
```

## Usage Examples

### Get all departmental analytics for current user's scope:

```bash
GET /api/case-distribution/analytics
Authorization: Bearer YOUR_TOKEN
```

### Get analytics for specific date range:

```bash
GET /api/case-distribution/analytics?start_date=2024-01-01&end_date=2024-01-31
Authorization: Bearer YOUR_TOKEN
```

### Get weekly trends for specific department:

```bash
GET /api/case-distribution/trends?period=weekly&department_id=dept_123
Authorization: Bearer YOUR_TOKEN
```

### Export CSV data:

```bash
GET /api/case-distribution/export?start_date=2024-01-01&end_date=2024-01-31
Authorization: Bearer YOUR_TOKEN
```

## Notes

-   All date parameters should be in `YYYY-MM-DD` format
-   Resolution times are calculated in days and presented in human-readable format
-   CSV exports include all data visible to the authenticated user based on their role
-   Filter options are dynamically generated based on user permissions
-   All endpoints require valid authentication via Sanctum bearer tokens
