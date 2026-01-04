# Case Tracking Module Documentation

## Overview

The case tracking module allows you to track the complete lifecycle of a case from submission to closure, including duration tracking at each stage and automatic escalation when cases are overdue.

---

## Dual Investigator Types

The system supports two types of investigators that can be assigned to a case:

### Internal Investigators

-   **Source**: Branch admins who are **not involved** in the case
-   **Selection Criteria**:
    -   Role: `branch_admin`
    -   Same branch (for branch-level assignment) or same company (for company-level)
    -   Status: `active`
    -   NOT the case submitter
    -   NOT named in the case (by email)
-   **Field**: `investigator_type = 'internal'`
-   **Source Tracking**: `internal_source` = 'branch_admin', 'company_admin', or 'super_admin'

### External Investigators

-   **Source**: Users with `investigator` role assigned to the company
-   **Selection Criteria**:
    -   Role: `investigator`
    -   Same company
    -   Status: `active`
-   **Field**: `investigator_type = 'external'`

### Lead Investigator

-   One investigator per case can be designated as the **lead investigator**
-   Field: `is_lead_investigator = true`
-   Assigning a new lead automatically removes lead status from previous lead

---

## Database Tables

| Table                   | Purpose                                             |
| ----------------------- | --------------------------------------------------- |
| `case_timeline_events`  | Stores all timeline events for cases                |
| `case_escalation_rules` | Defines escalation rules per stage                  |
| `case_escalations`      | Records triggered escalations                       |
| `case_assignments`      | Stores investigator assignments (internal/external) |

---

## Timeline Event Types

| Event Type              | Description                               |
| ----------------------- | ----------------------------------------- |
| `submitted`             | Case submitted by reporter                |
| `acknowledged`          | Case acknowledged by admin                |
| `assigned`              | Case assigned to investigator             |
| `reassigned`            | Case reassigned to different investigator |
| `investigation_started` | Investigation has begun                   |
| `investigation_updated` | Investigation progress updated            |
| `evidence_added`        | New evidence added to case                |
| `escalated`             | Case escalated due to overdue             |
| `priority_changed`      | Case priority changed                     |
| `status_changed`        | Case status changed                       |
| `resolved`              | Case resolved                             |
| `closed`                | Case closed                               |
| `reopened`              | Case reopened                             |
| `sla_warning`           | SLA warning triggered                     |
| `sla_breached`          | SLA breached                              |

---

## Case Stages

| Stage           | Description                    |
| --------------- | ------------------------------ |
| `submission`    | Initial submission             |
| `triage`        | Being reviewed/triaged         |
| `assignment`    | Being assigned to investigator |
| `investigation` | Under investigation            |
| `resolution`    | Being resolved                 |
| `closed`        | Case closed                    |

---

## API Endpoints

### Case Timeline & Tracking

| Method | Endpoint                                             | Description               |
| ------ | ---------------------------------------------------- | ------------------------- |
| GET    | `/api/admin/cases/{caseId}/timeline`                 | Get full case timeline    |
| GET    | `/api/admin/cases/{caseId}/duration`                 | Get duration summary      |
| GET    | `/api/admin/cases/{caseId}/escalations`              | Get case escalations      |
| POST   | `/api/admin/cases/{caseId}/timeline/log`             | Log manual timeline event |
| POST   | `/api/admin/cases/{caseId}/escalations/{id}/resolve` | Resolve an escalation     |

### Escalation Rules Management

| Method | Endpoint                                          | Description               |
| ------ | ------------------------------------------------- | ------------------------- |
| GET    | `/api/admin/escalation-rules`                     | List all rules            |
| POST   | `/api/admin/escalation-rules`                     | Create new rule           |
| GET    | `/api/admin/escalation-rules/{id}`                | Get rule details          |
| PUT    | `/api/admin/escalation-rules/{id}`                | Update rule               |
| DELETE | `/api/admin/escalation-rules/{id}`                | Delete rule               |
| GET    | `/api/admin/escalation-rules/company/{companyId}` | Get rules by company      |
| POST   | `/api/admin/escalation-rules/{id}/toggle`         | Toggle rule active status |

---

## API Examples

### 1. Get Case Timeline

```http
GET /api/admin/cases/{caseId}/timeline
Authorization: Bearer {token}
```

**Response:**

