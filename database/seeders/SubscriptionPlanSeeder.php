<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'price' => 29.99,
                'max_branches' => 1,
                'grace_days' => 7,
                'description' => 'Perfect for small businesses just getting started with incident reporting. Includes basic features for a single branch.',
                'is_active' => true,
            ],
            [
                'name' => 'Professional',
                'price' => 79.99,
                'max_branches' => 5,
                'grace_days' => 14,
                'description' => 'Ideal for growing companies with multiple branches. Advanced reporting features and priority support included.',
                'is_active' => true,
            ],
            [
                'name' => 'Business',
                'price' => 149.99,
                'max_branches' => 15,
                'grace_days' => 21,
                'description' => 'Comprehensive solution for medium to large enterprises. Full feature access with enhanced security and compliance.',
                'is_active' => true,
            ],
            [
                'name' => 'Enterprise',
                'price' => 299.99,
                'max_branches' => 50,
                'grace_days' => 30,
                'description' => 'Ultimate package for large organizations. Unlimited features, dedicated support, and custom integrations available.',
                'is_active' => true,
            ],
            [
                'name' => 'Legacy Basic',
                'price' => 19.99,
                'max_branches' => 1,
                'grace_days' => 5,
                'description' => 'Discontinued basic plan. No longer available for new customers.',
                'is_active' => false,
            ],
        ];

        foreach ($plans as $planData) {
            SubscriptionPlan::firstOrCreate(
                ['name' => $planData['name']],
                $planData
            );
        }

        $this->command->info('Subscription plans seeded successfully!');
    }
}
