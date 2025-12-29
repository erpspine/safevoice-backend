<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$user = User::where('email', 'testphone@example.com')->first();

if ($user) {
    $user->password = Hash::make('Investigator@123!');
    $user->save();
    echo "Password reset successful for: {$user->email}\n";
    echo "New password: Investigator@123!\n";
} else {
    echo "User not found\n";
}