```json
{
    "status": "success",
    "data": {
        "case_id": "01ke2aw44wfp932221bks6yhtv",
        "case_token": "INC123456",
        "timeline": [
            {
                "id": "01ke2aw46...",
                "event_type": "submitted",
                "event_label": "Case Submitted",
                "stage": "submission",
                "stage_label": "Submission",
                "title": "Case Submitted",
                "description": "A new incident case has been submitted.",
                "event_at": "2026-01-04T10:00:00Z",
                "event_at_human": "2 hours ago",
                "actor": null,
                "actor_type": "reporter",
                "assigned_to": null,
                "duration_from_previous": "-",
                "duration_in_stage": "2h 30m",
                "total_duration": "2h 30m",
                "is_escalation": false,
                "sla_breached": false
            },
            {
                "id": "01ke2bx78...",
                "event_type": "assigned",
                "event_label": "Assigned to Investigator",
                "stage": "assignment",
                "title": "Case Assigned",
                "description": "Case assigned to John Doe",
                "event_at": "2026-01-04T11:00:00Z",
                "assigned_to": {
                    "id": "01k87vmj8...",
                    "name": "John Doe"
                },
                "duration_from_previous": "1h 0m",
                "total_duration": "1h 0m"
            }
        ]
    }
}
```

### 2. Get Duration Summary

```http
GET /api/admin/cases/{caseId}/duration
Authorization: Bearer {token}
```

**Response:**

```json
{
    "status": "success",
    "data": {
        "case_id": "01ke2aw44wfp932221bks6yhtv",
        "total_duration_minutes": 150,
        "total_duration_formatted": "2h 30m",
        "stage_durations": [
            {
                "stage": "submission",
                "stage_label": "Submission",
                "duration_minutes": 60,
                "duration_formatted": "1h 0m"
            },
            {
                "stage": "assignment",
                "stage_label": "Assignment",
                "duration_minutes": 30,
                "duration_formatted": "30 min"
            },
            {
                "stage": "investigation",
                "stage_label": "Investigation",
                "duration_minutes": 60,
                "duration_formatted": "1h 0m"
            }
        ],
        "current_stage": "investigation",
        "current_stage_duration_minutes": 60,
        "current_stage_duration_formatted": "1h 0m",
        "events_count": 5,
        "escalations_count": 0,
        "case_status": "in_progress"
    }
}
```

### 3. Create Escalation Rule

```http
POST /api/admin/escalation-rules
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Investigation Stage - 24 Hour Escalation",
  "description": "Escalate to company admin if investigation takes more than 24 hours",
  "company_id": "01k87vmj8r93sfkjpwbsszbpss",
  "stage": "investigation",
  "applies_to": "incident",
  "warning_threshold": 1200,
  "escalation_threshold": 1440,
  "critical_threshold": 2880,
  "escalation_level": "level_2",
  "use_business_hours": true,
  "exclude_weekends": true,
  "notify_current_assignee": true,
  "notify_branch_admin": true,
  "notify_company_admin": true,
  "auto_change_priority": true,
  "new_priority": "high",
  "conditions": {
    "priority": ["medium", "high"]
  }
}
```

**Threshold values are in minutes:**

-   `warning_threshold`: 1200 = 20 hours
-   `escalation_threshold`: 1440 = 24 hours
-   `critical_threshold`: 2880 = 48 hours

### 4. Log Manual Timeline Event

```http
POST /api/admin/cases/{caseId}/timeline/log
Authorization: Bearer {token}
Content-Type: application/json

{
  "event_type": "investigation_updated",
  "title": "Interview Conducted",
  "description": "Conducted interview with witness John Smith",
  "is_internal": false
}
```

### 5. Resolve Escalation

```http
POST /api/admin/cases/{caseId}/escalations/{escalationId}/resolve
Authorization: Bearer {token}
Content-Type: application/json

{
  "resolution_note": "Case has been addressed and investigation is progressing"
}
```

---

## Investigator Assignment API

### 1. Get Available Investigators

```http
GET /api/branch/cases/available-investigators?case_id={caseId}
Authorization: Bearer {token}
```

**Response:**

```json
{
    "success": true,
    "data": {
        "internal_investigators": [
            {
                "id": "01k87vmj8...",
                "name": "John Admin",
                "email": "john@company.com",
                "phone": "+1234567890",
                "branch_id": "01k...",
                "branch_name": "Main Branch",
                "active_cases": 3,
                "investigator_type": "internal"
            }
        ],
        "external_investigators": [
            {
                "id": "01k98xyz8...",
                "name": "External Investigator",
                "email": "investigator@firm.com",
                "phone": "+0987654321",
                "active_cases": 5,
                "investigator_type": "external"
            }
        ],
        "total_available": {
            "internal": 2,
            "external": 3
        }
    }
}
```

### 2. Assign Investigators

```http
POST /api/branch/cases/{caseId}/investigators
Authorization: Bearer {token}
Content-Type: application/json

{
  "investigators": [
    {
      "investigator_id": "01k87vmj8...",
      "investigator_type": "internal",
      "assignment_type": "primary",
      "is_lead": true,
      "priority_level": 1,
      "assignment_note": "Lead internal investigator"
    },
    {
      "investigator_id": "01k98xyz8...",
      "investigator_type": "external",
      "assignment_type": "secondary",
      "is_lead": false,
      "estimated_hours": 40,
      "deadline": "2026-01-15"
    }
  ]
}
```

