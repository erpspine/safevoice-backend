# SafeVoice SMS API Documentation

## Overview

The SafeVoice SMS API provides comprehensive SMS messaging capabilities integrated with the messaging-service.co.tz platform. It supports single and bulk SMS sending, user invitations, verification codes, and phone number validation.

## Base URL

-   Development: `http://localhost:8000/api/sms/test`
-   Production: Replace with your production domain

## Authentication

Currently, the test endpoints don't require authentication. For production use, implement proper authentication middleware.

## API Endpoints

### 1. Get SMS Service Status

**GET** `/status`

Returns the current status of the SMS service.

**Response:**

```json
{
    "success": true,
    "message": "SMS service status retrieved successfully",
    "data": {
        "enabled": true,
        "driver": "messaging_service",
        "from": "SAFE VOICE",
        "timeout": 30,
        "debug": false
    }
}
```

### 2. Send Single SMS

**POST** `/send-single`

Send an SMS to a single phone number.

**Request Body:**

```json
{
    "phone_number": "0760299974",
    "message": "Your SMS message content",
    "reference": "optional-reference-id"
}
```

**Response:**

```json
{
    "success": true,
    "message": "SMS sent successfully",
    "data": {
        "reference": "SMS_abc123",
        "phone_numbers": ["255760299974"],
        "message": "Your SMS message content",
        "api_response": {
            "status": "PENDING_ENROUTE",
            "message_id": "12345"
        }
    }
}
```

### 3. Send Multiple SMS

**POST** `/send-multiple`

Send the same SMS to multiple phone numbers.

**Request Body:**

```json
{
    "phone_numbers": ["0760299974", "0712345678"],
    "message": "Bulk SMS message",
    "reference": "optional-reference-id"
}
```

**Response:**

```json
{
    "success": true,
    "message": "Bulk SMS sent successfully",
    "data": {
        "reference": "BULK_abc123",
        "phone_numbers": ["255760299974", "255712345678"],
        "message": "Bulk SMS message",
        "results": [
            {
                "phone": "255760299974",
                "status": "PENDING_ENROUTE",
                "message_id": "12345"
            },
            {
                "phone": "255712345678",
                "status": "PENDING_ENROUTE",
                "message_id": "12346"
            }
        ]
    }
}
```

### 4. Send Invitation SMS

**POST** `/send-invitation`

Send a formatted invitation SMS to a new user.

**Request Body:**

```json
{
    "phone_number": "0760299974",
    "user_name": "John Doe",
    "company_name": "SafeVoice Ltd",
    "invitation_link": "https://app.safevoice.tz/accept-invitation?token=abc123"
}
```

**Response:**

```json
{
    "success": true,
    "message": "Invitation SMS sent successfully",
    "data": {
        "reference": "INVITE_xyz789",
        "phone_number": "255760299974",
        "user_name": "John Doe",
        "company_name": "SafeVoice Ltd",
        "api_response": {
            "status": "PENDING_ENROUTE",
            "message_id": "12347"
        }
    }
}
```

### 5. Send Verification SMS

**POST** `/send-verification`

Send a verification code SMS.

**Request Body:**

```json
{
    "phone_number": "0760299974",
    "verification_code": "123456"
}
```

**Response:**

```json
{
    "success": true,
    "message": "Verification SMS sent successfully",
    "data": {
        "reference": "VERIFY_def456",
        "phone_number": "255760299974",
        "verification_code": "123456",
        "api_response": {
            "status": "PENDING_ENROUTE",
            "message_id": "12348"
        }
    }
}
```

### 6. Send Password Reset SMS

**POST** `/send-password-reset`

Send a password reset SMS with a secure link.

**Request Body:**

```json
{
    "phone_number": "0760299974",
    "user_name": "John Doe",
    "reset_link": "https://app.safevoice.tz/reset-password?token=xyz789"
}
```

**Response:**

```json
{
    "success": true,
    "message": "Password reset SMS sent successfully",
    "data": {
        "reference": "RESET_ghi789",
        "phone_number": "255760299974",
        "user_name": "John Doe",
        "api_response": {
            "status": "PENDING_ENROUTE",
            "message_id": "12349"
        }
    }
}
```

### 7. Validate Phone Number

**POST** `/validate-phone`

Validate and format a phone number for Tanzanian mobile networks.

**Request Body:**

```json
{
    "phone_number": "0760299974"
}
```

**Response:**

```json
{
    "success": true,
    "message": "Phone number validated successfully",
    "data": {
        "original": "0760299974",
        "formatted": "255760299974",
        "is_valid": true,
        "country_code": "255",
        "network_prefix": "76"
    }
}
```

### 8. API Documentation

**GET** `/documentation`

Returns comprehensive API documentation including all available endpoints.

## Phone Number Format

-   **Input formats accepted:** 0XXXXXXXXX, +255XXXXXXXXX, 255XXXXXXXXX
-   **Output format:** 255XXXXXXXXX (international format)
-   **Supported networks:** Vodacom, Airtel, Tigo, Halotel, TTCLs

## Error Responses

All endpoints return errors in the following format:

```json
{
    "success": false,
    "message": "Error description",
    "errors": {
        "field_name": ["Validation error messages"]
    }
}
```

### Common HTTP Status Codes:

-   `200` - Success
-   `400` - Bad Request (validation errors)
-   `500` - Internal Server Error

## Rate Limiting

Consider implementing rate limiting for production use to prevent abuse.

## Testing Tools

### 1. PHP Test Script

Run the included test script:

```bash
php test_sms_api.php
```

### 2. Web Interface

Access the HTML test interface at:

```
http://localhost/safevoicebackend/public/sms-test.html
```

### 3. Postman Collection

Import the included Postman collection: `SMS_API_Test_Collection.postman_collection.json`

## Configuration

### Environment Variables

```env
SMS_DRIVER=messaging_service
SMS_ENDPOINT=https://messaging-service.co.tz/api/sms/v1/text/single
SMS_USERNAME=your_username
SMS_PASSWORD=your_password
SMS_FROM="SAFE VOICE"
SMS_TIMEOUT=30
SMS_DEBUG=false
```

### Laravel Config

Configuration is stored in `config/sms.php`:

```php
return [
    'default' => env('SMS_DRIVER', 'messaging_service'),
    'drivers' => [
        'messaging_service' => [
            'endpoint' => env('SMS_ENDPOINT'),
            'username' => env('SMS_USERNAME'),
            'password' => env('SMS_PASSWORD'),
            'from' => env('SMS_FROM', 'SAFE VOICE'),
            'timeout' => env('SMS_TIMEOUT', 30),
        ],
    ],
    'debug' => env('SMS_DEBUG', false),
];
```

## Production Considerations

1. **Authentication**: Implement proper API authentication (Bearer tokens, API keys)
2. **Rate Limiting**: Add rate limiting middleware
3. **Logging**: Monitor SMS usage and delivery status
4. **Error Handling**: Implement proper error logging and notification
5. **Webhooks**: Consider implementing delivery status webhooks
6. **Queue**: Use Laravel queues for bulk SMS operations
7. **Validation**: Add more robust phone number validation
8. **Monitoring**: Monitor SMS costs and usage patterns

## Support

For support with the SMS integration, contact the development team or check the application logs for detailed error information.
