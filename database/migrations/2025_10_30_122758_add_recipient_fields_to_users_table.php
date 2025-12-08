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
        Schema::table('users', function (Blueprint $table) {
            // Primary recipient fields (for branch users only)
            $table->string('primary_recipient_name')->nullable();
            $table->string('primary_recipient_email')->nullable();
            $table->string('primary_recipient_phone')->nullable();
            $table->string('primary_recipient_position')->nullable();

            // Alternative recipient fields (for branch users only)  
            $table->string('alternative_recipient_name')->nullable();
            $table->string('alternative_recipient_email')->nullable();
            $table->string('alternative_recipient_phone')->nullable();
            $table->string('alternative_recipient_position')->nullable();

            // Add indexes for performance
            $table->index(['branch_id', 'primary_recipient_email']);
            $table->index(['branch_id', 'alternative_recipient_email']);
            $table->index(['primary_recipient_email']);
            $table->index(['alternative_recipient_email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'primary_recipient_email']);
            $table->dropIndex(['branch_id', 'alternative_recipient_email']);
            $table->dropIndex(['primary_recipient_email']);
            $table->dropIndex(['alternative_recipient_email']);

            $table->dropColumn([
                'primary_recipient_name',
                'primary_recipient_email',
                'primary_recipient_phone',
                'primary_recipient_position',
                'alternative_recipient_name',
                'alternative_recipient_email',
                'alternative_recipient_phone',
                'alternative_recipient_position'
            ]);
        });
    }
};
