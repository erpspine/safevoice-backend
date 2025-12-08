# Email Notification System - Quick Test Guide

## Prerequisites

Before testing the email notification system, ensure:

1. ✅ Queue system is configured (database driver)
2. ✅ Mail system is configured (log driver for dev)
3. ✅ Notifications table has required columns
4. ✅ Users have `recipient_type` set (primary/alternative)

## Quick Test Steps

### Step 1: Verify Test Recipients

Create test users with recipient types:

```sql
-- Check existing users with recipient types
SELECT id, name, email, branch_id, recipient_type, status, is_verified
FROM users
WHERE recipient_type IS NOT NULL
ORDER BY branch_id, recipient_type;

-- If no recipients exist, update a user:
UPDATE users
SET recipient_type = 'primary',
    is_verified = true,
    status = 'active'
WHERE email = 'test@example.com';
```

### Step 2: Submit a Test Case

Using the test page at `http://localhost:8000/test-case-api.html`:

1. Fill in the form with:

    - Case Type: incident
    - Branch: Select a branch that has recipients
    - Department: Any department in that branch
    - Incident Date: Any valid date
    - Description: "Test case for email notifications"

2. Click "Submit Case"

3. Note the Case Token from the response

### Step 3: Check Notification Records

Open your database and check:

```sql
-- View created notifications
SELECT
    id,
    case_id,
    user_id,
    status,
    channel,
    subject,
    sent_at,
    failed_at,
    error_message,
    created_at
FROM notifications
ORDER BY created_at DESC
LIMIT 5;

-- Get recipient details
SELECT
    n.id,
    n.status,
    n.subject,
    u.name as recipient_name,
    u.email as recipient_email,
    n.sent_at,
    n.created_at
FROM notifications n
JOIN users u ON n.user_id = u.id
ORDER BY n.created_at DESC
LIMIT 5;
```

Expected result:

-   Status should be 'sent' (if email queued successfully)
-   `sent_at` should have a timestamp
-   `channel` should be 'email'

### Step 4: Process the Email Queue

Run the queue worker to process pending emails:

```bash
# Process one job and stop
php artisan queue:work --once

# Or run continuously
php artisan queue:work --verbose
```

### Step 5: Check Email Logs

Since we're using the 'log' mail driver in development:

```bash
# View latest log entries
tail -n 100 storage/logs/laravel.log

# Or search for email content
grep -A 30 "New Case Submitted" storage/logs/laravel.log | tail -50
```

You should see the full email HTML content in the logs.

## Expected Email Content

The logged email should contain:

-   Subject: "New Case Submitted - SV-2025-XXXXXX"
-   Recipient email address
-   HTML body with:
    -   Case token
    -   Submission and incident dates
    -   Branch and department names
    -   Incident location
    -   Description preview
    -   "View Case Details" button
    -   Alert message (if alternative recipient)

## Verification Checklist

-   [ ] Notification record created in database
-   [ ] Notification status is 'sent'
-   [ ] `sent_at` timestamp is populated
-   [ ] Queue job was created in `jobs` table
-   [ ] Queue worker processed the job
-   [ ] Email content appears in `storage/logs/laravel.log`
-   [ ] Email shows correct case information
-   [ ] Recipient received correct notification type message

## Common Issues & Solutions

### Issue: No notifications created

**Solution**:

-   Verify branch has users with `recipient_type` set
-   Check if all users are involved in the case
-   Review logs for "No recipients available" warning

### Issue: Notification status is 'failed'

**Solution**:

-   Check `error_message` column in notifications table
-   Verify user has valid email address
-   Check mail configuration in `.env`

### Issue: Queue job not processing

**Solution**:

```bash
# Check if jobs exist
SELECT * FROM jobs LIMIT 5;

# Run queue worker manually
php artisan queue:work --once

# Check for failed jobs
php artisan queue:failed
```

### Issue: Email not in logs

**Solution**:

-   Verify `MAIL_MAILER=log` in `.env`
-   Check if queue worker is running
-   Look for errors: `grep ERROR storage/logs/laravel.log`

## Advanced Testing

### Test Alternative Recipients

Create a case where all primary recipients are involved:

1. Create a branch with 2 primary recipients
2. Create 1 alternative recipient
3. Submit a case involving both primary recipients
4. Verify notification goes to alternative recipient

```sql
-- Set recipient types for testing
UPDATE users SET recipient_type = 'primary' WHERE id IN ('id1', 'id2');
UPDATE users SET recipient_type = 'alternative' WHERE id = 'id3';
```

### Test Multiple Recipients

1. Create multiple recipients in same branch
2. Submit a case not involving any of them
3. Verify all receive notifications

```sql
-- Check all notifications for a case
SELECT
    u.name,
    u.email,
    n.status,
    n.sent_at
FROM notifications n
JOIN users u ON n.user_id = u.id
WHERE n.case_id = 'YOUR_CASE_ID';
```

### Simulate Email Failure

Temporarily break the mail config to test error handling:

```php
// In config/mail.php, temporarily set:
'default' => env('MAIL_MAILER', 'invalid_driver'),
```

Submit a case and verify:

-   Notification status becomes 'failed'
-   `failed_at` is populated
-   Error is logged

## Monitoring in Production

### Set up real-time monitoring

```bash
# Watch for new notifications
watch -n 2 'mysql -u user -p database -e "SELECT COUNT(*) as total, status FROM notifications GROUP BY status"'

# Monitor queue processing
php artisan queue:work --verbose --sleep=3 --tries=3

# Track email sending
tail -f storage/logs/laravel.log | grep -i "notification\|mail"
```

### Health checks

```sql
-- Notifications sent in last hour
SELECT COUNT(*) as sent_last_hour
FROM notifications
WHERE sent_at >= NOW() - INTERVAL 1 HOUR
AND status = 'sent';

-- Failed notifications
SELECT COUNT(*) as failed_count
FROM notifications
WHERE status = 'failed'
AND created_at >= NOW() - INTERVAL 24 HOUR;

-- Pending notifications (should be low)
SELECT COUNT(*) as pending_count
FROM notifications
WHERE status = 'pending';
```

## Next Steps

1. ✅ Test with actual SMTP server (Mailtrap, Gmail, etc.)
2. ✅ Set up queue worker as a service (Supervisor)
3. ✅ Configure frontend_url for production case links
4. ✅ Add email rate limiting if needed
5. ✅ Set up monitoring/alerts for failed emails

## Support

For issues or questions, check:

-   `storage/logs/laravel.log` for detailed errors
-   `EMAIL-NOTIFICATION-GUIDE.md` for complete documentation
-   Laravel documentation: https://laravel.com/docs/mail
