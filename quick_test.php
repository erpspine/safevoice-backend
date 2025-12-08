<?php

require_once 'vendor/autoload.php';

// Boot Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Quick SMS & Email Test ===\n\n";

// Test SMS with shorter timeout
echo "📱 Testing SMS (5 second timeout)...\n";
try {
    $client = new \GuzzleHttp\Client();
    $config = config('sms.drivers.messaging_service');

    $payload = [
        'from' => $config['from'],
        'to' => ['255760299974'],
        'text' => 'Quick test: ' . date('H:i:s'),
        'reference' => 'QUICK_' . time()
    ];

    $response = $client->post($config['endpoint'], [
        'headers' => [
            'Authorization' => 'Basic ' . $config['auth_header'],
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
        'json' => $payload,
        'timeout' => 5, // 5 second timeout
    ]);

    $responseData = json_decode($response->getBody()->getContents(), true);
    echo "✅ SMS Success! Status: " . ($responseData['messages'][0]['status']['name'] ?? 'Unknown') . "\n";
} catch (\GuzzleHttp\Exception\ConnectException $e) {
    echo "❌ SMS Connection Error: Network timeout or server unreachable\n";
} catch (\GuzzleHttp\Exception\RequestException $e) {
    echo "❌ SMS Request Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ SMS Error: " . $e->getMessage() . "\n";
}

// Test Email
echo "\n📧 Testing Email...\n";
try {
    \Illuminate\Support\Facades\Mail::raw(
        'Quick email test: ' . date('Y-m-d H:i:s'),
        function ($message) {
            $message->to('osurdancan@gmail.com')
                ->subject('Quick Test - ' . date('H:i:s'));
        }
    );
    echo "✅ Email Success! Check your inbox.\n";
} catch (Exception $e) {
    echo "❌ Email Error: " . $e->getMessage() . "\n";
}

// Check if you've received previous messages
echo "\n🔍 Recent SMS Message IDs from logs:\n";
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);

    // Extract recent SMS message IDs
    if (preg_match_all('/"messageId":(\d+)/', $logContent, $matches)) {
        $recentIds = array_slice(array_unique($matches[1]), -3);
        foreach ($recentIds as $id) {
            echo "  📱 Message ID: {$id}\n";
        }
        echo "\nIf you received SMS with these IDs, the system is working!\n";
    }
}

echo "\n💡 Solutions:\n";
echo "1. SMS Timeout Issue:\n";
echo "   - The SMS API server might be slow or overloaded\n";
echo "   - Previous messages were sent successfully (check your phone)\n";
echo "   - Consider using queue retry on network failures\n\n";

echo "2. Email Check:\n";
echo "   - Check spam/junk folder\n";
echo "   - Look for emails from: no-reply@safevoice.tz\n\n";

echo "3. To improve reliability:\n";
echo "   - Use queue with retries for SMS failures\n";
echo "   - Consider backup SMS provider\n";
echo "   - Monitor SMS API uptime\n";

echo "\n✅ Test completed!\n";
