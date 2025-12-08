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
        Schema::create('thread_participants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('thread_id');
            $table->enum('role', ['reporter', 'investigator']);
            $table->ulid('last_read_message_id')->nullable();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('thread_id')
                ->references('id')
                ->on('threads')
                ->onDelete('cascade');

            // Unique constraint - one participant per thread
            $table->unique('thread_id', 'uq_tp_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thread_participants');
    }
};
