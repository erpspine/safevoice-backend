<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Account Activated - SafeVoice</title>
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
            background: linear-gradient(135deg, #d1fae5 0%, #dbeafe 50%, #d1fae5 100%);
            padding: 40px 20px;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(16, 185, 129, 0.15);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            border: 1px solid #a7f3d0;
            border-radius: 8px;
            padding: 12px 20px;
            margin: 10px 0 25px;
        }

        .account-badge span {
            color: #059669;
            font-weight: 600;
            font-size: 14px;
        }

        .success-card {
            background: linear-gradient(135deg, #ecfdf5 0%, #f0fdf4 100%);
            border: 1px solid #bbf7d0;
            border-radius: 12px;
            padding: 25px;
            margin: 25px 0;
            text-align: center;
        }

        .success-icon {
            font-size: 50px;
            margin-bottom: 15px;
        }

        .success-title {
            color: #047857;
            font-weight: 700;
            font-size: 18px;
            margin-bottom: 10px;
        }

        .success-text {
            color: #065f46;
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
        }

        .features-box {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border: 1px solid #93c5fd;
            border-radius: 12px;
            padding: 25px;
            margin: 25px 0;
        }

        .features-title {
            color: #1e40af;
            font-weight: 700;
            font-size: 15px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }

        .features-list {
            margin: 0;
            padding-left: 0;
            list-style: none;
        }

        .features-list li {
            color: #1e3a8a;
            font-size: 14px;
            padding: 8px 0;
            padding-left: 28px;
            position: relative;
            line-height: 1.5;
        }

        .features-list li:before {
            content: '✓';
            position: absolute;
            left: 0;
            top: 8px;
            width: 18px;
            height: 18px;
            background-color: #10b981;
            border-radius: 50%;
            color: white;
            font-size: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .cta-section {
            text-align: center;
            margin: 35px 0;
        }

        .cta-btn {
            display: inline-block;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff !important;
            text-decoration: none;
            padding: 16px 40px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(16, 185, 129, 0.4);
        }

        .cta-btn:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-2px);
        }

        .cta-hint {
            color: #9ca3af;
            font-size: 13px;
            margin-top: 15px;
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

            .cta-btn {
                padding: 14px 30px;
                font-size: 15px;
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
                                <span style="font-size: 40px;">🎉</span>
                            </div>
                        </td>
                    </tr>
                </table>
                <h1>Account Activated!</h1>
                <p>Welcome back to SafeVoice</p>
            </div>

            <!-- Content -->
            <div class="content">
                <p class="greeting">Hello <strong>{{ $userName }}</strong>,</p>

                <p class="message-text">
                    Great news! Your SafeVoice account has been successfully activated by an administrator.
                    You now have full access to the platform and can resume your activities.
                </p>

                <div class="account-badge">
                    <span>📧 {{ $userEmail }}</span>
                </div>

                <div class="success-card">
                    <div class="success-icon">✅</div>
                    <div class="success-title">Your Account is Ready</div>
                    <p class="success-text">
                        All your previous data and settings have been preserved.<br>
                        You can pick up right where you left off!
                    </p>
                </div>

                <div class="features-box">
                    <div class="features-title">
                        <span style="font-size: 18px; margin-right: 8px;">🚀</span>
                        What You Can Do Now
                    </div>
                    <ul class="features-list">
                        <li>Log in to your account with your existing credentials</li>
                        <li>Access all your previous cases and reports</li>
                        <li>Continue managing your assignments</li>
                        <li>Receive notifications and updates</li>
                    </ul>
                </div>

                <div class="cta-section">
                    <a href="{{ $loginUrl }}" class="cta-btn">🔐 Log In Now</a>
                    <p class="cta-hint">Click the button above to access your account</p>
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
