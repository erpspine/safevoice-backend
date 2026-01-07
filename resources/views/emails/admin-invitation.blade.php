<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SafeVoice Admin Account</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }

        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            font-size: 28px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .credentials {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #007bff;
        }

        .btn {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }

        .btn:hover {
            background-color: #0056b3;
        }

        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 14px;
            color: #666;
        }

        .warning {
            background-color: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #ffc107;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <div class="logo">SafeVoice</div>
            <h2>Admin Account Created</h2>
        </div>

        <p>Hello <strong>{{ $user->name }}</strong>,</p>

        <p>Your SafeVoice administrator account has been successfully created. You now have access to the SafeVoice
            admin panel with <strong>{{ ucfirst(str_replace('_', ' ', $user->role)) }}</strong> privileges.</p>

        <div class="credentials">
            <h3>Your Account Details</h3>
            <p><strong>Email:</strong> {{ $user->email }}</p>
            <p><strong>Role:</strong> {{ ucfirst(str_replace('_', ' ', $user->role)) }}</p>
        </div>

        <div class="warning">
            <strong>Important:</strong> Please click the button below to complete your registration and create your password. This invitation link will expire in 7 days.
        </div>

        <div style="text-align: center;">
            <a href="{{ $invitationUrl }}"
                style="display: inline-block; padding: 12px 24px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">
                Complete Registration & Create Password
            </a>
        </div>

        <p><strong>Admin Panel Features:</strong></p>
        <ul>
            <li>Manage companies and organizations</li>
            <li>Oversee user accounts and permissions</li>
            <li>Monitor system activity and reports</li>
            <li>Configure system settings and policies</li>
            <li>Access administrative dashboards and analytics</li>
        </ul>

        <div class="warning">
            <strong>Security Notice:</strong> This invitation link expires on
            {{ $expiresAt->format('M d, Y \a\t g:i A') }}. Please complete your account setup before this date.
        </div>

        <div class="footer">
            <p><strong>Dashboard URL:</strong> <a href="{{ $dashboardUrl }}">{{ $dashboardUrl }}</a></p>

            <p>If you have any questions or need assistance, please contact the system administrator.</p>

            <p><small>This is an automated message from SafeVoice. Please do not reply to this email.</small></p>

            <p><small>&copy; {{ date('Y') }} SafeVoice. All rights reserved.</small></p>
        </div>
    </div>
</body>

</html>
