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
        Schema::table('branches', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
            $table->dropIndex(['manager_id']);
            $table->dropColumn(['manager_id', 'operating_hours', 'timezone']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->foreignUlid('manager_id')->nullable()->constrained('users')->onDelete('set null');
            $table->json('operating_hours')->nullable();
            $table->string('timezone')->default('UTC');
            $table->index(['manager_id']);
        });
    }
};
