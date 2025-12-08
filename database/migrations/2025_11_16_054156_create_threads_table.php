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
        Schema::create('threads', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('case_id');
            $table->text('note')->nullable();
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('case_id')
                ->references('id')
                ->on('cases')
                ->onDelete('cascade');

            // Index for fast lookups
            $table->index('case_id', 'idx_threads_case');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('threads');
    }
};
