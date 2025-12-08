# Case Messaging API Documentation

This API provides messaging functionality between case investigators and case reporters with file attachment support.

## Authentication

### Public Case Messaging (For Case Reporters)

-   **Middleware**: `case.session`
-   **Authentication**: Session token obtained through case tracking login
-   **Header**: `X-Session-Token: {session_token}` or `Authorization: Bearer {session_token}`

### Admin Case Messaging (For Investigators)

-   **Middleware**: `auth:sanctum`
-   **Authentication**: Standard admin/user authentication token
-   **Header**: `Authorization: Bearer {access_token}`

---

## Public Case Messaging API (For Case Reporters)

### 1. Get Messages

Retrieve all public messages for a case (reporters can only see public messages).

**Endpoint**: `GET /api/public/cases/{caseId}/messages`

**Headers**:

```
X-Session-Token: {session_token}
Content-Type: application/json
```

**Query Parameters**:

-   `page` (optional): Page number (default: 1)
-   `per_page` (optional): Items per page (default: 20, max: 50)

**Response Example**:

```json
{
    "status": "success",
    "data": {
        "messages": [
            {
                "id": "01HXXX",
                "sender_type": "investigator",
                "sender_name": "John Investigator",
                "visibility": "public",
                "message": "We have received your case and assigned it for investigation.",
                "message_type": "comment",
                "priority": "normal",
                "has_attachments": false,
                "attachments": [],
                "is_read": false,
                "created_at": "2024-01-01T10:00:00Z"
            }
        ],
        "pagination": {
            "current_page": 1,
            "total_pages": 1,
            "per_page": 20,
            "total": 1,
            "has_more_pages": false
        }
    }
}
```

### 2. Send Message

Send a message from the case reporter to investigators.

**Endpoint**: `POST /api/public/cases/{caseId}/messages`

**Headers**:

```
X-Session-Token: {session_token}
Content-Type: multipart/form-data
```

**Body Parameters**:

-   `message` (required): Message text (max: 5000 chars)
-   `attachments[]` (optional): Files (max 10 files, 10MB each)
-   `parent_message_id` (optional): ID of message being replied to

**Supported file types**: jpg, jpeg, png, pdf, doc, docx, txt, xlsx, xls

**Response Example**:

```json
{
    "status": "success",
    "message": "Message sent successfully",
    "data": {
        "message": {
            "id": "01HYYY",
            "sender_type": "reporter",
            "sender_name": "Case Reporter",
            "visibility": "public",
            "message": "Thank you for the update. I have additional evidence to share.",
            "has_attachments": true,
            "attachments": [
                {
                    "original_name": "evidence.pdf",
                    "stored_name": "01HZZZ.pdf",
                    "mime_type": "application/pdf",
                    "size": 1024000,
                    "uploaded_at": "2024-01-01T10:30:00Z"
                }
            ],
            "created_at": "2024-01-01T10:30:00Z"
        }
    }
}
```

### 3. Mark Messages as Read

Mark specific messages as read by the case reporter.

**Endpoint**: `PUT /api/public/cases/{caseId}/messages/read`

**Headers**:

```
X-Session-Token: {session_token}
Content-Type: application/json
```

**Body**:

```json
{
    "message_ids": ["01HXXX", "01HYYY"]
}
```

**Response**:

```json
{
    "status": "success",
    "message": "Messages marked as read",
    "data": {
        "updated_count": 2
    }
}
```

---

## Admin Case Messaging API (For Investigators)

### 1. Get Messages

Retrieve all messages for a case (investigators can see both public and internal messages).

**Endpoint**: `GET /api/admin/cases/{caseId}/messages`

**Headers**:

```
Authorization: Bearer {access_token}
Content-Type: application/json
```

**Query Parameters**:

-   `page` (optional): Page number (default: 1)
-   `per_page` (optional): Items per page (default: 20, max: 50)
-   `visibility` (optional): Filter by visibility (`public`, `internal`, `all` - default: `all`)

**Response**: Similar to public API but includes internal messages and additional metadata.

### 2. Send Message

Send a message from investigator (can be public or internal).

**Endpoint**: `POST /api/admin/cases/{caseId}/messages`

**Headers**:

```
Authorization: Bearer {access_token}
Content-Type: multipart/form-data
```

**Body Parameters**:

-   `message` (required): Message text (max: 5000 chars)
-   `visibility` (required): `public` or `internal`
-   `message_type` (optional): `comment`, `update`, `notification`, `status_change`, `assignment`
-   `priority` (optional): `low`, `normal`, `high`, `urgent`
-   `parent_message_id` (optional): ID of message being replied to
-   `attachments[]` (optional): Files (max 10 files, 10MB each)

### 3. Mark Messages as Read

Mark messages as read by investigator.

**Endpoint**: `PUT /api/admin/cases/{caseId}/messages/read`

Similar to public API but tracks which investigator read the messages.

### 4. Get Message Statistics

Get messaging statistics for a case.

**Endpoint**: `GET /api/admin/cases/{caseId}/messages/stats`

**Headers**:

```
Authorization: Bearer {access_token}
Content-Type: application/json
```

**Response Example**:

```json
{
    "status": "success",
    "data": {
        "case_id": "01HXXX",
        "statistics": {
            "total_messages": 25,
            "public_messages": 18,
            "internal_messages": 7,
            "unread_messages": 3,
            "messages_with_attachments": 5,
            "by_sender_type": {
                "reporter": 12,
                "investigator": 13
            },
            "by_message_type": {
                "comment": 20,
                "update": 3,
                "status_change": 2
            },
            "last_message": {
                "created_at": "2024-01-01T15:30:00Z",
                "sender_type": "investigator",
                "message_type": "update"
            }
        }
    }
}
```

---

## Message Types

-   **comment**: General conversation message
-   **update**: Case status or progress update
-   **notification**: System or procedural notification
-   **status_change**: Case status change notification
-   **assignment**: Case assignment change

## Priority Levels

-   **low**: Non-urgent communication
-   **normal**: Standard priority (default)
-   **high**: Important, needs attention
-   **urgent**: Critical, immediate attention required

## Visibility Types

-   **public**: Visible to both investigators and case reporters
-   **internal**: Only visible to investigators and case handlers

## Error Codes

-   `SESSION_TOKEN_MISSING`: No session token provided
-   `SESSION_INVALID`: Invalid or expired session token
-   `SESSION_CASE_MISMATCH`: Session token doesn't match requested case
-   `CASE_NOT_FOUND`: Case doesn't exist
-   `UNAUTHORIZED_ACCESS`: User doesn't have permission to access case
-   `VALIDATION_ERROR`: Request validation failed
-   `FILE_UPLOAD_ERROR`: Problem with file attachment

## File Storage

Attachments are stored securely and are only accessible to authorized users. File paths are not directly exposed in the API responses.
