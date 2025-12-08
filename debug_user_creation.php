<?php

require_once 'vendor/autoload.php';

// Boot Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Company;

echo "=== Debugging User Creation Issue ===\n\n";

try {
    echo "1. Checking database connection...\n";
    $companiesCount = Company::count();
    echo "   ✅ Database connected. Companies count: {$companiesCount}\n\n";

    echo "2. Checking company existence...\n";
    $companyId = '01k7rjt9vjh4zdkv38nq4akwdj';
    $company = Company::find($companyId);

    if ($company) {
        echo "   ✅ Company found: {$company->name}\n";
        echo "   Status: " . ($company->status ? 'Active' : 'Inactive') . "\n\n";
    } else {
        echo "   ❌ Company not found with ID: {$companyId}\n";
        echo "   Available companies:\n";
        $companies = Company::select('id', 'name', 'status')->limit(5)->get();
        foreach ($companies as $comp) {
            echo "     - ID: {$comp->id}, Name: {$comp->name}, Status: " . ($comp->status ? 'Active' : 'Inactive') . "\n";
        }
        echo "\n";
    }

    echo "3. Testing simple user creation (without SMS)...\n";

    // Test with a simple user creation first
    $userData = [
        'name' => 'Simple Test User',
        'email' => 'simpletest' . time() . '@example.com',
        'role' => 'investigator',
        'company_id' => $company ? $company->id : null,
        'status' => 'pending',
        'password' => bcrypt('temp123'),
        'invitation_token' => \Illuminate\Support\Str::random(64),
        'invitation_expires_at' => now()->addDays(7),
        'is_verified' => false,
    ];

    if ($company) {
        $startTime = microtime(true);
        $user = \App\Models\User::create($userData);
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);

        echo "   ✅ Direct user creation successful in {$duration} ms\n";
        echo "   User ID: {$user->id}\n";

        // Clean up
        $user->delete();
        echo "   ✅ Test user cleaned up\n\n";
    } else {
        echo "   ❌ Cannot test - no valid company found\n\n";
    }

    echo "4. Queue configuration check...\n";
    echo "   Queue Driver: " . config('queue.default') . "\n";
    echo "   Database Connection: " . config('database.default') . "\n";

    // Test queue table exists
    $queueTableExists = DB::getSchemaBuilder()->hasTable('jobs');
    echo "   Jobs Table Exists: " . ($queueTableExists ? 'Yes' : 'No') . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
