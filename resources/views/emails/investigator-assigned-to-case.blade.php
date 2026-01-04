<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Case Assignment</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }

        .container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 20px;
        }

        .header h1 {
            color: #2563eb;
            margin: 0;
            font-size: 24px;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 10px;
        }

        .badge-lead {
            background-color: #fef3c7;
            color: #d97706;
        }

        .badge-internal {
            background-color: #dbeafe;
            color: #2563eb;
        }

        .badge-external {
            background-color: #f3e8ff;
            color: #7c3aed;
        }

        .content {
            margin-bottom: 20px;
        }

        .case-info {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .case-info h3 {
            margin: 0 0 15px 0;
            color: #1e40af;
            font-size: 16px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #6b7280;
        }

        .info-value {
            color: #1f2937;
        }

        .priority-high {
            color: #dc2626;
            font-weight: 600;
        }

        .priority-critical {
            color: #dc2626;
            font-weight: 700;
            text-transform: uppercase;
        }

        .assignment-note {
            background-color: #fef9c3;
            border-left: 4px solid #eab308;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
        }

        .assignment-note h4 {
            margin: 0 0 8px 0;
            color: #854d0e;
        }

        .btn {
            display: inline-block;
            padding: 12px 30px;
            background-color: #2563eb;
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin-top: 20px;
        }

        .btn:hover {
            background-color: #1d4ed8;
        }

        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #6b7280;
            font-size: 12px;
        }

        .text-center {
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>📋 New Case Assignment</h1>
            @if ($isLead)
                <span class="badge badge-lead">👑 Lead Investigator</span>
            @endif
            <span class="badge {{ $investigatorType === 'Internal' ? 'badge-internal' : 'badge-external' }}">
                {{ $investigatorType }} Investigator
            </span>
        </div>

        <div class="content">
            <p>Dear <strong>{{ $investigatorName }}</strong>,</p>

            <p>You have been assigned to a new case by <strong>{{ $assignedByName }}</strong>. Please review the case
                details below and begin your investigation.</p>

            <div class="case-info">
                <h3>📁 Case Details</h3>
                <div class="info-row">
                    <span class="info-label">Case Number:</span>
                    <span class="info-value"><strong>{{ $caseNumber }}</strong></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Title:</span>
                    <span class="info-value">{{ $caseTitle }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Type:</span>
                    <span class="info-value">{{ $caseType }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Priority:</span>
                    <span
                        class="info-value {{ in_array(strtolower($casePriority), ['high', 'critical']) ? 'priority-' . strtolower($casePriority) : '' }}">
                        {{ $casePriority }}
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Company:</span>
                    <span class="info-value">{{ $companyName }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Branch:</span>
                    <span class="info-value">{{ $branchName }}</span>
                </div>
            </div>

            <div class="case-info">
                <h3>👤 Your Assignment</h3>
                <div class="info-row">
                    <span class="info-label">Assignment Type:</span>
                    <span class="info-value">{{ $assignmentType }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Role:</span>
                    <span class="info-value">{{ $isLead ? 'Lead Investigator' : 'Team Member' }}</span>
                </div>
                @if ($deadline)
                    <div class="info-row">
                        <span class="info-label">Deadline:</span>
                        <span class="info-value priority-high">{{ $deadline }}</span>
                    </div>
                @endif
                <div class="info-row">
                    <span class="info-label">Assigned At:</span>
                    <span class="info-value">{{ $assignedAt }}</span>
                </div>
            </div>

            @if ($assignmentNote)
                <div class="assignment-note">
                    <h4>📝 Assignment Notes</h4>
                    <p style="margin: 0;">{{ $assignmentNote }}</p>
                </div>
            @endif

            <div class="text-center">
                <a href="{{ $loginUrl }}" class="btn">View Case Details</a>
            </div>
        </div>

        <div class="footer">
            <p>This is an automated notification from SafeVoice.</p>
            <p>If you have any questions, please contact your administrator.</p>
            <p>&copy; {{ date('Y') }} SafeVoice. All rights reserved.</p>
        </div>
    </div>
</body>

</html>
