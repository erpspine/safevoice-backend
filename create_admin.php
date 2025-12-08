<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->boot();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$user = User::firstOrCreate(
    ['email' => 'admin@test.com'],
    [
        'name' => 'Test Admin',
        'password' => Hash::make('password'),
        'role' => 'super_admin',
        'status' => 'active',
        'email_verified_at' => now(),
        'is_verified' => true
    ]
);

echo "Admin user created/updated: " . $user->email . " (Role: " . $user->role . ")\n";
