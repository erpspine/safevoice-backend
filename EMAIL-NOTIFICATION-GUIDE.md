# Case Notification Email System

## Overview

The system automatically sends email notifications when new cases are submitted. Emails are sent to appropriate recipients based on the following logic:

1. **Primary Recipients**: Users with `recipient_type = 'primary'` in the case's branch
2. **Involved Party Filtering**: Any users involved in the case are excluded from receiving notifications
3. **Alternative Recipients**: If all primary recipients are involved in the case, notifications go to users with `recipient_type = 'alternative'`

## Components

### 1. Mailable Class

**File**: `app/Mail/NewCaseNotification.php`

-   Implements `ShouldQueue` for asynchronous email sending
-   Accepts: `CaseModel $case`, `User $recipient`, `string $recipientType`
-   Email subject: "New Case Submitted - {case_token}"
-   Uses Blade template: `resources/views/emails/new-case-notification.blade.php`

### 2. Email Template

**File**: `resources/views/emails/new-case-notification.blade.php`

-   Professional HTML email design
-   Displays:
    -   Case token
    -   Submission date
    -   Incident date
    -   Branch and department
    -   Incident location
    -   Case description (limited to 200 characters)
    -   Link to view full case details
-   Special alert for alternative recipients

### 3. Controller Logic

**File**: `app/Http/Controllers/Api/Public/CaseSubmissionController.php`
**Method**: `sendCaseNotifications()`

Flow:

1. Get all involved party user IDs
2. Fetch primary recipients for the branch
3. Filter out involved parties
4. If no eligible primaries, use alternative recipients
5. Create notification record in database
6. Queue email for each recipient
7. Update notification status (sent/failed)

## Email Queue System

### Configuration

-   **Queue Driver**: Database (`config/queue.php`)
-   **Mail Driver**: Log (development) / SMTP (production)
-   Emails are queued for async processing using Laravel's queue system

### Running the Queue Worker

To process queued emails, run:

```bash
php artisan queue:work
```

For development, you can run in verbose mode:

```bash
php artisan queue:work --verbose
```

## Testing the System

### 1. Check Mail Configuration

Emails are logged to `storage/logs/laravel.log` by default in development.

To see sent emails:

```bash
tail -f storage/logs/laravel.log | grep -A 50 "New Case Submitted"
```

### 2. Submit a Test Case

Use the test page at `http://localhost:8000/test-case-api.html` or submit via API:

```javascript
const formData = new FormData();
formData.append("case_type", "incident");
formData.append("branch_id", "YOUR_BRANCH_ID");
formData.append("department_id", "YOUR_DEPARTMENT_ID");
formData.append("incident_date", "2025-01-15");
formData.append("description", "Test case for notification");

axios.post("/api/public/cases/submit", formData).then((response) => {
    console.log("Case submitted:", response.data);
});
```

### 3. Verify Notifications

Check the `notifications` table:

```sql
SELECT * FROM notifications
WHERE case_id = 'YOUR_CASE_ID'
ORDER BY created_at DESC;
```

Expected columns:

-   `status`: 'sent' or 'failed'
-   `sent_at`: Timestamp when email was queued
-   `failed_at`: Timestamp if email failed
-   `channel`: 'email'

### 4. Process Queue Manually

If queue worker isn't running:

```bash
php artisan queue:work --once
```

## Notification Status Tracking

### Status Values

-   **pending**: Notification created, email not yet sent
-   **sent**: Email successfully queued/sent
-   **failed**: Email sending failed

### Database Fields

-   `sent_at`: Timestamp when email was sent
-   `failed_at`: Timestamp when sending failed
-   `error_message`: Error details if sending failed
-   `retry_count`: Number of retry attempts

## Recipient Type Logic

### Primary Recipients

```php
User::where('branch_id', $branchId)
    ->where('recipient_type', 'primary')
    ->where('status', 'active')
    ->where('is_verified', true)
    ->get();
```

### Alternative Recipients

Used when all primary recipients are involved in the case:

```php
User::where('branch_id', $branchId)
    ->where('recipient_type', 'alternative')
    ->where('status', 'active')
    ->where('is_verified', true)
    ->get();
```

## Email Template Variables

Available in the Blade template:

-   `$case`: Full CaseModel instance
-   `$recipient`: User receiving the notification
-   `$recipientType`: 'primary' or 'alternative'
-   `$caseUrl`: Frontend URL to view case details

## Production Configuration

### SMTP Setup

Update `.env` file:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@safevoice.tz
MAIL_FROM_NAME="${APP_NAME}"
```

### Queue Worker as Service

For production, run queue worker as a background service (using Supervisor):

```ini
[program:safevoice-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/worker.log
```

## Troubleshooting

### No Emails Received

1. Check if queue worker is running
2. Verify mail configuration in `.env`
3. Check `storage/logs/laravel.log` for errors
4. Verify recipient has valid email address
5. Check `notifications` table for status

### Failed Notifications

Check the `error_message` field in notifications table:

```sql
SELECT id, user_id, status, error_message, created_at
FROM notifications
WHERE status = 'failed'
ORDER BY created_at DESC;
```

### Queue Jobs Not Processing

```bash
# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear all jobs
php artisan queue:flush
```

## Logs and Monitoring

### Key Log Messages

-   "Case notification email queued" - Successful email queuing
-   "Failed to queue case notification email" - Email sending error
-   "No recipients available for case notification" - No eligible recipients
-   "Failed to send case notifications" - General notification error

### Check Logs

```bash
# Real-time log monitoring
tail -f storage/logs/laravel.log

# Filter for notification logs
grep "notification" storage/logs/laravel.log

# Check for errors
grep "ERROR" storage/logs/laravel.log | grep -i "mail\|notification"
```

## API Response

When a case is submitted successfully:

```json
{
    "success": true,
    "message": "Case submitted successfully",
    "data": {
        "case": {
            "id": "01JJXXXXXXXXXXXXXXXXXXXXXX",
            "case_token": "SV-2025-001234"
            // ... other case fields
        },
        "access_info": {
            "access_id": "ABC123XYZ",
            "case_token": "SV-2025-001234"
        }
    }
}
```

The notification and email sending happens in the background and doesn't affect the API response.
