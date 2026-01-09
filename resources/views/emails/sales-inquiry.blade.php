<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            background-color: #2563eb;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }

        .content {
            background-color: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
        }

        .field {
            margin-bottom: 20px;
        }

        .label {
            font-weight: bold;
            color: #1f2937;
            display: block;
            margin-bottom: 5px;
        }

        .value {
            color: #4b5563;
            padding: 10px;
            background-color: white;
            border-left: 3px solid #2563eb;
        }

        .message-box {
            background-color: white;
            padding: 15px;
            border-left: 3px solid #2563eb;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .footer {
            text-align: center;
            padding: 20px;
            color: #6b7280;
            font-size: 12px;
        }

        .priority {
            background-color: #fef3c7;
            border: 1px solid #fbbf24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
            color: #92400e;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>🎯 New Sales Inquiry</h1>
        <p style="margin: 5px 0;">SafeVoice Website Contact Form</p>
    </div>

    <div class="content">
        <div class="priority">
            ⚡ New Lead - Please respond within 24 hours
        </div>

        <div class="field">
            <span class="label">📅 Submitted At:</span>
            <div class="value">{{ $submitted_at }}</div>
        </div>

        <div class="field">
            <span class="label">👤 Contact Name:</span>
            <div class="value">{{ $name }}</div>
        </div>

        <div class="field">
            <span class="label">📧 Email Address:</span>
            <div class="value">
                <a href="mailto:{{ $email }}">{{ $email }}</a>
            </div>
        </div>

        <div class="field">
            <span class="label">🏢 Company Name:</span>
            <div class="value">{{ $company }}</div>
        </div>

        <div class="field">
            <span class="label">📱 Phone Number:</span>
            <div class="value">
                <a href="tel:{{ $phone }}">{{ $phone }}</a>
            </div>
        </div>

        <div class="field">
            <span class="label">👥 Number of Employees:</span>
            <div class="value">{{ $employees }}</div>
        </div>

        <div class="field">
            <span class="label">💬 Message:</span>
            <div class="message-box">{{ $inquiryMessage }}</div>
        </div>

        <div style="margin-top: 30px; padding: 15px; background-color: #e0f2fe; border-radius: 5px;">
            <p style="margin: 0; font-weight: bold; color: #0369a1;">Quick Actions:</p>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li>Reply directly to this email to contact {{ $name }}</li>
                <li>Call {{ $phone }} for immediate follow-up</li>
                <li>Log this lead in your CRM system</li>
            </ul>
        </div>
    </div>

    <div class="footer">
        <p>This email was sent from the SafeVoice website contact form.</p>
        <p>SafeVoice - Empowering Organizations Through Anonymous Reporting</p>
        <p>© {{ date('Y') }} SafeVoice. All rights reserved.</p>
    </div>
</body>

</html>
