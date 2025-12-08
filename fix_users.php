<?php

// Fix admin user roles
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

echo "=== Fixing Admin User Roles ===\n";

// Update super admin
$superAdmin = User::where('email', 'superadmin@safevoice.com')->first();
if ($superAdmin) {
    $superAdmin->update([
        'role' => 'super_admin',
        'status' => 'active',
        'is_verified' => true
    ]);
    echo "Updated super admin: " . $superAdmin->name . "\n";
} else {
    echo "Super admin not found\n";
}

// Update admin
$admin = User::where('email', 'admin@safevoice.com')->first();
if ($admin) {
    $admin->update([
        'role' => 'admin',
        'status' => 'active',
        'is_verified' => true
    ]);
    echo "Updated admin: " . $admin->name . "\n";
} else {
    echo "Admin not found\n";
}

// Update branch manager
$manager = User::where('email', 'manager@testcompany.com')->first();
if ($manager) {
    $manager->update([
        'role' => 'branch_manager',
        'status' => 'active',
        'is_verified' => true
    ]);
    echo "Updated branch manager: " . $manager->name . "\n";
}

// Update investigator
$investigator = User::where('email', 'investigator@testcompany.com')->first();
if ($investigator) {
    $investigator->update([
        'role' => 'investigator',
        'status' => 'active',
        'is_verified' => true
    ]);
    echo "Updated investigator: " . $investigator->name . "\n";
}

// Update regular user (keep as user)
$user = User::where('email', 'user@testcompany.com')->first();
if ($user) {
    $user->update([
        'status' => 'active',
        'is_verified' => true
    ]);
    echo "Updated regular user: " . $user->name . "\n";
}

echo "\n=== Verification ===\n";
$adminUsers = User::whereIn('role', ['admin', 'super_admin'])->get();
foreach ($adminUsers as $user) {
    echo $user->name . " (" . $user->email . ") - Role: " . $user->role . ", Status: " . $user->status . "\n";
}
