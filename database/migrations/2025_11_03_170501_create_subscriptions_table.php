<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $t) {
            $t->id();
            $t->string('company_id', 26); // ULID length
            $t->string('plan_id', 26); // ULID length
            $t->date('starts_on');
            $t->date('ends_on');                 // end of paid period
            $t->date('grace_until')->nullable(); // ends_on + plan.grace_days
            $t->boolean('auto_renew')->default(false);
            $t->string('renewal_method')->nullable();   // mpesa,tigopesa,airtel,clickpesa,card
            $t->string('renewal_token')->nullable();    // token/customer ref from gateway (store securely)
            $t->boolean('cancel_at_period_end')->default(false);
            $t->string('status')->default('active');    // active|in_grace|past_due|canceled|expired
            $t->timestamps();

            // Foreign key constraints
            $t->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $t->foreign('plan_id')->references('id')->on('subscription_plans')->onDelete('cascade');

            // Indexes
            $t->index(['company_id', 'status']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
