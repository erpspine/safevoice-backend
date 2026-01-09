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
            padding: 30px 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }

        .content {
            background-color: #ffffff;
            padding: 30px;
            border: 1px solid #e5e7eb;
            border-top: none;
        }

        .greeting {
            font-size: 18px;
            color: #1f2937;
            margin-bottom: 20px;
        }

        .message {
            color: #4b5563;
            margin-bottom: 15px;
        }

        .cta-box {
            background-color: #eff6ff;
            border-left: 4px solid #2563eb;
            padding: 20px;
            margin: 25px 0;
        }

        .contact-info {
            background-color: #f9fafb;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }

        .contact-item {
            margin: 10px 0;
        }

        .contact-label {
            font-weight: bold;
            color: #1f2937;
        }

        .footer {
            text-align: center;
            padding: 20px;
            color: #6b7280;
            font-size: 12px;
            border-top: 1px solid #e5e7eb;
        }

        .signature {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1 style="margin: 0;">✅ Thank You!</h1>
        <p style="margin: 10px 0 0 0;">We've received your inquiry</p>
    </div>

    <div class="content">
        <div class="greeting">
            Dear {{ $name }},
        </div>

        <div class="message">
            Thank you for your interest in <strong>SafeVoice</strong>! We appreciate you taking the time to reach out to
            us regarding your organization, <strong>{{ $company }}</strong>.
        </div>

        <div class="cta-box">
            <p style="margin: 0 0 10px 0; font-weight: bold; color: #1e40af;">📩 What happens next?</p>
            <p style="margin: 0;">Our sales team has received your inquiry and will review your requirements carefully.
                You can expect to hear from us within <strong>24-48 hours</strong> via email or phone.</p>
        </div>

        <div class="message">
            In the meantime, if you have any urgent questions or would like to speak with someone immediately, please
            don't hesitate to contact us directly.
        </div>

        <div class="contact-info">
            <p style="margin: 0 0 15px 0; font-weight: bold; color: #1f2937;">📞 Contact Information:</p>
            <div class="contact-item">
                <span class="contact-label">Email:</span>
                <a href="mailto:sales@safevoice.tz">sales@safevoice.tz</a>
            </div>
            <div class="contact-item">
                <span class="contact-label">Phone:</span>
                <a href="tel:+255">+255 XXX XXX XXX</a>
            </div>
            <div class="contact-item">
                <span class="contact-label">Website:</span>
                <a href="https://safevoice.tz">www.safevoice.tz</a>
            </div>
        </div>

        <div class="message">
            We look forward to discussing how SafeVoice can help your organization create a safe, transparent, and
            accountable workplace environment.
        </div>

        <div class="signature">
            <p style="margin: 0;">Best regards,</p>
            <p style="margin: 5px 0; font-weight: bold; color: #2563eb;">The SafeVoice Sales Team</p>
            <p style="margin: 5px 0; color: #6b7280; font-size: 14px;">Empowering Organizations Through Anonymous
                Reporting</p>
        </div>
    </div>

    <div class="footer">
        <p>This is an automated confirmation email. Please do not reply to this message.</p>
        <p>If you did not submit this inquiry, please contact us at <a
                href="mailto:sales@safevoice.tz">sales@safevoice.tz</a></p>
        <p style="margin-top: 15px;">© {{ date('Y') }} SafeVoice. All rights reserved.</p>
    </div>
</body>

</html>
