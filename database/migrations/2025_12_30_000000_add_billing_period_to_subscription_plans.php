<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // Add billing period column (monthly, yearly)
            $table->enum('billing_period', ['monthly', 'yearly'])->default('monthly')->after('price');

            // Add yearly pricing and discount fields
            $table->decimal('yearly_price', 10, 2)->nullable()->after('billing_period')->comment('Price for yearly billing (optional)');
            $table->decimal('discount_amount', 10, 2)->nullable()->after('yearly_price')->comment('Discount amount for yearly plan');
            $table->decimal('discount_percentage', 5, 2)->nullable()->after('discount_amount')->comment('Discount percentage for yearly plan');
            $table->decimal('amount_saved', 10, 2)->nullable()->after('discount_percentage')->comment('Total amount saved with yearly plan vs monthly');

            // Add index for billing period
            $table->index('billing_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropIndex(['billing_period']);
            $table->dropColumn([
                'billing_period',
                'yearly_price',
                'discount_amount',
                'discount_percentage',
                'amount_saved',
            ]);
        });
    }
};
