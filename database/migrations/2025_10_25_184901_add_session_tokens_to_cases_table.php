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
            $table->text('session_token')->nullable()->after('access_password');
            $table->timestamp('session_expires_at')->nullable()->after('session_token');

            // Add index for session token queries
            $table->index('session_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropIndex(['session_expires_at']);
            $table->dropColumn(['session_token', 'session_expires_at']);
        });
    }
};
