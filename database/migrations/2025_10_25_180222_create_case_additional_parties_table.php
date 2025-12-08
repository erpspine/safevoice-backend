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
        Schema::create('case_additional_parties', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('case_id');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('job_title')->nullable();
            $table->string('role');
            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraint
            $table->foreign('case_id')->references('id')->on('cases')->onDelete('cascade');

            // Indexes
            $table->index('case_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_additional_parties');
    }
};
