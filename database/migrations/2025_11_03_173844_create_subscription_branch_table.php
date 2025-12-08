<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_branch', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('subscription_id');
            $t->string('branch_id', 26); // ULID length
            $t->date('activated_from')->nullable();
            $t->date('activated_until')->nullable(); // usually = subscription->ends_on
            $t->timestamps();

            // Foreign key constraints
            $t->foreign('subscription_id')->references('id')->on('subscriptions')->onDelete('cascade');
            $t->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');

            // Unique constraint
            $t->unique(['subscription_id', 'branch_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('subscription_branch');
    }
};
