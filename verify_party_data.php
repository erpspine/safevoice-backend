<?php
require 'vendor/autoload.php';

// Load Laravel app
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check the latest involved party record
$latestParty = App\Models\CaseInvolvedParty::latest()->first();

if ($latestParty) {
    echo "Latest Involved Party Record:\n";
    echo "ID: " . $latestParty->id . "\n";
    echo "Case ID: " . $latestParty->case_id . "\n";
    echo "Employee ID: " . $latestParty->employee_id . "\n";
    echo "Nature of Involvement: " . $latestParty->nature_of_involvement . "\n";
    echo "Created: " . $latestParty->created_at . "\n";
} else {
    echo "No involved party records found.\n";
}

// Check the latest additional party record
$latestAdditional = App\Models\CaseAdditionalParty::latest()->first();

if ($latestAdditional) {
    echo "\nLatest Additional Party Record:\n";
    echo "ID: " . $latestAdditional->id . "\n";
    echo "Case ID: " . $latestAdditional->case_id . "\n";
    echo "Name: " . $latestAdditional->name . "\n";
    echo "Email: " . $latestAdditional->email . "\n";
    echo "Role: " . $latestAdditional->role . "\n";
    echo "Job Title: " . $latestAdditional->job_title . "\n";
    echo "Created: " . $latestAdditional->created_at . "\n";
} else {
    echo "\nNo additional party records found.\n";
}
