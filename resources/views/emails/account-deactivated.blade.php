<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Account Deactivated - SafeVoice</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        /* Reset styles */
        body,
        table,
        td,
        p,
        a,
        li,
        blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }

        table,
        td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }

        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f8fafc;
            width: 100% !important;
            height: 100% !important;
        }

        .email-wrapper {
            width: 100%;
            background: linear-gradient(135deg, #fee2e2 0%, #fef3c7 50%, #fee2e2 100%);
            padding: 40px 20px;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(220, 53, 69, 0.15);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            padding: 40px 30px;
            text-align: center;
        }

        .header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .header p {
            color: rgba(255, 255, 255, 0.9);
            margin: 10px 0 0;
            font-size: 16px;
        }

        .content {
            padding: 40px 35px;
        }

        .greeting {
            font-size: 18px;
            color: #1f2937;
            margin-bottom: 20px;
        }

        .message-text {
            font-size: 15px;
            color: #4b5563;
            line-height: 1.7;
            margin-bottom: 25px;
        }

        .account-badge {
            display: inline-block;
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 12px 20px;
            margin: 10px 0 25px;
        }

        .account-badge span {
            color: #dc2626;
            font-weight: 600;
            font-size: 14px;
        }

        .reason-card {
            background: linear-gradient(135deg, #fef2f2 0%, #fff7ed 100%);
            border-left: 4px solid #dc2626;
            border-radius: 0 12px 12px 0;
            padding: 20px 25px;
            margin: 25px 0;
        }

        .reason-card-title {
            color: #dc2626;
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .reason-card-text {
            color: #7f1d1d;
            font-size: 15px;
            line-height: 1.6;
            margin: 0;
        }

        .info-box {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            border: 1px solid #fcd34d;
            border-radius: 12px;
            padding: 25px;
            margin: 25px 0;
        }

        .info-box-title {
            color: #92400e;
            font-weight: 700;
            font-size: 15px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }

        .info-list {
            margin: 0;
            padding-left: 0;
            list-style: none;
        }

        .info-list li {
            color: #78350f;
            font-size: 14px;
            padding: 8px 0;
            padding-left: 28px;
            position: relative;
            line-height: 1.5;
        }

        .info-list li:before {
            content: '';
            position: absolute;
            left: 0;
            top: 12px;
            width: 8px;
            height: 8px;
            background-color: #f59e0b;
            border-radius: 50%;
        }

        .support-section {
            background-color: #f8fafc;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            margin-top: 30px;
        }

        .support-section p {
            color: #64748b;
            font-size: 14px;
            margin: 0 0 15px;
        }

        .support-btn {
            display: inline-block;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
        }

        .support-btn:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        }

        .footer {
            background-color: #1f2937;
            padding: 35px;
            text-align: center;
        }

        .footer-logo {
            color: #ffffff;
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .footer p {
            color: #9ca3af;
            font-size: 13px;
            margin: 5px 0;
            line-height: 1.6;
        }

        .footer-divider {
            height: 1px;
            background-color: #374151;
            margin: 20px 0;
        }

        @media only screen and (max-width: 600px) {
            .email-wrapper {
                padding: 20px 10px;
            }

            .content {
                padding: 30px 20px;
            }

            .header {
                padding: 30px 20px;
            }

            .header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>

<body>
    <div class="email-wrapper">
        <div class="email-container">
            <!-- Header -->
            <div class="header">
                <table width="100%" border="0" cellspacing="0" cellpadding="0">
                    <tr>
                        <td align="center">
                            <div
                                style="width: 80px; height: 80px; background-color: rgba(255,255,255,0.2); border-radius: 50%; margin: 0 auto 20px; line-height: 80px;">
                                <span style="font-size: 40px;">🔒</span>
                            </div>
                        </td>
                    </tr>
                </table>
                <h1>Account Deactivated</h1>
                <p>Your access has been temporarily suspended</p>
            </div>

            <!-- Content -->
            <div class="content">
                <p class="greeting">Hello <strong>{{ $userName }}</strong>,</p>

                <p class="message-text">
                    We're writing to inform you that your SafeVoice account has been deactivated.
                    This action was taken by an administrator and affects your ability to access the platform.
                </p>

                <div class="account-badge">
                    <span>📧 {{ $userEmail }}</span>
                </div>

                @if ($reason)
                    <div class="reason-card">
                        <div class="reason-card-title">⚠️ Reason for Deactivation</div>
                        <p class="reason-card-text">{{ $reason }}</p>
                    </div>
                @endif

                <div class="info-box">
                    <div class="info-box-title">
                        <span style="font-size: 18px; margin-right: 8px;">📋</span>
                        What This Means For You
                    </div>
                    <ul class="info-list">
                        <li>You will no longer be able to log in to your account</li>
                        <li>All active sessions have been terminated immediately</li>
                        <li>Your data remains secure and protected in our system</li>
                        <li>Any pending tasks or assignments have been paused</li>
                    </ul>
                </div>

                <div class="support-section">
                    <p>If you believe this was done in error or need assistance,<br>our support team is here to help.
                    </p>
                    <a href="mailto:{{ $supportEmail }}" class="support-btn">📧 Contact Support</a>
                </div>
            </div>

            <!-- Footer -->
            <div class="footer">
                <div class="footer-logo">🛡️ SafeVoice</div>
                <p>Empowering organizations with secure whistleblowing solutions</p>
                <div class="footer-divider"></div>
                <p>This is an automated message. Please do not reply directly to this email.</p>
                <p>© {{ date('Y') }} SafeVoice. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>

</html>
