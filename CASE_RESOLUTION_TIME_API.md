# Case Resolution Time API Documentation

This API provides analytics for case resolution times, including individual case data, summary statistics, trends, and export functionality.

## Authentication

All endpoints require authentication using Laravel Sanctum. Include the Bearer token in the Authorization header:

```
Authorization: Bearer {your_token}
```

## Access Control

-   **Company Admins**: Can view resolution analytics for all cases in their company
-   **Branch Admins**: Can view resolution analytics for cases in their specific branch
-   **Investigators**: Can view resolution analytics for cases assigned to them

## Endpoints

### 1. Get Resolution Analytics

**GET** `/api/case-resolution/analytics`

Returns detailed resolution time analytics for closed cases.

**Query Parameters:**

-   `start_date` (optional): Filter cases closed on or after this date (YYYY-MM-DD)
-   `end_date` (optional): Filter cases closed on or before this date (YYYY-MM-DD)

**Response:**

```json
{
    "success": true,
    "data": {
        "cases": [
            {
                "case_id": "SV-102",
                "case_title": "Harassment Report",
                "submitted_on": "01/02/2025",
                "closed_on": "05/02/2025",
                "duration": "4 days",
                "duration_in_days": 4,
                "duration_in_hours": 96,
                "duration_in_minutes": 5760,
                "close_classification": "substantiated",
                "close_remarks": "Issue resolved after investigation",
                "closed_by": {
                    "id": "user123",
                    "name": "Duncan Osur",
                    "email": "duncan@safevoice.tz"
                },
                "priority": 3,
                "type": "incident",
                "status": "closed",
                "company": "SafeVoice Company",
                "branch": "Headquarters"
            }
        ],
        "summary": {
            "total_cases": 15,
            "average_resolution_days": 3.2,
            "average_resolution_hours": 76.8,
            "by_classification": [
                {
                    "classification": "substantiated",
                    "count": 8,
                    "avg_days": 2.5
                },
                {
                    "classification": "unsubstantiated",
                    "count": 4,
                    "avg_days": 4.2
                },
                {
                    "classification": "partially_substantiated",
                    "count": 3,
                    "avg_days": 3.8
                }
            ],
            "by_time_range": {
                "same_day": 2,
                "1_3_days": 8,
                "4_7_days": 4,
                "8_30_days": 1,
                "over_30_days": 0
            }
        }
    }
}
```

### 2. Get Resolution Trends

**GET** `/api/case-resolution/trends`

Returns resolution time trends over monthly or weekly periods.

**Query Parameters:**

-   `period` (optional): Either `monthly` (default) or `weekly`

**Response for Monthly Trends:**

```json
{
    "success": true,
    "data": {
        "period": "monthly",
        "trends": [
            {
                "period": "Jan 2025",
                "cases_count": 12,
                "avg_resolution_days": 3.2
            },
            {
                "period": "Feb 2025",
                "cases_count": 8,
                "avg_resolution_days": 2.8
            }
        ]
    }
}
```

**Response for Weekly Trends:**

```json
{
    "success": true,
    "data": {
        "period": "weekly",
        "trends": [
            {
                "period": "Week 5, 2025",
                "week_start": "Jan 27",
                "week_end": "Feb 02, 2025",
                "cases_count": 3,
                "avg_resolution_days": 2.1
            }
        ]
    }
}
```

### 3. Export Resolution Data

**GET** `/api/case-resolution/export`

Exports resolution time data as a CSV file.

**Query Parameters:**

-   `start_date` (optional): Filter cases closed on or after this date (YYYY-MM-DD)
-   `end_date` (optional): Filter cases closed on or before this date (YYYY-MM-DD)

**Response:**
Returns a CSV file with the following columns:

-   Case ID
-   Submitted On
-   Closed On
-   Duration
-   Close Classification
-   Close Remarks
-   Closed By
-   Priority
-   Type
-   Company
-   Branch

**Response Headers:**

```
Content-Type: text/csv
Content-Disposition: attachment; filename="case_resolution_time_2025-12-10_10-30-45.csv"
```

## Example Usage

### Get analytics for cases closed in the last month

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
     "https://your-domain.com/api/case-resolution/analytics?start_date=2025-11-10&end_date=2025-12-10"
```

### Get weekly trends

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
     "https://your-domain.com/api/case-resolution/trends?period=weekly"
```

### Export data for a specific date range

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
     "https://your-domain.com/api/case-resolution/export?start_date=2025-01-01&end_date=2025-12-31" \
     --output "case_resolution_data.csv"
```

## Error Responses

All endpoints may return error responses in the following format:

```json
{
    "success": false,
    "message": "Error description",
    "error": "Detailed error message (in development mode)"
}
```

Common HTTP status codes:

-   `401`: Unauthorized (missing or invalid token)
-   `403`: Forbidden (insufficient permissions)
-   `500`: Internal Server Error

## Duration Calculation Formula

**Time to Close = Closed Date/Time – Created Date/Time**

The duration is calculated using the following logic:

1. If the duration is more than 0 days, it shows days and remaining hours
2. If the duration is less than 1 day but more than 0 hours, it shows hours and remaining minutes
3. If the duration is less than 1 hour, it shows only minutes

Examples:

-   4 days, 2 hours → "4 days 2 hours"
-   18 hours, 30 minutes → "18 hours 30 minutes"
-   45 minutes → "45 minutes"

## Notes

-   Only cases with `status = 'closed'` and a non-null `case_closed_at` value are included in the analytics
-   The `closed_by` field was automatically added to track who closed each case
-   All existing case closure operations now record the user who performed the action
-   Time zones are handled using the application's default timezone configuration
