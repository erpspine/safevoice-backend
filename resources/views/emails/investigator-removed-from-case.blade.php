<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Case Assignment Removed</title>
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
            border-bottom: 2px solid #dc2626;
            padding-bottom: 20px;
        }

        .header h1 {
            color: #dc2626;
            margin: 0;
            font-size: 24px;
        }

        .content {
            margin-bottom: 20px;
        }

        .case-info {
            background-color: #fef2f2;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #fecaca;
        }

        .case-info h3 {
            margin: 0 0 15px 0;
            color: #991b1b;
            font-size: 16px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #fecaca;
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

        .reason-box {
            background-color: #f8fafc;
            border-left: 4px solid #6b7280;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
        }

        .reason-box h4 {
            margin: 0 0 8px 0;
            color: #374151;
        }

        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #6b7280;
            font-size: 12px;
        }

        .notice {
            background-color: #fef3c7;
            border: 1px solid #fcd34d;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
        }

        .notice p {
            margin: 0;
            color: #92400e;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>🔔 Case Assignment Removed</h1>
        </div>

        <div class="content">
            <p>Dear <strong>{{ $investigatorName }}</strong>,</p>

            <p>Your assignment to the following case has been removed by <strong>{{ $removedByName }}</strong>.</p>

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
                    <span class="info-label">Company:</span>
                    <span class="info-value">{{ $companyName }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Branch:</span>
                    <span class="info-value">{{ $branchName }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Removed At:</span>
                    <span class="info-value">{{ $removedAt }}</span>
                </div>
            </div>

            @if ($removalReason)
                <div class="reason-box">
                    <h4>📝 Reason for Removal</h4>
                    <p style="margin: 0;">{{ $removalReason }}</p>
                </div>
            @endif

            <div class="notice">
                <p>⚠️ You no longer have access to this case. If you believe this was done in error, please contact your
                    administrator.</p>
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
