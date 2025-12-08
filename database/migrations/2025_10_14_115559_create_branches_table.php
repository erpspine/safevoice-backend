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
        Schema::create('branches', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->onDelete('cascade');
            $table->string('name');
            $table->string('location');
            $table->boolean('status')->default(true);
            $table->text('address')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->foreignUlid('manager_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('branch_code')->unique()->nullable();
            $table->json('operating_hours')->nullable();
            $table->string('timezone')->default('UTC');
            $table->timestamps();
            $table->softDeletes();

            // Indexes for better performance
            $table->index(['company_id', 'status']);
            $table->index(['status']);
            $table->index(['branch_code']);
            $table->index(['manager_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
