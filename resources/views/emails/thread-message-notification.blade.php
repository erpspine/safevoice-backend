<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Message in Case {{ $case->case_token }}</title>
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
            background-color: #28a745;
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

        .case-info {
            background-color: white;
            padding: 15px;
            margin: 15px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .message-preview {
            background-color: #e3f2fd;
            padding: 15px;
            margin: 15px 0;
            border: 1px solid #bbdefb;
            border-radius: 5px;
            border-left: 4px solid #2196f3;
        }

        .info-row {
            margin: 10px 0;
        }

        .label {
            font-weight: bold;
            color: #555;
        }

        .value {
            color: #333;
        }

        .footer {
            background-color: #f1f1f1;
            padding: 15px;
            text-align: center;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 5px 5px;
            font-size: 12px;
            color: #666;
        }

        .button {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 0;
        }

        .urgent {
            color: #dc3545;
            font-weight: bold;
        }

        .high {
            color: #fd7e14;
            font-weight: bold;
        }

        .medium {
            color: #ffc107;
            font-weight: bold;
        }

        .low {
            color: #28a745;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>📬 New Message Posted</h1>
        <p>Case {{ $case->case_token }}</p>
    </div>

    <div class="content">
        <p>Hello {{ $recipient->name }},</p>

        <p>A new message has been posted in case <strong>{{ $case->case_token }}</strong> that requires your attention.
        </p>

        <div class="case-info">
            <h3>Case Information</h3>
            <div class="info-row">
                <span class="label">Case Number:</span>
                <span class="value">{{ $case->case_token }}</span>
            </div>

            @if ($case->title)
                <div class="info-row">
                    <span class="label">Title:</span>
                    <span class="value">{{ $case->title }}</span>
                </div>
            @endif

            <div class="info-row">
                <span class="label">Status:</span>
                <span class="value">{{ ucfirst($case->status) }}</span>
            </div>

            @if ($case->priority)
                <div class="info-row">
                    <span class="label">Priority:</span>
                    <span class="value {{ strtolower($case->priority) }}">{{ ucfirst($case->priority) }}</span>
                </div>
            @endif

            <div class="info-row">
                <span class="label">Company:</span>
                <span class="value">{{ $case->company->name ?? 'N/A' }}</span>
            </div>

            @if ($case->branch)
                <div class="info-row">
                    <span class="label">Branch:</span>
                    <span class="value">{{ $case->branch->name }}</span>
                </div>
            @endif

            <div class="info-row">
                <span class="label">Submitted:</span>
                <span class="value">{{ $case->created_at->format('M j, Y \a\t g:i A') }}</span>
            </div>
        </div>

        <div class="message-preview">
            <h4>💬 Message from {{ $senderType }}:</h4>
            <p><em>"{{ $messagePreview }}"</em></p>
            <p><small>Posted on: {{ $messageCreatedAt }}</small></p>
        </div>

        <p><strong>What you need to do:</strong></p>
        <ul>
            <li>Review the new message and any attachments</li>
            <li>Respond if action is required from your department</li>
            <li>Ensure proper follow-up with the case reporter</li>
            <li>Update case status if necessary</li>
        </ul>

        <p>
            <strong>Notification Type:</strong> You are receiving this as a {{ $recipientType }} recipient for cases in
            your branch.
        </p>

        @if ($case->is_anonymous)
            <p><em><strong>Note:</strong> This is an anonymous case submission. Handle with appropriate
                    confidentiality.</em></p>
        @endif

        <p>This is an automated notification. Please do not reply to this email.</p>

        <p>Thank you for your prompt attention to this matter.</p>

        <p>Best regards,<br>
            SafeVoice System</p>
    </div>

    <div class="footer">
        <p>
            This email was sent to {{ $recipient->email }} because you are registered as a {{ $recipientType }}
            recipient for
            {{ $case->branch->name ?? 'your branch' }}.<br>
            SafeVoice - Confidential Reporting System<br>
            &copy; {{ date('Y') }} SafeVoice. All rights reserved.
        </p>
    </div>
</body>

</html>
