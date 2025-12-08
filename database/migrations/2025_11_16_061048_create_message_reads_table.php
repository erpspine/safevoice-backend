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
        Schema::create('message_reads', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('message_id');
            $table->ulid('user_id');
            $table->timestamp('read_at')->useCurrent();
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('message_id')
                ->references('id')
                ->on('case_messages')
                ->onDelete('cascade');

            // Unique constraint - one read record per message
            $table->unique('message_id', 'uq_mr_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_reads');
    }
};
