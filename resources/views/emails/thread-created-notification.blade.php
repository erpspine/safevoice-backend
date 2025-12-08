<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New Discussion Thread Created</title>
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
            background-color: #2c3e50;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }

        .content {
            background-color: #f8f9fa;
            padding: 30px;
            border: 1px solid #ddd;
        }

        .footer {
            background-color: #ecf0f1;
            padding: 15px;
            text-align: center;
            border-radius: 0 0 5px 5px;
            font-size: 12px;
            color: #666;
        }

        .button {
            display: inline-block;
            background-color: #3498db;
            color: white !important;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 5px;
            margin: 15px 0;
        }

        .case-info {
            background-color: #fff;
            border: 1px solid #ddd;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }

        .thread-info {
            background-color: #e8f4f8;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin: 15px 0;
        }

        .highlight {
            color: #2c3e50;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>🧵 New Discussion Thread Created</h1>
        <p>A new discussion thread has been created for case {{ $case->case_token }}</p>
    </div>

    <div class="content">
        <p>Hello {{ $recipient->name }},</p>

        <p>A new discussion thread has been created by {{ $creator->name }} for case <span
                class="highlight">{{ $case->case_token }}</span>.</p>

        <div class="case-info">
            <h3>📋 Case Information</h3>
            <p><strong>Case Number:</strong> {{ $case->case_token }}</p>
            <p><strong>Company:</strong> {{ $case->company->name ?? 'N/A' }}</p>
            @if ($case->branch)
                <p><strong>Branch:</strong> {{ $case->branch->name }}</p>
            @endif
            <p><strong>Status:</strong> <span class="highlight">{{ ucfirst($case->status) }}</span></p>
            <p><strong>Description:</strong> {{ Str::limit($case->description, 150) }}</p>
        </div>

        <div class="thread-info">
            <h3>💬 Thread Details</h3>
            <p><strong>Thread Title:</strong> {{ $thread->title }}</p>
            @if ($descriptionPreview)
                <p><strong>Description:</strong> {{ $descriptionPreview }}</p>
            @endif
            <p><strong>Created by:</strong> {{ $creator->name }}
                ({{ ucfirst(str_replace('_', ' ', $creator->role)) }})</p>
            <p><strong>Participants:</strong> {{ $participantsCount }} members</p>
            <p><strong>Created:</strong> {{ $thread->created_at->format('M j, Y \a\t g:i A') }}</p>
        </div>

        <p>You have been added as a participant in this discussion thread. You can now:</p>
        <ul>
            <li>View and respond to messages in the thread</li>
            <li>Share files and attachments</li>
            <li>Collaborate with other team members</li>
            <li>Track the progress of case discussions</li>
        </ul>

        <p>Please log in to your SafeVoice dashboard to participate in the discussion.</p>

        <p style="margin-top: 30px;">
            <strong>Important:</strong> This is an automated notification. Please do not reply to this email.
            Log in to the SafeVoice platform to participate in the discussion.
        </p>
    </div>

    <div class="footer">
        <p>This email was sent by SafeVoice Case Management System</p>
        <p>If you have any questions, please contact your system administrator.</p>
        <p>&copy; {{ date('Y') }} SafeVoice. All rights reserved.</p>
    </div>
</body>

</html>
