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
        Schema::table('cases', function (Blueprint $table) {
            $table->string('access_id')->nullable()->unique();
            $table->string('access_password')->nullable();
            $table->boolean('is_anonymous')->default(false);

            // Add indexes
            $table->index('access_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropIndex(['access_id']);
            $table->dropColumn(['access_id', 'access_password', 'is_anonymous']);
        });
    }
};