**Request Fields:**

| Field               | Type    | Required | Description                                     |
| ------------------- | ------- | -------- | ----------------------------------------------- |
| `investigator_id`   | ulid    | Yes      | User ID of investigator                         |
| `investigator_type` | string  | Yes      | `internal` or `external`                        |
| `assignment_type`   | string  | No       | `primary`, `secondary`, `support`, `consultant` |
| `is_lead`           | boolean | No       | Designate as lead investigator (max 1)          |
| `priority_level`    | integer | No       | 1-3 (1=highest)                                 |
| `assignment_note`   | string  | No       | Max 500 characters                              |
| `estimated_hours`   | number  | No       | Estimated hours to complete                     |
| `deadline`          | date    | No       | Assignment deadline                             |

**Response:**

```json
{
  "success": true,
  "message": "Investigators assigned successfully",
  "data": {
    "assigned": [...assignments],
    "summary": {
      "total_assigned": 2,
      "internal_investigators": 1,
      "external_investigators": 1
    },
    "errors": []
  }
}
```

### 3. Get Case Investigators

```http
GET /api/branch/cases/{caseId}/investigators
Authorization: Bearer {token}
```

**Query Parameters:**

-   `status`: Filter by status (`active`, `removed`)
-   `investigator_type`: Filter by type (`internal`, `external`)

**Response:**

```json
{
  "success": true,
  "message": "Case investigators retrieved successfully",
  "data": {
    "all_assignments": [...],
    "internal_investigators": [...],
    "external_investigators": [...],
    "lead_investigator": {
      "id": "01k87vmj8...",
      "investigator_type": "internal",
      "is_lead_investigator": true,
      "investigator": {
        "id": "01k87vmj8...",
        "name": "John Admin",
        "email": "john@company.com"
      }
    },
    "summary": {
      "total": 2,
      "active": 2,
      "internal_count": 1,
      "external_count": 1,
      "has_lead": true
    }
  }
}
```

### 4. Unassign Investigator

```http
DELETE /api/branch/cases/{caseId}/investigators/{assignmentId}
Authorization: Bearer {token}
```

---

## Escalation Rules Configuration

| Field                     | Type   | Description                                                                     |
| ------------------------- | ------ | ------------------------------------------------------------------------------- |
| `name`                    | string | Rule name                                                                       |
| `stage`                   | string | Stage to apply rule (submission, triage, assignment, investigation, resolution) |
| `applies_to`              | string | Case type: `all`, `incident`, or `feedback`                                     |
| `warning_threshold`       | int    | Minutes before warning (optional)                                               |
| `escalation_threshold`    | int    | Minutes before escalation (required)                                            |
| `critical_threshold`      | int    | Minutes before critical (optional)                                              |
| `escalation_level`        | string | `level_1`, `level_2`, or `level_3`                                              |
| `use_business_hours`      | bool   | Only count business hours                                                       |
| `exclude_weekends`        | bool   | Exclude weekends from calculation                                               |
| `notify_current_assignee` | bool   | Notify current case assignee                                                    |
| `notify_branch_admin`     | bool   | Notify branch admins                                                            |
| `notify_company_admin`    | bool   | Notify company admins                                                           |
| `notify_super_admin`      | bool   | Notify super admins                                                             |
| `auto_reassign`           | bool   | Auto-reassign on escalation                                                     |
| `auto_reassign_to_id`     | ulid   | User to reassign to                                                             |
| `auto_change_priority`    | bool   | Auto-change priority                                                            |
| `new_priority`            | string | New priority value                                                              |
| `conditions`              | json   | Additional conditions                                                           |

### Escalation Levels

| Level     | Description         | Typical Recipients |
| --------- | ------------------- | ------------------ |
| `level_1` | First escalation    | Branch Admin       |
| `level_2` | Second escalation   | Company Admin      |
| `level_3` | Critical escalation | Super Admin        |

---

## Automated Escalation Check

### Run Manually

```bash
php artisan cases:check-escalations
```

### Schedule Automatically

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Check every 15 minutes
    $schedule->command('cases:check-escalations')->everyFifteenMinutes();

    // Or every hour
    $schedule->command('cases:check-escalations')->hourly();
}
```

### Command Options

```bash
# Run with verbose output
php artisan cases:check-escalations -v

# Dry run (show what would be escalated without actually escalating)
php artisan cases:check-escalations --dry-run
```

---

## Integration Points

Timeline events are automatically logged when:

1. **Case Submitted** - `CaseSubmissionController::submit()`
2. **Case Assigned** - When investigator is assigned
3. **Case Closed** - When case status changes to closed
4. **Case Escalated** - By scheduled escalation checker

### Manual Event Logging in Code

```php
use App\Services\CaseTrackingService;

