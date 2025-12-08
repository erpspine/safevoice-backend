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
        Schema::create('case_involved_parties', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Core relationships
            $table->ulid('user_id')->nullable(); // Optional link to registered user
            $table->ulid('case_id');

            // Contact information (for external parties or override for users)
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            // Involvement details
            $table->enum('involvement_type', [
                'witness',
                'perpetrator',
                'complainant',
                'victim',
                'reporter',
                'other'
            ]);
            $table->text('nature_of_involvement')->nullable();

            // Additional attributes
            $table->enum('contact_preference', ['email', 'phone', 'mail', 'none'])
                ->default('email');
            $table->json('additional_info')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('status')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table->foreign('case_id')
                ->references('id')
                ->on('cases')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Indexes for performance
            $table->index(['case_id', 'involvement_type']);
            $table->index(['user_id']);
            $table->index(['involvement_type']);
            $table->index(['is_anonymous']);
            $table->index(['status']);
            $table->index(['email']);

            // Composite indexes
            $table->index(['case_id', 'user_id']);
            $table->index(['case_id', 'involvement_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_involved_parties');
    }
};
