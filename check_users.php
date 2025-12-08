<?php

// Quick test to check admin users
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

echo "=== Checking Admin Users ===\n";

$adminUsers = User::whereIn('role', ['admin', 'super_admin'])->get();

if ($adminUsers->count() > 0) {
    foreach ($adminUsers as $user) {
        echo "ID: " . $user->id . "\n";
        echo "Name: " . $user->name . "\n";
        echo "Email: " . $user->email . "\n";
        echo "Role: " . $user->role . "\n";
        echo "Status: " . $user->status . "\n";
        echo "Is Verified: " . ($user->is_verified ? 'Yes' : 'No') . "\n";
        echo "---\n";
    }
} else {
    echo "No admin users found!\n";
}

// Test the admins scope
echo "\n=== Testing admins() scope ===\n";
$adminsViaScope = User::admins()->get();
echo "Admin users via scope: " . $adminsViaScope->count() . "\n";

// Test specific email
echo "\n=== Testing specific email ===\n";
$specificUser = User::where('email', 'admin@safevoice.com')->first();
if ($specificUser) {
    echo "Found user: " . $specificUser->name . " (" . $specificUser->role . ")\n";
} else {
    echo "User not found with email admin@safevoice.com\n";
}

// Test with scope
$specificAdminUser = User::where('email', 'admin@safevoice.com')->admins()->first();
if ($specificAdminUser) {
    echo "Found admin user via scope: " . $specificAdminUser->name . "\n";
} else {
    echo "Admin user not found via scope\n";
}
