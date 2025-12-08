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
        Schema::create('investigators', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignUlid('company_id')->constrained('companies')->onDelete('cascade');
            $table->boolean('is_external')->default(false);

            // External investigator fields
            $table->string('external_name')->nullable();
            $table->string('external_email')->nullable();
            $table->string('external_phone')->nullable();
            $table->string('external_organization')->nullable();

            // Professional details
            $table->json('specializations')->nullable(); // Array of specialization areas
            $table->string('certification_number')->nullable();
            $table->date('license_expiry')->nullable();
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->enum('availability_status', ['available', 'busy', 'unavailable'])->default('available');
            $table->text('bio')->nullable();
            $table->boolean('status')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Indexes for better performance
            $table->index(['company_id', 'status']);
            $table->index(['status']);
            $table->index(['is_external']);
            $table->index(['availability_status']);
            $table->index(['user_id']);
            $table->index(['external_email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('investigators');
    }
};
