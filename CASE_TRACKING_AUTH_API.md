# Case Tracking Authentication API Documentation

## Overview

The Case Tracking Authentication API provides a secure way for users to login with their case access credentials and track their case progress using session tokens. This is an alternative to the direct tracking method that doesn't require sending credentials with every request.

## Base URL

```
http://localhost:8000/api/public/cases
```

## Authentication Flow

### 1. Login

**Endpoint:** `POST /api/public/cases/login`

**Description:** Authenticate using access ID and password to get a session token.

**Request Body:**

```json
{
    "access_id": "CASE123",
    "access_password": "your_password"
}
```

**Success Response (200):**

```json
{
    "status": "success",
    "message": "Login successful",
    "data": {
        "access_token": "6kFx6oJQgQqG4YrlINpdcYnLB4ovm89VGpWL09vcQrI...",
        "token_type": "Bearer",
        "expires_in": 86400,
        "expires_at": "2025-10-26T19:09:01.134202Z",
        "case_info": {
            "case_id": "01k8eaegjet3hbj18nmrhyn144",
            "case_number": "PARTY8499",
            "status": "open",
            "submitted_at": "2025-10-25T18:35:14.000000Z"
        }
    }
}
```

**Error Response (401):**

```json
{
    "status": "error",
    "message": "Invalid access credentials"
}
```

### 2. Get Case Details

**Endpoint:** `POST /api/public/cases/details`

**Description:** Get comprehensive case details using the session token.

**Request Body:**

```json
{
    "access_token": "your_session_token_here"
}
```

**Success Response (200):**

```json
{
    "status": "success",
    "data": {
        "case_id": "01k8eaegjet3hbj18nmrhyn144",
        "case_number": "PARTY8499",
        "description": "Test incident with updated party structure",
        "status": "open",
        "priority": "high",
        "location_description": "Main office conference room B",
        "date_time_type": "specific",
        "date_occurred": "2025-10-24T00:00:00.000000Z",
        "time_occurred": "14:30:00",
        "general_timeframe": null,
        "company_relationship": "employee",
        "submitted_at": "2025-10-25T18:35:14.000000Z",
        "last_updated": "2025-10-25T19:09:01.000000Z",
        "company": {
            "id": "01k7rjt9vjh4zdkv38nq4akwdj",
            "name": "SafeVoice Admin"
        },
        "branch": {
            "id": "01k86f7tpxc1r3rkzn0abh30er",
            "name": "Arusha Branch"
        },
        "category": {
            "id": "01k87z5gjf91q70gx7hajr7k34",
            "name": "Theft"
        },
        "files_count": 0,
        "parties_count": 2,
        "additional_parties_count": 2,
        "is_anonymous": false,
        "follow_up_required": true,
        "resolution_note": null,
        "resolved_at": null,
        "files": [],
        "involved_parties": [
            {
                "employee_id": "EMP001",
                "nature_of_involvement": "Witnessed the incident firsthand"
            }
        ],
        "additional_parties": [
            {
                "name": "Jane Smith",
                "email": "jane@external.com",
                "phone": "555-0456",
                "job_title": "External Consultant",
                "role": "Witness"
            }
        ],
        "timeline": {
            "submitted": "2025-10-25T18:35:14.000000Z",
            "last_update": "2025-10-25T19:09:01.000000Z",
            "resolved": null
        }
    }
}
```

**Error Response (401):**

```json
{
    "status": "error",
    "message": "Invalid or expired session token"
}
```

### 3. Logout

**Endpoint:** `POST /api/public/cases/logout`

**Description:** Invalidate the session token and logout.

**Request Body:**

```json
{
    "access_token": "your_session_token_here"
}
```

**Success Response (200):**

```json
{
    "status": "success",
    "message": "Logged out successfully"
}
```

## Security Features

1. **Session Tokens**: 64-character random tokens that are hashed before storage
2. **Token Expiration**: Tokens automatically expire after 24 hours
3. **Secure Storage**: Passwords are bcrypt hashed, session tokens are also hashed
4. **Auto-cleanup**: Expired tokens are automatically ignored
5. **Manual Invalidation**: Users can explicitly logout to invalidate tokens

## Usage Examples

### JavaScript/Frontend Example

```javascript
// Login
const loginResponse = await fetch("/api/public/cases/login", {
    method: "POST",
    headers: {
        "Content-Type": "application/json",
    },
    body: JSON.stringify({
        access_id: "CASE123",
        access_password: "password123",
    }),
});

const loginData = await loginResponse.json();
const accessToken = loginData.data.access_token;

// Get case details
const detailsResponse = await fetch("/api/public/cases/details", {
    method: "POST",
    headers: {
        "Content-Type": "application/json",
    },
    body: JSON.stringify({
        access_token: accessToken,
    }),
});

const caseDetails = await detailsResponse.json();

// Logout
await fetch("/api/public/cases/logout", {
    method: "POST",
    headers: {
        "Content-Type": "application/json",
    },
    body: JSON.stringify({
        access_token: accessToken,
    }),
});
```

### cURL Examples

```bash
# Login
curl -X POST http://localhost:8000/api/public/cases/login \
  -H "Content-Type: application/json" \
  -d '{"access_id":"CASE123","access_password":"password123"}'

# Get Details
curl -X POST http://localhost:8000/api/public/cases/details \
  -H "Content-Type: application/json" \
  -d '{"access_token":"your_token_here"}'

# Logout
curl -X POST http://localhost:8000/api/public/cases/logout \
  -H "Content-Type: application/json" \
  -d '{"access_token":"your_token_here"}'
```

## Error Codes

-   **400**: Bad Request (validation errors)
-   **401**: Unauthorized (invalid credentials or expired token)
-   **422**: Unprocessable Entity (validation failed)
-   **500**: Internal Server Error

## Benefits Over Direct Tracking

1. **Enhanced Security**: Credentials are only sent once during login
2. **Better UX**: Users don't need to enter credentials repeatedly
3. **Session Management**: Proper session handling with expiration
4. **Audit Trail**: Login/logout activities can be tracked
5. **Scalability**: Reduces password verification overhead
