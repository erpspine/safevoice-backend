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
            // Add recipient type column for branch users
            $table->enum('recipient_type', ['primary', 'alternative'])->nullable()->after('branch_id');

            // Add index for performance
            $table->index(['branch_id', 'recipient_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'recipient_type']);
            $table->dropColumn('recipient_type');
        });
    }
};
