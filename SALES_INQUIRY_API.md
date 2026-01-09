# Sales Inquiry API Documentation

## Overview

This endpoint allows website visitors to submit sales inquiries through a contact form. The system sends the inquiry details to the sales team and sends an automated confirmation email to the customer.

---

## Endpoint

**POST** `/api/public/sales-inquiry`

**Authentication:** None (Public endpoint)

**Content-Type:** `application/json`

---

## Request Parameters

| Parameter   | Type   | Required | Max Length | Description                                    |
| ----------- | ------ | -------- | ---------- | ---------------------------------------------- |
| `name`      | string | Yes      | 255        | Full name of the person submitting the inquiry |
| `email`     | string | Yes      | 255        | Valid email address for contact                |
| `company`   | string | Yes      | 255        | Company/Organization name                      |
| `phone`     | string | Yes      | 20         | Phone number (preferably with country code)    |
| `employees` | string | Yes      | 50         | Company size/number of employees               |
| `message`   | string | Yes      | 2000       | Detailed message about the inquiry             |

---

## Request Example

```json
{
    "name": "John Doe",
    "email": "john.doe@example.com",
    "company": "Example Corp",
    "phone": "+255712345678",
    "employees": "51-200",
    "message": "I'm interested in implementing SafeVoice for our organization. Please provide more information about your services."
}
```

---

## Success Response (200 OK)

```json
{
    "success": true,
    "message": "Thank you for your inquiry. Our sales team will contact you shortly.",
    "data": {
        "submitted_at": "2026-01-09T07:21:21.343279Z",
        "name": "John Doe",
        "email": "john.doe@example.com"
    }
}
```

---

## Error Response (422 Validation Failed)

```json
{
    "success": false,
    "message": "Validation failed.",
    "errors": {
        "email": [
            "The email field is required.",
            "The email must be a valid email address."
        ],
        "name": ["The name field is required."]
    }
}
```

---

## Error Response (500 Server Error)

```json
{
    "success": false,
    "message": "Failed to submit your inquiry. Please try again later or contact us directly at sales@safevoice.tz",
    "error": "Error details (only in debug mode)"
}
```

---

## Usage Examples

### JavaScript (Fetch API)

```javascript
async function submitSalesInquiry() {
    const data = {
        name: "John Doe",
        email: "john.doe@example.com",
        company: "Example Corp",
        phone: "+255712345678",
        employees: "51-200",
        message:
            "I'm interested in implementing SafeVoice for our organization.",
    };

    try {
        const response = await fetch(
            "https://api.safevoice.tz/api/public/sales-inquiry",
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify(data),
            }
        );

        const result = await response.json();

        if (result.success) {
            console.log("Success:", result.message);
            // Show success message to user
        } else {
            console.error("Error:", result.message);
            // Show error message to user
        }
    } catch (error) {
        console.error("Network error:", error);
    }
}
```

### cURL

```bash
curl -X POST https://api.safevoice.tz/api/public/sales-inquiry \
  -H 'Content-Type: application/json' \
  -d '{
    "name": "John Doe",
    "email": "john.doe@example.com",
    "company": "Example Corp",
    "phone": "+255712345678",
    "employees": "51-200",
    "message": "I am interested in implementing SafeVoice for our organization. Please provide more information about your services."
  }'
```

### Axios

```javascript
import axios from "axios";

const submitSalesInquiry = async (formData) => {
    try {
        const response = await axios.post(
            "https://api.safevoice.tz/api/public/sales-inquiry",
            formData
        );

        if (response.data.success) {
            return {
                success: true,
                message: response.data.message,
                data: response.data.data,
            };
        }
    } catch (error) {
        if (error.response) {
            // Server responded with error
            return {
                success: false,
                message: error.response.data.message,
                errors: error.response.data.errors,
            };
        } else {
            // Network error
            return {
                success: false,
                message: "Network error. Please try again.",
            };
        }
    }
};

// Usage
const formData = {
    name: "John Doe",
    email: "john.doe@example.com",
    company: "Example Corp",
    phone: "+255712345678",
    employees: "51-200",
    message: "I'm interested in SafeVoice.",
};

submitSalesInquiry(formData).then((result) => {
    console.log(result);
});
```

---

## What Happens After Submission

1. **Validation**: The system validates all required fields
2. **Email to Sales Team**: An email is sent to `sales@safevoice.tz` with:
    - All inquiry details
    - Reply-to set to customer's email
    - Priority marker for quick response
    - Quick action suggestions
3. **Confirmation Email**: A confirmation email is sent to the customer with:
    - Thank you message
    - Expected response time (24-48 hours)
    - Contact information
4. **Response**: API returns success confirmation with submission timestamp

---

## Email Features

### Sales Team Email

-   **Subject**: "New Sales Inquiry from SafeVoice Website - [Company Name]"
-   **To**: sales@safevoice.tz
-   **Reply-To**: Customer's email address
-   **Contains**: Full inquiry details, contact information, and quick action links

### Customer Confirmation Email

-   **Subject**: "Thank You for Your Interest in SafeVoice"
-   **To**: Customer's email address
-   **Contains**: Thank you message, expected response time, contact information

---

## Employee Size Options

Common values for the `employees` field:

-   `"1-10"`
-   `"11-50"`
-   `"51-200"`
-   `"201-500"`
-   `"501-1000"`
-   `"1000+"`

---

## Validation Rules

-   **name**: Required, string, max 255 characters
-   **email**: Required, valid email format, max 255 characters
-   **company**: Required, string, max 255 characters
-   **phone**: Required, string, max 20 characters
-   **employees**: Required, string, max 50 characters
-   **message**: Required, string, max 2000 characters

---

## Response Codes

| Status Code | Description                              |
| ----------- | ---------------------------------------- |
| 200         | Success - Inquiry submitted successfully |
| 422         | Validation Error - Invalid input         |
| 500         | Server Error - Contact support           |

---

## Notes

-   This is a public endpoint - no authentication required
-   Rate limiting may apply to prevent abuse
-   Both emails are sent asynchronously (confirmation email failure won't affect main submission)
-   All email failures are logged for monitoring

---

## Testing

Test the endpoint using the provided test script:

```bash
php test_sales_inquiry.php
```

Or test directly with cURL:

```bash
curl -X POST http://localhost:8000/api/public/sales-inquiry \
  -H 'Content-Type: application/json' \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "company": "Test Corp",
    "phone": "+255712345678",
    "employees": "51-200",
    "message": "This is a test inquiry"
  }'
```

---

## Support

For issues or questions about this API:

-   Email: sales@safevoice.tz
-   Check email logs if emails aren't being delivered
-   Verify mail configuration in `.env` file

---

**Last Updated:** January 9, 2026  
**Version:** 1.0  
**Status:** ✅ Production Ready
