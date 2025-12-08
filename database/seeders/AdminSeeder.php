<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Create a default company for admin users
        $defaultCompany = Company::firstOrCreate([
            'name' => 'SafeVoice Admin',
        ], [
            'email' => 'admin@safevoice.com',
            'contact' => '+1234567890',
            'address' => 'Admin Office',
            'plan' => 'enterprise',
            'status' => true,
        ]);

        // Create Super Admin
        User::firstOrCreate([
            'email' => 'superadmin@safevoice.com',
        ], [
            'name' => 'Super Administrator',
            'password' => Hash::make('SuperAdmin123!'),
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => User::STATUS_ACTIVE,
            'is_verified' => true,
            'company_id' => $defaultCompany->id,
        ]);

        // Create Company Admin
        User::firstOrCreate([
            'email' => 'admin@safevoice.com',
        ], [
            'name' => 'System Administrator',
            'password' => Hash::make('Admin123!'),
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
            'is_verified' => true,
            'company_id' => $defaultCompany->id,
        ]);

        // Create sample company for testing branch users
        $testCompany = Company::firstOrCreate([
            'name' => 'Test Company Inc',
        ], [
            'email' => 'info@testcompany.com',
            'contact' => '+1987654321',
            'address' => '123 Test Street, Test City',
            'plan' => 'premium',
            'status' => true,
        ]);

        // Create test branch manager
        User::firstOrCreate([
            'email' => 'manager@testcompany.com',
        ], [
            'name' => 'Branch Manager',
            'password' => Hash::make('Manager123!'),
            'role' => User::ROLE_BRANCH_MANAGER,
            'status' => User::STATUS_ACTIVE,
            'is_verified' => true,
            'company_id' => $testCompany->id,
            'employee_id' => 'BM001',
            'phone' => '+1555123456',
        ]);

        // Create test regular user
        User::firstOrCreate([
            'email' => 'user@testcompany.com',
        ], [
            'name' => 'Test User',
            'password' => Hash::make('User123!'),
            'role' => User::ROLE_USER,
            'status' => User::STATUS_ACTIVE,
            'is_verified' => true,
            'company_id' => $testCompany->id,
            'employee_id' => 'USR001',
            'phone' => '+1555654321',
        ]);

        // Create test investigator
        User::firstOrCreate([
            'email' => 'investigator@testcompany.com',
        ], [
            'name' => 'Case Investigator',
            'password' => Hash::make('Investigator123!'),
            'role' => User::ROLE_INVESTIGATOR,
            'status' => User::STATUS_ACTIVE,
            'is_verified' => true,
            'company_id' => $testCompany->id,
            'employee_id' => 'INV001',
            'phone' => '+1555789123',
        ]);

        $this->command->info('Admin and test users created successfully!');
        $this->command->info('Super Admin: superadmin@safevoice.com / SuperAdmin123!');
        $this->command->info('Admin: admin@safevoice.com / Admin123!');
        $this->command->info('Manager: manager@testcompany.com / Manager123!');
        $this->command->info('User: user@testcompany.com / User123!');
        $this->command->info('Investigator: investigator@testcompany.com / Investigator123!');
    }
}
