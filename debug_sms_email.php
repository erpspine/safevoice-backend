<?php

require_once 'vendor/autoload.php';

// Boot Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\SmsService;
use App\Models\User;
use App\Mail\UserInvitation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

echo "=== SMS & Email Debugging Tool ===\n\n";

// Test SMS Service
echo "1. Testing SMS Service Direct...\n";
try {
    $smsService = new SmsService();

    echo "   SMS Config Status: " . (config('sms.enabled') ? 'Enabled' : 'Disabled') . "\n";
    echo "   SMS Driver: " . config('sms.default') . "\n";
    echo "   SMS From: " . config('sms.drivers.messaging_service.from') . "\n";

    // Test direct SMS
    $testPhone = '0760299974';
    $testMessage = 'Debug test SMS from SafeVoice at ' . date('Y-m-d H:i:s');

    echo "   Sending test SMS to {$testPhone}...\n";
    $smsResult = $smsService->sendSingle($testPhone, $testMessage, 'DEBUG_' . time());

    if ($smsResult['success']) {
        echo "   ✅ SMS sent successfully!\n";
        echo "   Reference: " . ($smsResult['data']['reference'] ?? 'N/A') . "\n";
        if (isset($smsResult['data']['api_response'])) {
            echo "   API Status: " . ($smsResult['data']['api_response']['status'] ?? 'Unknown') . "\n";
        }
    } else {
        echo "   ❌ SMS failed: " . $smsResult['message'] . "\n";
    }
} catch (Exception $e) {
    echo "   ❌ SMS Exception: " . $e->getMessage() . "\n";
}

echo "\n2. Testing Email Service Direct...\n";
try {
    // Get a test user
    $testUser = User::first();
    if (!$testUser) {
        echo "   ❌ No users found in database\n";
    } else {
        echo "   Test User: {$testUser->name} ({$testUser->email})\n";
        echo "   Mail Driver: " . config('mail.default') . "\n";
        echo "   Mail Host: " . config('mail.mailers.smtp.host') . "\n";
        echo "   Mail From: " . config('mail.from.address') . "\n";

        // Test direct email
        echo "   Sending test email...\n";

        // Create a simple test email
        Mail::raw('This is a test email from SafeVoice debug tool at ' . date('Y-m-d H:i:s'), function ($message) use ($testUser) {
            $message->to($testUser->email)
                ->subject('SafeVoice Debug Test Email');
        });

        echo "   ✅ Email sent successfully (if no exception thrown)!\n";
    }
} catch (Exception $e) {
    echo "   ❌ Email Exception: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n3. Testing Job System...\n";
try {
    // Check if jobs table exists and has data
    $queueJobs = \DB::table('jobs')->count();
    $failedJobs = \DB::table('failed_jobs')->count();

    echo "   Pending Jobs: {$queueJobs}\n";
    echo "   Failed Jobs: {$failedJobs}\n";

    // Test if we can create a simple job
    if ($queueJobs > 0) {
        echo "   Queue appears to be working (has pending jobs)\n";
    } else {
        echo "   No pending jobs in queue\n";
    }
} catch (Exception $e) {
    echo "   ❌ Queue Exception: " . $e->getMessage() . "\n";
}

echo "\n4. Testing Job Processing (if available)...\n";
try {
    if (\DB::table('jobs')->count() > 0) {
        echo "   Found " . \DB::table('jobs')->count() . " pending job(s)\n";
        echo "   Job Details:\n";

        $jobs = \DB::table('jobs')->limit(3)->get();
        foreach ($jobs as $job) {
            $payload = json_decode($job->payload, true);
            $jobClass = $payload['displayName'] ?? 'Unknown';
            echo "     - {$jobClass} (ID: {$job->id})\n";
        }

        echo "\n   To process jobs manually, run:\n";
        echo "     php artisan queue:work --once\n";
        echo "     php artisan queue:work --stop-when-empty\n";
    }
} catch (Exception $e) {
    echo "   ❌ Job Processing Exception: " . $e->getMessage() . "\n";
}

echo "\n5. Environment Check...\n";
try {
    echo "   APP_ENV: " . config('app.env') . "\n";
    echo "   APP_DEBUG: " . (config('app.debug') ? 'true' : 'false') . "\n";
    echo "   LOG_CHANNEL: " . config('logging.default') . "\n";
    echo "   QUEUE_CONNECTION: " . config('queue.default') . "\n";
} catch (Exception $e) {
    echo "   ❌ Environment Exception: " . $e->getMessage() . "\n";
}

echo "\n6. Recent Log Entries...\n";
try {
    $logFile = storage_path('logs/laravel.log');
    if (file_exists($logFile)) {
        $lines = file($logFile);
        $recentLines = array_slice($lines, -10);

        echo "   Last 10 log entries:\n";
        foreach ($recentLines as $line) {
            if (stripos($line, 'SMS') !== false || stripos($line, 'mail') !== false || stripos($line, 'invitation') !== false) {
                echo "     " . trim($line) . "\n";
            }
        }
    } else {
        echo "   Log file not found\n";
    }
} catch (Exception $e) {
    echo "   ❌ Log Exception: " . $e->getMessage() . "\n";
}

echo "\n=== Debug Complete ===\n";
echo "\nRecommendations:\n";
echo "1. Check your email provider (Gmail) app password is correct\n";
echo "2. Verify SMS API credentials are working\n";
echo "3. Run 'php artisan queue:work' to process pending jobs\n";
echo "4. Check storage/logs/laravel.log for detailed errors\n";
