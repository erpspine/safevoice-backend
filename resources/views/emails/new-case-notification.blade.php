<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Case Submitted</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background-color: #007bff;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }

        .content {
            background-color: #f9f9f9;
            padding: 20px;
            border: 1px solid #ddd;
            border-top: none;
        }

        .info-row {
            margin: 10px 0;
            padding: 10px;
            background-color: white;
            border-left: 3px solid #007bff;
        }

        .label {
            font-weight: bold;
            color: #555;
        }

        .value {
            color: #333;
            margin-left: 10px;
        }

        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge-primary {
            background-color: #007bff;
            color: white;
        }

        .badge-warning {
            background-color: #ffc107;
            color: #333;
        }

        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }

        .footer {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #777;
            text-align: center;
        }

        .alert {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>New Case Submitted</h1>
    </div>

    <div class="content">
        <p>Hello {{ $recipient->name }},</p>

        <p>A new case has been submitted to your branch and requires your attention.</p>

        @if ($recipientType === 'alternative')
            <div class="alert">
                <strong>Note:</strong> You are receiving this notification as an alternative recipient because all
                primary recipients are involved in this case.
            </div>
        @endif

        <div class="info-row">
            <span class="label">Case Token:</span>
            <span class="value"><strong>{{ $case->case_token }}</strong></span>
        </div>

        <div class="info-row">
            <span class="label">Submission Date:</span>
            <span class="value">{{ $case->created_at->format('F j, Y, g:i a') }}</span>
        </div>

        <div class="info-row">
            <span class="label">Incident Date:</span>
            <span class="value">{{ \Carbon\Carbon::parse($case->incident_date)->format('F j, Y') }}</span>
        </div>

        <div class="info-row">
            <span class="label">Branch:</span>
            <span class="value">{{ $case->branch->name ?? 'N/A' }}</span>
        </div>

        <div class="info-row">
            <span class="label">Department:</span>
            <span class="value">{{ $case->department->name ?? 'N/A' }}</span>
        </div>

        <div class="info-row">
            <span class="label">Incident Location:</span>
            <span class="value">{{ $case->incident_location ?? 'Not specified' }}</span>
        </div>

        @if ($case->description)
            <div class="info-row">
                <span class="label">Description:</span>
                <div class="value" style="margin-top: 10px;">{{ Str::limit($case->description, 200) }}</div>
            </div>
        @endif

        <div style="text-align: center;">
            <a href="{{ $caseUrl }}" class="button">View Case Details</a>
        </div>

        <p style="margin-top: 20px; font-size: 14px; color: #666;">
            Please review this case and take appropriate action. You can access the full case details by clicking the
            button above.
        </p>
    </div>

    <div class="footer">
        <p>This is an automated notification from SafeVoice Case Management System.</p>
        <p>Please do not reply to this email.</p>
        <p>&copy; {{ date('Y') }} SafeVoice. All rights reserved.</p>
    </div>
</body>

</html>
