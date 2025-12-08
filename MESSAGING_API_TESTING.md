# Case Messaging API Testing Guide

## Prerequisites

1. Have a case with a valid case_token
2. For public API: Login using the case tracking endpoint to get a session token
3. For admin API: Have an authenticated admin/investigator user

## Testing Workflow

### Step 1: Case Tracking Login (Public API Setup)

```bash
# Login to get session token
curl -X POST http://localhost/api/public/cases/login \
  -H "Content-Type: application/json" \
  -d '{
    "case_token": "YOUR_CASE_TOKEN_HERE",
    "contact_info": "reporter@example.com"
  }'

# Response will include session_token - use this for messaging API
```

### Step 2: Test Public Messaging (Case Reporter)

#### Get Messages

```bash
curl -X GET "http://localhost/api/public/cases/{CASE_ID}/messages?page=1&per_page=20" \
  -H "X-Session-Token: YOUR_SESSION_TOKEN"
```

#### Send Message (Text Only)

```bash
curl -X POST "http://localhost/api/public/cases/{CASE_ID}/messages" \
  -H "X-Session-Token: YOUR_SESSION_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "message": "I have some additional information about the incident."
  }'
```

#### Send Message with Attachment

```bash
curl -X POST "http://localhost/api/public/cases/{CASE_ID}/messages" \
  -H "X-Session-Token: YOUR_SESSION_TOKEN" \
  -F "message=Here is the evidence file you requested." \
  -F "attachments[]=@/path/to/your/file.pdf"
```

#### Mark Messages as Read

```bash
curl -X PUT "http://localhost/api/public/cases/{CASE_ID}/messages/read" \
  -H "X-Session-Token: YOUR_SESSION_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "message_ids": ["MESSAGE_ID_1", "MESSAGE_ID_2"]
  }'
```

### Step 3: Test Admin Messaging (Investigator)

#### Get Messages (All visibility levels)

```bash
curl -X GET "http://localhost/api/admin/cases/{CASE_ID}/messages?visibility=all" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

#### Send Public Message to Reporter

```bash
curl -X POST "http://localhost/api/admin/cases/{CASE_ID}/messages" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Thank you for the additional information. We are reviewing the case.",
    "visibility": "public",
    "message_type": "update",
    "priority": "normal"
  }'
```

#### Send Internal Message (Not visible to reporter)

```bash
curl -X POST "http://localhost/api/admin/cases/{CASE_ID}/messages" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Internal note: Need to schedule interview with witness.",
    "visibility": "internal",
    "message_type": "comment",
    "priority": "high"
  }'
```

#### Get Message Statistics

```bash
curl -X GET "http://localhost/api/admin/cases/{CASE_ID}/messages/stats" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

## Sample Integration Code

### JavaScript Frontend (Case Reporter Portal)

```javascript
class CaseMessaging {
    constructor(sessionToken, caseId) {
        this.sessionToken = sessionToken;
        this.caseId = caseId;
        this.baseUrl = "/api/public/cases";
    }

    async getMessages(page = 1, perPage = 20) {
        const response = await fetch(
            `${this.baseUrl}/${this.caseId}/messages?page=${page}&per_page=${perPage}`,
            {
                headers: {
                    "X-Session-Token": this.sessionToken,
                    "Content-Type": "application/json",
                },
            }
        );
        return response.json();
    }

    async sendMessage(message, files = null) {
        const formData = new FormData();
        formData.append("message", message);

        if (files) {
            Array.from(files).forEach((file, index) => {
                formData.append(`attachments[]`, file);
            });
        }

        const response = await fetch(
            `${this.baseUrl}/${this.caseId}/messages`,
            {
                method: "POST",
                headers: {
                    "X-Session-Token": this.sessionToken,
                },
                body: formData,
            }
        );
        return response.json();
    }

    async markAsRead(messageIds) {
        const response = await fetch(
            `${this.baseUrl}/${this.caseId}/messages/read`,
            {
                method: "PUT",
                headers: {
                    "X-Session-Token": this.sessionToken,
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({ message_ids: messageIds }),
            }
        );
        return response.json();
    }
}

// Usage
const messaging = new CaseMessaging("session_token_here", "case_id_here");
messaging.getMessages().then((data) => console.log(data));
```

### PHP Backend (Admin Panel)

```php
class AdminCaseMessaging {
    private $token;
    private $baseUrl;

    public function __construct($adminToken) {
        $this->token = $adminToken;
        $this->baseUrl = '/api/admin/cases';
    }

    public function getMessages($caseId, $visibility = 'all', $page = 1) {
        $url = "{$this->baseUrl}/{$caseId}/messages?visibility={$visibility}&page={$page}";

        $response = $this->makeRequest('GET', $url);
        return json_decode($response, true);
    }

    public function sendMessage($caseId, $message, $visibility = 'public', $options = []) {
        $data = array_merge([
            'message' => $message,
            'visibility' => $visibility
        ], $options);

        $response = $this->makeRequest('POST', "{$this->baseUrl}/{$caseId}/messages", $data);
        return json_decode($response, true);
    }

    private function makeRequest($method, $url, $data = null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json'
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }
}
```

## Expected Response Formats

### Success Response

```json
{
    "status": "success",
    "message": "Operation completed successfully",
    "data": {
        // Response data here
    }
}
```

### Error Response

```json
{
    "status": "error",
    "message": "Error description",
    "code": "ERROR_CODE",
    "errors": {
        // Validation errors if applicable
    }
}
```

## Common HTTP Status Codes

-   `200`: Success
-   `201`: Created (new message)
-   `400`: Bad Request (validation error)
-   `401`: Unauthorized (invalid token)
-   `403`: Forbidden (no access to case)
-   `404`: Not Found (case or message not found)
-   `422`: Unprocessable Entity (validation failed)
-   `500`: Internal Server Error
