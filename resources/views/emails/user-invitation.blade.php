<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to SafeVoice</title>
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

        .company-info {
            background-color: #e8f4f8;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #17a2b8;
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
            background-color: #28a745;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }

        .btn:hover {
            background-color: #218838;
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

        .welcome {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #28a745;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <div class="logo">SafeVoice</div>
            <h2>Welcome to Your Organization</h2>
        </div>

        <div class="welcome">
            <strong>Welcome aboard!</strong> Your SafeVoice account has been successfully created.
        </div>

        <p>Hello <strong>{{ $user->name }}</strong>,</p>

        <p>You have been added to the SafeVoice platform and granted access to your organization's incident reporting
            and management system.</p>

        @if ($company)
            <div class="company-info">
                <h3>Organization Details</h3>
                <p><strong>Company:</strong> {{ $company->name }}</p>
                @if ($company->description)
                    <p><strong>About:</strong> {{ $company->description }}</p>
                @endif
                @if ($company->website)
                    <p><strong>Website:</strong> <a href="{{ $company->website }}">{{ $company->website }}</a></p>
                @endif
            </div>
        @endif

        <div class="credentials">
            <h3>Your Account Details</h3>
            <p><strong>Email:</strong> {{ $user->email }}</p>
            <p><strong>Role:</strong> {{ ucfirst(str_replace('_', ' ', $user->role)) }}</p>
            @if ($user->department)
                <p><strong>Department:</strong> {{ $user->department->name }}</p>
            @endif
            @if ($user->branch)
                <p><strong>Branch:</strong> {{ $user->branch->name }}</p>
            @endif
        </div>

        <div class="warning">
            <strong>Important:</strong> Please click the button below to complete your registration and create your password. This invitation link will expire in 7 days.
        </div>

        <div style="text-align: center;">
            <a href="{{ $invitationUrl }}"
                style="display: inline-block; padding: 12px 24px; background-color: #28a745; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">
                Complete Registration & Create Password
            </a>
        </div>

        <p><strong>What you can do with SafeVoice:</strong></p>
        <ul>
            <li><strong>Report Incidents:</strong> Safely and anonymously report workplace issues</li>
            <li><strong>Track Cases:</strong> Monitor the progress of your reports</li>
            <li><strong>Receive Updates:</strong> Get notifications about case developments</li>
            @if (in_array($user->role, ['investigator', 'department_head', 'branch_manager']))
                <li><strong>Manage Cases:</strong> Review and investigate reported incidents</li>
                <li><strong>Generate Reports:</strong> Create analytics and compliance reports</li>
            @endif
        </ul>

        <p><strong>Getting Started:</strong></p>
        <ol>
            <li>Click the "Complete Registration & Create Password" button above</li>
            <li>Create your secure password</li>
            <li>You'll be automatically logged in</li>
            <li>Complete your profile setup</li>
            <li>Familiarize yourself with the platform features</li>
        </ol>

        <div class="warning">
            <strong>Security Notice:</strong> This invitation link expires on
            {{ $expiresAt->format('M d, Y \a\t g:i A') }}. Please complete your account setup before this date.
        </div>

        <div class="footer">
            <p><strong>Dashboard URL:</strong> <a href="{{ $dashboardUrl }}">{{ $dashboardUrl }}</a></p>

            <p>If you have any questions about using SafeVoice or need assistance with your account, please contact your
                system administrator or HR department.</p>

            <p><strong>Need Help?</strong> Our platform is designed to be intuitive, but if you need guidance, look for
                the help section once you're logged in.</p>

            <p><small>This is an automated message from SafeVoice. Please do not reply to this email.</small></p>

            <p><small>&copy; {{ date('Y') }} SafeVoice. All rights reserved.</small></p>
        </div>
    </div>
</body>

</html>
