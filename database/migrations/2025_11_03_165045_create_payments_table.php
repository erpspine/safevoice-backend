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
        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('company_id', 26); // ULID length
            $table->string('subscription_plan_id', 26); // ULID length
            $table->string('subscription_id')->nullable(); // External subscription ID from payment gateway
            $table->integer('duration_months');
            $table->decimal('amount_paid', 10, 2);
            $table->enum('payment_method', ['card', 'bank_transfer', 'mobile_money', 'paypal', 'stripe', 'other']);
            $table->string('payment_reference')->unique(); // Transaction reference from payment gateway
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded', 'cancelled'])->default('pending');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('subscription_plan_id')->references('id')->on('subscription_plans')->onDelete('restrict');

            // Indexes for better query performance
            $table->index(['company_id', 'status']);
            $table->index(['subscription_plan_id']);
            $table->index(['period_start', 'period_end']);
            $table->index('payment_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
