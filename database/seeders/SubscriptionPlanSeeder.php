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
            // Starter Plan - Monthly & Yearly
            [
                'name' => 'Starter',
                'price' => 29.99,
                'billing_period' => 'monthly',
                'yearly_price' => 299.99, // 10% discount = saves $59.81
                'discount_percentage' => 10.0,
                'discount_amount' => 59.88,
                'amount_saved' => 59.88,
                'grace_days' => 7,
                'description' => 'Perfect for small businesses just getting started with incident reporting. Includes basic features for a single branch.',
                'is_active' => true,
            ],
            [
                'name' => 'Starter Yearly',
                'price' => 299.99,
                'billing_period' => 'yearly',
                'yearly_price' => 299.99,
                'discount_percentage' => 10.0,
                'discount_amount' => 59.88,
                'amount_saved' => 59.88,
                'grace_days' => 7,
                'description' => 'Starter plan on yearly billing - save 10% compared to monthly payments.',
                'is_active' => true,
            ],

            // Professional Plan - Monthly & Yearly
            [
                'name' => 'Professional',
                'price' => 79.99,
                'billing_period' => 'monthly',
                'yearly_price' => 799.90, // 15% discount = saves $143.98
                'discount_percentage' => 15.0,
                'discount_amount' => 143.98,
                'amount_saved' => 143.98,
                'grace_days' => 14,
                'description' => 'Ideal for growing companies with multiple branches. Advanced reporting features and priority support included.',
                'is_active' => true,
            ],
            [
                'name' => 'Professional Yearly',
                'price' => 799.90,
                'billing_period' => 'yearly',
                'yearly_price' => 799.90,
                'discount_percentage' => 15.0,
                'discount_amount' => 143.98,
                'amount_saved' => 143.98,
                'grace_days' => 14,
                'description' => 'Professional plan on yearly billing - save 15% compared to monthly payments.',
                'is_active' => true,
            ],

            // Business Plan - Monthly & Yearly
            [
                'name' => 'Business',
                'price' => 149.99,
                'billing_period' => 'monthly',
                'yearly_price' => 1679.88, // 20% discount = saves $419.88
                'discount_percentage' => 20.0,
                'discount_amount' => 419.88,
                'amount_saved' => 419.88,
                'grace_days' => 21,
                'description' => 'Comprehensive solution for medium to large enterprises. Full feature access with enhanced security and compliance.',
                'is_active' => true,
            ],
            [
                'name' => 'Business Yearly',
                'price' => 1679.88,
                'billing_period' => 'yearly',
                'yearly_price' => 1679.88,
                'discount_percentage' => 20.0,
                'discount_amount' => 419.88,
                'amount_saved' => 419.88,
                'grace_days' => 21,
                'description' => 'Business plan on yearly billing - save 20% compared to monthly payments.',
                'is_active' => true,
            ],

            // Enterprise Plan - Monthly & Yearly
            [
                'name' => 'Enterprise',
                'price' => 299.99,
                'billing_period' => 'monthly',
                'yearly_price' => 3239.88, // 20% discount = saves $959.88
                'discount_percentage' => 20.0,
                'discount_amount' => 959.88,
                'amount_saved' => 959.88,
                'grace_days' => 30,
                'description' => 'Ultimate package for large organizations. Unlimited features, dedicated support, and custom integrations available.',
                'is_active' => true,
            ],
            [
                'name' => 'Enterprise Yearly',
                'price' => 3239.88,
                'billing_period' => 'yearly',
                'yearly_price' => 3239.88,
                'discount_percentage' => 20.0,
                'discount_amount' => 959.88,
                'amount_saved' => 959.88,
                'grace_days' => 30,
                'description' => 'Enterprise plan on yearly billing - save 20% compared to monthly payments.',
                'is_active' => true,
            ],

            // Legacy Plan (Discontinued)
            [
                'name' => 'Legacy Basic',
                'price' => 19.99,
                'billing_period' => 'monthly',
                'yearly_price' => null,
                'discount_percentage' => null,
                'discount_amount' => null,
                'amount_saved' => null,
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

        $this->command->info('Subscription plans (Monthly & Yearly) seeded successfully!');
    }
}
