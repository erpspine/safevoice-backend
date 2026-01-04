<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Company Assignment</title>
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
        }

        .header h1 {
            color: #2563eb;
            margin: 0;
            font-size: 24px;
        }

        .content {
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        th,
        td {
            border: 1px solid #e5e7eb;
            padding: 12px;
            text-align: left;
        }

        th {
            background-color: #f3f4f6;
            font-weight: 600;
            color: #374151;
        }

        tr:nth-child(even) {
            background-color: #f9fafb;
        }

        .btn {
            display: inline-block;
            background-color: #2563eb;
            color: #ffffff;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            margin: 20px 0;
        }

        .btn:hover {
            background-color: #1d4ed8;
        }

        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            color: #666;
            font-size: 14px;
        }

        .note {
            background-color: #f0f9ff;
            border-left: 4px solid #2563eb;
            padding: 15px;
            margin: 20px 0;
            font-size: 14px;
            color: #1e40af;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>New Company Assignment</h1>
        </div>

        <div class="content">
            <p>Hello {{ $investigatorName ?? 'Investigator' }},</p>

            <p>You have been assigned to the following {{ count($companies) > 1 ? 'companies' : 'company' }} as an
                investigator:</p>

            <table>
                <thead>
                    <tr>
                        <th>Company Name</th>
                        <th>Email</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($companies as $company)
                        <tr>
                            <td>{{ $company->name }}</td>
                            <td>{{ $company->email ?? 'N/A' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="note">
                You can now access cases and reports from
                {{ count($companies) > 1 ? 'these companies' : 'this company' }} through your dashboard.
            </div>

            <p style="text-align: center;">
                <a href="{{ $loginUrl }}" class="btn">Go to Dashboard</a>
            </p>

            <p>If you have any questions about your new assignments, please contact your administrator.</p>
        </div>

        <div class="footer">
            <p>Thanks,<br>{{ config('app.name') }}</p>
            <p style="font-size: 12px; color: #999;">
                If you're having trouble clicking the button, copy and paste this URL into your browser:<br>
                <a href="{{ $loginUrl }}" style="color: #2563eb;">{{ $loginUrl }}</a>
            </p>
        </div>
    </div>
</body>

</html>
