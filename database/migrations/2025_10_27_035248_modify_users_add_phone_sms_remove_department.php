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
            // Add new columns
            $table->string('phone_number')->nullable()->after('email');
            $table->boolean('sms_invitation')->default(false)->after('phone_number');

            // Remove department_id column
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add back department_id column
            $table->ulid('department_id')->nullable()->after('branch_id');
            $table->foreign('department_id')->references('id')->on('departments');

            // Remove added columns
            $table->dropColumn(['phone_number', 'sms_invitation']);
        });
    }
};
