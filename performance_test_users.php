<?php

require_once 'vendor/autoload.php';

// Boot Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Api\Admin\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

echo "=== SafeVoice User Creation Performance Test ===\n\n";

$controller = new UserController();
$results = [];

// Test different scenarios
$scenarios = [
    [
        'name' => 'User without SMS',
        'data' => [
            'name' => 'Test User No SMS',
            'email' => 'nosms' . time() . '@example.com',
            'role' => 'investigator',
            'company_id' => '01k7rjt9vjh4zdkv38nq4akwdj',
            'status' => 'pending',
            'sms_invitation' => false
        ]
    ],
    [
        'name' => 'User with SMS (queued)',
        'data' => [
            'name' => 'Test User With SMS',
            'email' => 'withsms' . time() . '@example.com',
            'phone_number' => '0760299974',
            'role' => 'investigator',
            'company_id' => '01k7rjt9vjh4zdkv38nq4akwdj',
            'status' => 'pending',
            'sms_invitation' => true
        ]
    ],
    [
        'name' => 'Admin User',
        'data' => [
            'name' => 'Test Admin User',
            'email' => 'admin' . time() . '@example.com',
            'phone_number' => '0760299974',
            'role' => 'admin',
            'status' => 'pending',
            'sms_invitation' => true
        ]
    ]
];

foreach ($scenarios as $scenario) {
    echo "Testing: {$scenario['name']}\n";

    $startTime = microtime(true);
    $request = new Request($scenario['data']);

    try {
        $response = $controller->store($request);
        $responseData = $response->getData(true);

        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);

        if ($responseData['success']) {
            echo "   ✅ Success in {$duration} ms\n";
            echo "   Email Queued: " . ($responseData['data']['email_invitation_queued'] ? 'Yes' : 'No') . "\n";
            echo "   SMS Queued: " . ($responseData['data']['sms_invitation_queued'] ? 'Yes' : 'No') . "\n";

            // Clean up
            $userId = $responseData['data']['user']['id'];
            DB::table('users')->where('id', $userId)->delete();

            $results[] = [
                'scenario' => $scenario['name'],
                'duration' => $duration,
                'success' => true
            ];
        } else {
            echo "   ❌ Failed: {$responseData['message']}\n";
            $results[] = [
                'scenario' => $scenario['name'],
                'duration' => $duration,
                'success' => false
            ];
        }
    } catch (Exception $e) {
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);
        echo "   ❌ Exception: {$e->getMessage()}\n";
        $results[] = [
            'scenario' => $scenario['name'],
            'duration' => $duration,
            'success' => false
        ];
    }

    echo "\n";
}

// Performance Summary
echo "=== Performance Summary ===\n";
$successfulResults = array_filter($results, fn($r) => $r['success']);
if (!empty($successfulResults)) {
    $avgDuration = array_sum(array_column($successfulResults, 'duration')) / count($successfulResults);
    $minDuration = min(array_column($successfulResults, 'duration'));
    $maxDuration = max(array_column($successfulResults, 'duration'));

    echo "Average Duration: " . round($avgDuration, 2) . " ms\n";
    echo "Fastest: " . round($minDuration, 2) . " ms\n";
    echo "Slowest: " . round($maxDuration, 2) . " ms\n";

    if ($avgDuration < 500) {
        echo "🚀 Excellent performance!\n";
    } elseif ($avgDuration < 1000) {
        echo "✅ Good performance\n";
    } else {
        echo "⚠️ Could be improved\n";
    }
}

// Check queue status
echo "\nQueue Status:\n";
$queueJobs = DB::table('jobs')->count();
$failedJobs = DB::table('failed_jobs')->count();
echo "Pending Jobs: {$queueJobs}\n";
echo "Failed Jobs: {$failedJobs}\n";

if ($queueJobs > 0) {
    echo "\n📋 To process queued jobs:\n";
    echo "   php artisan queue:work --once   (process one job)\n";
    echo "   php artisan queue:work --stop-when-empty   (process all jobs)\n";
    echo "   php artisan queue:listen   (run continuously)\n";
}

echo "\n✅ Performance test completed!\n";
