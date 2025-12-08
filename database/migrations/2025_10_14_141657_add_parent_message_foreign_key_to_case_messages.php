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
        Schema::table('case_messages', function (Blueprint $table) {
            $table->foreign('parent_message_id')
                ->references('id')
                ->on('case_messages')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('case_messages', function (Blueprint $table) {
            $table->dropForeign(['parent_message_id']);
        });
    }
};
