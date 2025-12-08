<?php

require_once 'vendor/autoload.php';

// Boot Laravel application
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\CaseModel;
use App\Models\CaseInvolvedParty;

echo "Diagnosing User Relationship Issues\n";
echo "==================================\n\n";

try {
    // The employee IDs from your API response that have null user_info
    $employeeIds = [
        '01k87nxwpm7jrr7wq49haz52b9',
        '01k87vmj8r93sfkjpwbsszbpss'
    ];

    echo "Checking employee IDs from your API response...\n\n";

    foreach ($employeeIds as $empId) {
        echo "Employee ID: $empId\n";
        echo "-------------------\n";

        // Check if user exists with this employee_id
        $user = User::where('employee_id', $empId)->first();

        if ($user) {
            echo "✅ User found:\n";
            echo "  Name: " . $user->name . "\n";
            echo "  Email: " . $user->email . "\n";
            echo "  Phone: " . ($user->phone ?? 'Not provided') . "\n";
            echo "  Employee ID: " . $user->employee_id . "\n";
        } else {
            echo "❌ No user found with this employee_id\n";
        }

        // Check involved parties with this employee_id
        $involvedParties = CaseInvolvedParty::where('employee_id', $empId)->with('user')->get();
        echo "  Involved parties records: " . $involvedParties->count() . "\n";

        foreach ($involvedParties as $party) {
            echo "  Case ID: " . $party->case_id . "\n";
            echo "  Nature: " . $party->nature_of_involvement . "\n";
            echo "  User relationship: " . ($party->user ? 'Found' : 'NULL') . "\n";
            if ($party->user) {
                echo "    User name: " . $party->user->name . "\n";
            }
        }

        echo "\n";
    }

    // Let's also check what users exist in the system
    echo "Available users in system:\n";
    echo "=========================\n";
    $users = User::select('id', 'name', 'email', 'employee_id')->take(10)->get();

    foreach ($users as $user) {
        echo "Employee ID: " . ($user->employee_id ?? 'NULL') . " | Name: " . $user->name . "\n";
    }

    // Check if the employee IDs are actually ULIDs (user IDs) instead
    echo "\nChecking if employee IDs are actually user IDs...\n";
    echo "================================================\n";

    foreach ($employeeIds as $empId) {
        $userById = User::find($empId);
        if ($userById) {
            echo "✅ Found user by ID: $empId\n";
            echo "  Name: " . $userById->name . "\n";
            echo "  Employee ID: " . ($userById->employee_id ?? 'NULL') . "\n";
            echo "  This suggests the involved_parties.employee_id is storing user.id instead of user.employee_id\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