$trackingService = app(CaseTrackingService::class);

// Log case submission
$trackingService->logCaseSubmitted($case);

// Log assignment
$trackingService->logCaseAssigned($case, $assignee, $assignedBy);

// Log investigation started
$trackingService->logInvestigationStarted($case, $investigator);

// Log status change
$trackingService->logStatusChange($case, 'open', 'in_progress', $changedBy, 'Investigation started');

// Log case closed
$trackingService->logCaseClosed($case, $closedBy, 'Issue resolved');

// Log case reopened
$trackingService->logCaseReopened($case, $reopenedBy, 'Additional evidence found');

// Log custom event
$trackingService->logEvent($case, 'evidence_added', 'investigation', [
    'actor_id' => $user->id,
    'title' => 'Evidence Uploaded',
    'description' => 'New document evidence uploaded',
    'metadata' => ['file_id' => $file->id],
]);
```

---

## Duration Calculation

Durations are calculated in minutes and formatted as:

-   Under 60 minutes: `45 min`
-   Under 24 hours: `2h 30m`
-   Over 24 hours: `3d 5h 30m`

### Stage Duration

Each stage tracks:

-   **Duration in stage**: Time spent in current stage
-   **Duration from previous**: Time since last event
-   **Total case duration**: Total time since case creation

---

## SLA Tracking

When escalation rules are configured:

-   `sla_deadline`: Calculated deadline based on threshold
-   `sla_remaining_minutes`: Minutes until SLA breach (negative if breached)
-   `sla_breached`: Boolean indicating if SLA was breached

---

## Email Notifications

Escalation notifications are sent via `App\Mail\CaseEscalationNotification` using the template at:
`resources/views/emails/case-escalation.blade.php`

The email includes:

-   Case reference number
-   Escalation level and reason
-   Overdue duration
-   Link to view case
-   Actions taken (if any)

---

## Models Reference

### CaseTimelineEvent

```php
// Relationships
$event->case;           // CaseModel
$event->company;        // Company
$event->branch;         // Branch
$event->actor;          // User (who triggered event)
$event->assignedTo;     // User (for assignment events)
$event->escalatedTo;    // User (for escalation events)

// Scopes
CaseTimelineEvent::forCase($caseId)->get();
CaseTimelineEvent::forCompany($companyId)->get();
CaseTimelineEvent::ofType('assigned')->get();
CaseTimelineEvent::inStage('investigation')->get();
CaseTimelineEvent::escalations()->get();
CaseTimelineEvent::slaBreached()->get();
CaseTimelineEvent::recent(30)->get(); // Last 30 days
```

### CaseEscalationRule

```php
// Relationships
$rule->company;
$rule->branch;
$rule->escalationToUser;
$rule->escalations;

// Scopes
CaseEscalationRule::active()->get();
CaseEscalationRule::forCompany($companyId)->get();
CaseEscalationRule::forStage('investigation')->get();
CaseEscalationRule::global()->get();

// Methods
$rule->appliesTo($case); // Check if rule applies to case
$rule->getFormattedEscalationThreshold(); // "24 hours"
```

### CaseEscalation

```php
// Relationships
$escalation->case;
$escalation->escalationRule;
$escalation->timelineEvent;
$escalation->resolvedBy;
$escalation->reassignedTo;

// Scopes
CaseEscalation::forCase($caseId)->get();
CaseEscalation::unresolved()->get();
CaseEscalation::resolved()->get();
CaseEscalation::ofLevel('level_2')->get();

// Methods
$escalation->resolve($userId, 'Resolution note');
$escalation->getFormattedOverdueDuration(); // "2h 30m overdue"
$escalation->getLevelLabel(); // "Level 2 - Company Admin"
```

---

## Best Practices

1. **Set Realistic Thresholds**: Consider your team's capacity when setting escalation thresholds
2. **Use Business Hours**: Enable business hours calculation for more accurate SLA tracking
3. **Layer Escalations**: Create rules for different stages with increasing severity
4. **Monitor Escalations**: Review escalation patterns to identify process bottlenecks
5. **Document Resolutions**: Always add resolution notes when resolving escalations

---

## Troubleshooting

### Escalations Not Triggering

1. Check if the escalation rule is active: `is_active = true`
2. Verify the rule applies to the case type and company
3. Ensure the scheduler is running: `php artisan schedule:work`
4. Check logs for errors: `storage/logs/laravel.log`

### Timeline Events Missing

1. Verify the CaseTrackingService is being called in controllers
2. Check for database transaction issues
3. Review error logs for exceptions

### Duration Calculations Incorrect

1. Ensure timeline events have correct `event_at` timestamps
2. Check if business hours calculation is enabled when expected
3. Verify stage transitions are being logged properly
