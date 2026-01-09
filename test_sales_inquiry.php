<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Test data
$testData = [
    'name' => 'John Doe',
    'email' => 'john.doe@example.com',
    'company' => 'Example Corp',
    'phone' => '+255712345678',
    'employees' => '51-200',
    'message' => "I'm interested in implementing SafeVoice for our organization. Please provide more information about your services."
];

echo "Testing Sales Inquiry API Endpoint\n";
echo "===================================\n\n";

echo "Test Data:\n";
echo json_encode($testData, JSON_PRETTY_PRINT) . "\n\n";

// Create a test request
$request = \Illuminate\Http\Request::create(
    '/api/public/sales-inquiry',
    'POST',
    $testData
);

try {
    $controller = new \App\Http\Controllers\Api\SalesInquiryController();
    $response = $controller->submit($request);

    echo "Response Status: " . $response->getStatusCode() . "\n";
    echo "Response Body:\n";
    echo json_encode(json_decode($response->getContent()), JSON_PRETTY_PRINT) . "\n\n";

    if ($response->getStatusCode() === 200) {
        echo "✅ SUCCESS: Sales inquiry email should be sent to sales@safevoice.tz\n";
        echo "✅ Confirmation email should be sent to {$testData['email']}\n";
    } else {
        echo "❌ FAILED: Something went wrong\n";
    }
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n\nAPI Endpoint Details:\n";
echo "=====================\n";
echo "URL: POST /api/public/sales-inquiry\n";
echo "Authentication: None (Public endpoint)\n";
echo "Content-Type: application/json\n\n";

echo "cURL Example:\n";
echo "curl -X POST http://yourdomain.com/api/public/sales-inquiry \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '" . json_encode($testData) . "'\n";
