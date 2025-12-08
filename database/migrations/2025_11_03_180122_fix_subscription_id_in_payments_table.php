<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Use raw SQL to handle the type conversion
        DB::statement('ALTER TABLE payments ALTER COLUMN subscription_id TYPE bigint USING subscription_id::bigint');

        Schema::table('payments', function (Blueprint $table) {
            // Add foreign key
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Drop foreign key
            $table->dropForeign(['subscription_id']);

            // Change back to string
            $table->string('subscription_id')->nullable()->change();
        });
    }
};
