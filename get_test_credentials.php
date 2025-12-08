<?php

require_once __DIR__ . '/bootstrap/app.php';

$app = \Illuminate\Foundation\Application::getInstance();

// Set up the application
$app->bootstrapWith([
    \Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables::class,
    \Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
    \Illuminate\Foundation\Bootstrap\HandleExceptions::class,
    \Illuminate\Foundation\Bootstrap\RegisterFacades::class,
    \Illuminate\Foundation\Bootstrap\RegisterProviders::class,
    \Illuminate\Foundation\Bootstrap\BootProviders::class,
]);

// Now we can use models
use App\Models\CaseModel;

echo "Looking for existing case with access credentials...\n";

$case = CaseModel::whereNotNull('access_id')->first();

if ($case) {
    echo "Found case:\n";
    echo "Case ID: {$case->id}\n";
    echo "Case Token: {$case->case_token}\n";
    echo "Access ID: {$case->access_id}\n";
    echo "Has access password: " . ($case->access_password ? 'Yes' : 'No') . "\n";
} else {
    echo "No case found with access credentials. Creating one...\n";

    // Find any existing case and add credentials
    $case = CaseModel::first();

    if ($case) {
        $case->update([
            'access_id' => 'TEST-ACCESS-123',
            'access_password' => bcrypt('password123')
        ]);

        echo "Updated case:\n";
        echo "Case ID: {$case->id}\n";
        echo "Case Token: {$case->case_token}\n";
        echo "Access ID: {$case->access_id}\n";
        echo "Password: password123\n";
    } else {
        echo "No cases found in database at all.\n";
    }
}

echo "\nNow test with these credentials in Postman:\n";
echo "POST http://localhost/safevoicebackend/public/api/public/cases/login\n";
echo "Body: {\n";
echo "  \"access_id\": \"{$case->access_id}\",\n";
echo "  \"access_password\": \"password123\"\n";
echo "}\n";
