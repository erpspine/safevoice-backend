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
        Schema::table('incident_categories', function (Blueprint $table) {
            $table->dropForeign(['auto_assign_to_department']);
            $table->dropColumn(['color', 'priority_level', 'auto_assign_to_department']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incident_categories', function (Blueprint $table) {
            $table->string('color', 7)->nullable();
            $table->integer('priority_level')->default(3);
            $table->foreignUlid('auto_assign_to_department')->nullable()->constrained('departments')->onDelete('set null');
        });
    }
};
