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
        Schema::create('recipients', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('role_hint')->nullable(); // Manager, Staff, Security, etc.
            $table->string('position')->nullable();
            $table->string('department')->nullable();
            $table->boolean('is_primary_contact')->default(false);
            $table->json('notification_preferences')->nullable(); // Email, SMS, etc.
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Indexes for better performance
            $table->index(['branch_id', 'status']);
            $table->index(['status']);
            $table->index(['is_primary_contact']);
            $table->index(['role_hint']);
            $table->index(['email']);
            $table->unique(['branch_id', 'email']); // Unique email per branch
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipients');
    }
};
