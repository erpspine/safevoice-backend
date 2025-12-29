<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetInvestigatorPassword extends Command
{
    protected $signature = 'user:reset-password {email} {password}';
    protected $description = 'Reset user password';

    public function handle()
    {
        $email = $this->argument('email');
        $password = $this->argument('password');

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error('User not found');
            return 1;
        }

        $user->password = Hash::make($password);
        $user->save();

        $this->info("Password reset successful for: {$user->email}");
        return 0;
    }
}
