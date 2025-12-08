<?php

require_once 'vendor/autoload.php';

// Boot Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\SmsService;
use Illuminate\Support\Facades\Mail;

echo "=== Real-time Message Test ===\n\n";

$yourPhone = '0760299974'; // Replace with your actual phone number
$yourEmail = 'admin@safevoice.tz'; // Replace with your actual email

echo "📱 Sending SMS to {$yourPhone}...\n";
try {
    $smsService = new SmsService();
    $message = "SAFEVOICE TEST: If you receive this SMS, the system is working! Time: " . date('H:i:s');

    $result = $smsService->sendSingle($yourPhone, $message, 'REALTEST_' . time());

    if ($result['success']) {
        echo "✅ SMS API Response: SUCCESS\n";
        echo "📋 Reference: " . $result['data']['reference'] . "\n";
        echo "🔍 Status: " . ($result['data']['api_response']['status'] ?? 'Unknown') . "\n";
        echo "📱 Check your phone now for the message!\n";
    } else {
        echo "❌ SMS Failed: " . $result['message'] . "\n";
    }
} catch (Exception $e) {
    echo "❌ SMS Error: " . $e->getMessage() . "\n";
}

echo "\n📧 Sending Email to {$yourEmail}...\n";
try {
    $subject = "SafeVoice Test Email - " . date('Y-m-d H:i:s');
    $content = "
    <h2>SafeVoice Email Test</h2>
    <p>If you receive this email, the SafeVoice email system is working correctly!</p>
    <p>Test Time: " . date('Y-m-d H:i:s') . "</p>
    <p>This email was sent as part of debugging the invitation system.</p>
    
    <hr>
    <small>SafeVoice Debug Tool</small>
    ";

    Mail::send([], [], function ($message) use ($yourEmail, $subject, $content) {
        $message->to($yourEmail)
            ->subject($subject)
            ->html($content);
    });

    echo "✅ Email sent successfully!\n";
    echo "📧 Check your email inbox (and spam folder) now!\n";
    echo "📧 Subject: {$subject}\n";
} catch (Exception $e) {
    echo "❌ Email Error: " . $e->getMessage() . "\n";
    echo "📄 Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n🔍 Checking Previous Job Executions...\n";

// Check recent logs for actual delivery confirmations
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);

    // Look for recent SMS logs
    if (preg_match_all('/\[([0-9-:\s]+)\].*SMS sent successfully.*messageId":(\d+)/', $logContent, $smsMatches)) {
        echo "📱 Recent SMS Deliveries:\n";
        $recentSms = array_slice($smsMatches[0], -3); // Last 3
        foreach ($recentSms as $match) {
            echo "   " . trim($match) . "\n";
        }
    } else {
        echo "📱 No recent SMS delivery logs found\n";
    }

    // Look for email logs
    if (preg_match_all('/\[([0-9-:\s]+)\].*Invitation email sent successfully/', $logContent, $emailMatches)) {
        echo "📧 Recent Email Deliveries:\n";
        $recentEmails = array_slice($emailMatches[0], -3); // Last 3
        foreach ($recentEmails as $match) {
            echo "   " . trim($match) . "\n";
        }
    } else {
        echo "📧 No recent email delivery logs found\n";
    }
}

echo "\n🔧 Troubleshooting Tips:\n";
echo "1. SMS Issues:\n";
echo "   - Check if your phone number {$yourPhone} is correct\n";
echo "   - SMS status 'PENDING_ENROUTE' means it's being delivered\n";
echo "   - Delivery can take 1-5 minutes depending on network\n";
echo "   - Check with your mobile network provider if issues persist\n\n";

echo "2. Email Issues:\n";
echo "   - Check spam/junk folder in your email\n";
echo "   - Gmail sometimes blocks emails from new SMTP sources\n";
echo "   - Verify the Gmail app password is still valid\n";
echo "   - Check if Gmail 2FA is properly configured\n\n";

echo "3. If both are working in this test but not in jobs:\n";
echo "   - The jobs are processing correctly\n";
echo "   - The issue might be with the job data or user records\n";
echo "   - Check if the user phone numbers and emails are valid\n\n";

echo "⏱️  Wait 2-3 minutes and check your phone and email!\n";
